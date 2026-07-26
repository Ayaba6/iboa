<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * [ACHATS #9] Idempotence DURABLE et générique.
 *
 * `remember($scope, $key, $payload, $create)` exécute `$create` UNE SEULE FOIS
 * pour une clé logique donnée. Un rejeu (même clé + même empreinte de payload)
 * renvoie le document déjà créé sans le recréer. Une même clé avec un payload
 * DIFFÉRENT est refusée explicitement.
 *
 * Concurrence : la barrière est l'index unique DB `(company_id, scope, key)`.
 * La transaction perdante voit la violation d'unicité, annule sa propre création
 * (rollback) et renvoie le document du gagnant — jamais de doublon, jamais
 * d'exception SQL brute exposée.
 *
 * Ne stocke qu'une EMPREINTE du contenu économique (aucun secret, aucune pièce
 * jointe brute).
 */
class IdempotencyService
{
    public const MAX_KEY_LENGTH = 128;

    public function remember(
        string $scope,
        ?string $key,
        array $payload,
        \Closure $create,
        ?string $source = null,
        ?string $externalReference = null,
    ): Model {
        $key = trim((string) $key);

        // Clé facultative : sans clé, chemin normal (les autres gardes métier
        // — dont l'anti-doublon A1 — restent actives).
        if ($key === '') {
            return $create();
        }
        if (mb_strlen($key) > self::MAX_KEY_LENGTH) {
            throw new \RuntimeException(sprintf(
                'Clé d\'idempotence trop longue (%d caractères, maximum %d).',
                mb_strlen($key), self::MAX_KEY_LENGTH
            ));
        }

        $companyId = currentCompany()?->id;
        $hash      = hash('sha256', json_encode($payload));

        // Rejeu rapide : clé déjà scellée et validée.
        $existing = IdempotencyKey::where('company_id', $companyId)
            ->where('scope', $scope)->where('idempotency_key', $key)->first();
        if ($existing) {
            return $this->resolveExisting($existing, $hash);
        }

        // Chemin leader : réclame la clé PUIS crée, dans une transaction atomique
        // (un échec de création annule la réclamation → rejeu propre).
        try {
            return DB::transaction(function () use ($companyId, $scope, $key, $hash, $source, $externalReference, $create) {
                $record = IdempotencyKey::create([
                    'company_id' => $companyId, 'scope' => $scope, 'idempotency_key' => $key,
                    'payload_hash' => $hash, 'source' => $source, 'external_reference' => $externalReference,
                    'status' => 'completed',
                ]);
                $subject = $create();
                $record->forceFill([
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id'   => $subject->getKey(),
                ])->save();

                return $subject;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Course perdue sur la clé : le gagnant a scellé et commité.
            $winner = IdempotencyKey::where('company_id', $companyId)
                ->where('scope', $scope)->where('idempotency_key', $key)->first();
            if ($winner) {
                return $this->resolveExisting($winner, $hash);
            }
            throw new \RuntimeException('Conflit d\'idempotence concurrent — réessayez.', 0, $e);
        }
    }

    private function resolveExisting(IdempotencyKey $record, string $hash): Model
    {
        if ($record->payload_hash !== $hash) {
            throw new \RuntimeException(
                'Clé d\'idempotence réutilisée avec un contenu différent — requête refusée '
                . '(risque de doublon / falsification de rejeu).'
            );
        }
        $subject = $record->subject;
        if (! $subject) {
            throw new \RuntimeException('Requête idempotente incomplète ou en cours — réessayez.');
        }

        return $subject;
    }
}
