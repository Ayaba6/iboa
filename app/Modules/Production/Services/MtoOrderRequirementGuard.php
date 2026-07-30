<?php

namespace App\Modules\Production\Services;

use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * [MTO — règle 1] Un ordre de fabrication portant un article MTO doit être
 * rattaché à une commande client.
 *
 * En MTO (make to order) on ne fabrique que du vendu : un OF sans commande
 * produit du stock qu'aucun client n'a demandé, immobilise de la matière et
 * casse la traçabilité « client → commande → OF » exigée par la règle 19.
 *
 * La règle est portée ICI, et appelée par ProductionService::create() — le seul
 * chemin de création métier — pour qu'elle s'applique quel que soit le canal
 * (formulaire, API, import, commande Artisan) et non au seul formulaire HTTP.
 *
 * Elle NE s'applique PAS :
 *   - au MTS (make to stock), dont c'est le fonctionnement normal ;
 *   - à un article dont `production_mode` est NULL (article historique non
 *     qualifié) — la règle ne se déclenche que sur une intention explicite,
 *     jamais par défaut sur des données incomplètes.
 *
 * Dérogation : possible, mais jamais silencieuse. Elle exige la permission
 * dédiée ET un motif non vide, et laisse une trace dans le journal d'audit
 * scellé plus une alerte dans le canal de sécurité.
 */
class MtoOrderRequirementGuard
{
    public const PERMISSION = 'production.create_mto_without_order';

    public const ACTION = 'production.derogation.mto_sans_commande';

    /**
     * Vérifie la règle AVANT création.
     *
     * @param  array<string,mixed>  $data  Données de l'OF en cours de création.
     * @param  string  $channel  Canal d'origine : interface, api, import, commande…
     * @return string|null Motif de dérogation retenu, ou null si aucune dérogation
     *                     n'était nécessaire (cas nominal).
     *
     * @throws ValidationException
     */
    public function assertSatisfied(array $data, string $channel = 'interface'): ?string
    {
        if (! $this->requiresOrder($data)) {
            return null;
        }

        $product = $this->product($data);
        $user    = Auth::user();

        // 1. Permission d'abord : sans elle, le motif ne se discute même pas.
        if (! $user || ! $user->can(self::PERMISSION)) {
            throw ValidationException::withMessages([
                'order_id' => sprintf(
                    'OF refusé : « %s » est un article fabriqué à la commande (MTO). '
                    . 'Rattachez-le à une commande client, ou faites-le créer par un '
                    . 'utilisateur disposant de la dérogation « %s ».',
                    $product?->name ?? 'cet article',
                    self::PERMISSION
                ),
            ]);
        }

        // 2. Motif ensuite : la permission autorise la dérogation, elle ne la justifie pas.
        $motif = trim((string) ($data['derogation_motif'] ?? ''));
        if ($motif === '') {
            throw ValidationException::withMessages([
                'derogation_motif' => 'OF MTO sans commande client : le motif de la dérogation '
                    . 'est obligatoire (il est journalisé et opposable).',
            ]);
        }

        return $motif;
    }

    /**
     * Journalise la dérogation APRÈS création — l'OF doit exister pour être cité.
     * Utilise le journal d'audit scellé existant (AuditService), qui capte déjà
     * l'utilisateur, l'horodatage, l'adresse IP, l'agent et l'URL.
     */
    public function journalize(ProductionOrder $order, string $motif, string $channel = 'interface'): void
    {
        $product = $order->product_id ? Product::find($order->product_id) : null;

        app(AuditService::class)->log(
            self::ACTION,
            $order,
            // Ancien état : l'OF n'existait pas. On le dit explicitement plutôt que
            // de laisser un tableau vide, ambigu à la relecture.
            ['existence' => 'inexistant'],
            [
                'existence'       => 'cree',
                'statut'          => $order->status,
                'of'              => $order->number,
                'production_order_id' => $order->id,
                'product_id'      => $order->product_id,
                'produit'         => $product?->name,
                'production_mode' => $product?->production_mode,
                'order_id'        => null,
                'motif'           => $motif,
                'canal'           => $channel,
            ]
        );

        Log::channel('security')->warning(self::ACTION, [
            'of'      => $order->number,
            'produit' => $product?->name,
            'user_id' => Auth::id(),
            'motif'   => $motif,
            'canal'   => $channel,
        ]);
    }

    /**
     * La règle s'applique-t-elle à ces données ? Vrai si l'article est explicitement
     * MTO et qu'aucune commande n'est rattachée.
     *
     * @param  array<string,mixed>  $data
     */
    public function requiresOrder(array $data): bool
    {
        if (! empty($data['order_id'])) {
            return false;
        }

        return $this->product($data)?->production_mode === 'mto';
    }

    /** @param  array<string,mixed>  $data */
    private function product(array $data): ?Product
    {
        if (empty($data['product_id'])) {
            return null;
        }

        return Product::find($data['product_id']);
    }
}
