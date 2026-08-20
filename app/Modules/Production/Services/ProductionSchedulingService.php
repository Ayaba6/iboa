<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [Production] ORDONNANCEMENT — positionne les OF dans le temps, sur une ligne.
 *
 * À ne pas confondre avec le PLAN DE CHARGE (PlanningService), qui mesure une
 * capacité agrégée par centre de travail et par machine. Le plan de charge dit
 * « la ligne est occupée à 80 % cette semaine » ; l'ordonnancement dit « cet OF
 * tourne mardi de 8 h à 12 h sur la ligne 2 ». L'un dimensionne, l'autre séquence.
 *
 * AUCUNE MIGRATION : les colonnes existaient déjà et n'étaient jamais
 * renseignées — `date_debut_prevue`, `heure_debut_prevue`, `date_fin_prevue`,
 * `heure_fin_prevue`, `production_line_id`. Les six OF de la base ont été
 * produits et clôturés sans une seule date de planification.
 *
 * DEUX DATES DE DÉBUT COEXISTENT dans la table : `date_debut_prevue`, la notion
 * fine (seule accompagnée d'heures), et `date_fabrication_prevue`, l'héritée.
 * La fiche OF arbitre déjà en affichant « début ?? fabrication ». On écrit donc
 * sur la première et on lit avec repli sur la seconde, pour que l'existant
 * apparaisse au planning. Unifier les deux colonnes est une décision de schéma
 * qui dépasse ce service.
 */
class ProductionSchedulingService
{
    /** Statuts d'OF qui ne s'ordonnancent plus : la production est finie ou abandonnée. */
    public const NON_ORDONNANCABLES = ['termine', 'annule'];

    /** Statuts de ligne interdisant toute affectation. */
    public const LIGNES_INDISPONIBLES = ['indisponible', 'arretee', 'en_panne'];

    /**
     * Positionne un OF sur une ligne, à une date et une heure.
     *
     * @throws ValidationException
     */
    public function schedule(ProductionOrder $order, array $data): ProductionOrder
    {
        if (in_array($order->status, self::NON_ORDONNANCABLES, true)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'OF %s « %s » : un ordre clôturé ou annulé ne s’ordonnance plus.',
                    $order->number, $order->status
                ),
            ]);
        }

        // Un select vide renvoie '' et non null : sans cette normalisation, `?? `
        // ne prendrait pas le relais et l'on perdrait la ligne déjà affectée.
        $ligneId = ($data['production_line_id'] ?? null) ?: $order->production_line_id;
        $debut   = $this->instant($data['date_debut_prevue'] ?? null, $data['heure_debut_prevue'] ?? null, '00:00');
        $fin     = $this->instant($data['date_fin_prevue'] ?? null, $data['heure_fin_prevue'] ?? null, '23:59');

        if ($ligneId) {
            $ligne = ProductionLine::find($ligneId);
            if (! $ligne) {
                throw ValidationException::withMessages(['production_line_id' => 'Ligne de production introuvable.']);
            }
            if (in_array($ligne->status, self::LIGNES_INDISPONIBLES, true)) {
                throw ValidationException::withMessages([
                    'production_line_id' => sprintf('Ligne « %s » indisponible (%s) — affectation refusée.', $ligne->name, $ligne->status),
                ]);
            }
        }

        if ($debut && $fin && $fin->lt($debut)) {
            throw ValidationException::withMessages([
                'date_fin_prevue' => 'La fin planifiée précède le début.',
            ]);
        }

        return DB::transaction(function () use ($order, $data, $ligneId, $debut, $fin) {
            // Verrou : deux planificateurs visant le même créneau ne doivent pas
            // le remporter tous les deux. La perdante lit l'état commité.
            $order = ProductionOrder::lockForUpdate()->findOrFail($order->id);

            // Sans fin déclarée, l'OF occupe la journée de son début — même règle
            // que `finDe()`. Sinon un ordre sans date de fin traverserait la
            // détection sans jamais entrer en conflit avec personne.
            $finEffective = $fin ?: $debut?->copy()->endOfDay();

            if ($ligneId && $debut && $finEffective) {
                $conflit = $this->conflit($order, (int) $ligneId, $debut, $finEffective);
                if ($conflit) {
                    throw ValidationException::withMessages([
                        'date_debut_prevue' => sprintf(
                            'Créneau déjà occupé sur cette ligne par l’OF %s (%s → %s). '
                            . 'Une ligne ne produit qu’un ordre à la fois.',
                            $conflit->number,
                            $this->debutDe($conflit)?->format('d/m/Y H:i') ?? '—',
                            $this->finDe($conflit)?->format('d/m/Y H:i') ?? '—'
                        ),
                    ]);
                }
            }

            $order->update(array_filter([
                'production_line_id' => $ligneId,
                'date_debut_prevue'  => $debut?->toDateString(),
                // PONT ENTRE LES DEUX COLONNES DE DÉBUT : le plan de charge trie et
                // filtre sur `date_fabrication_prevue`. Sans cette écriture, un OF
                // ordonnancé ici apparaîtrait « sans date » là-bas. Les deux colonnes
                // restent alignées tant que leur fusion n'est pas arbitrée en schéma.
                'date_fabrication_prevue' => $debut?->toDateString(),
                'heure_debut_prevue' => $debut?->format('H:i'),
                'date_fin_prevue'    => $fin?->toDateString(),
                'heure_fin_prevue'   => $fin?->format('H:i'),
                'priorite'           => $data['priorite'] ?? null,
                'equipe_prevue'      => $data['equipe_prevue'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));

            return $order->fresh();
        });
    }

    /** Statuts où la production a physiquement commencé sur la ligne. */
    public const DEMARRES = ['lance', 'en_cours', 'termine_partiellement', 'suspendu'];

    /**
     * Retire un OF du planning sans toucher à son avancement : consommations,
     * déclarations et quantités produites restent intactes.
     *
     * La ligne n'est libérée que si l'OF n'a pas démarré. Une fois lancé, l'ordre
     * EST sur cette ligne — le nier dans la base ne l'en fait pas descendre. On
     * lui retire alors son créneau, pas son atelier.
     */
    public function unschedule(ProductionOrder $order): ProductionOrder
    {
        $champs = [
            'date_debut_prevue'  => null,
            'heure_debut_prevue' => null,
            'date_fin_prevue'    => null,
            'heure_fin_prevue'   => null,
        ];

        if (! in_array($order->status, self::DEMARRES, true)) {
            // Sans cela, un OF gardant `date_fabrication_prevue` resterait affiché
            // comme placé par le repli de `debutDe()` : le bouton semblerait inerte.
            $champs['production_line_id'] = null;
        }

        $order->update($champs);

        return $order->fresh();
    }

    /**
     * Tableau d'ordonnancement : OF placés sur la période, et OF à placer.
     *
     * @return array{lignes: Collection, places: Collection, a_placer: Collection, du: Carbon, au: Carbon}
     */
    public function board(?string $du = null, ?string $au = null): array
    {
        $debut = $du ? Carbon::parse($du)->startOfDay() : now()->startOfWeek();
        $fin   = $au ? Carbon::parse($au)->endOfDay() : (clone $debut)->addDays(13)->endOfDay();

        $ordonnancables = ProductionOrder::with(['product:id,name,reference', 'productionLine:id,name', 'client:id,name'])
            ->whereNotIn('status', self::NON_ORDONNANCABLES)
            ->orderByRaw('date_debut_prevue IS NULL, date_debut_prevue, heure_debut_prevue')
            ->get();

        // `debut_effectif` / `fin_effectif` sont des ACCESSEURS du modèle : la vue
        // y accède directement. Les poser ici comme attributs marquait chaque OF
        // du tableau comme modifié sur des colonnes inexistantes.
        $places = $ordonnancables->filter(function (ProductionOrder $o) use ($debut, $fin) {
            $d = $this->debutDe($o);

            return $o->production_line_id && $d && $d->between($debut, $fin);
        })->values();

        // À placer : sans ligne, ou sans date — un OF ne peut pas se produire
        // « quelque part, un jour ». Les deux manques sont des trous de planning.
        $aPlacer = $ordonnancables->reject(function (ProductionOrder $o) use ($debut, $fin) {
            $d = $this->debutDe($o);

            return $o->production_line_id && $d && $d->between($debut, $fin);
        })->values();

        return [
            'lignes'   => ProductionLine::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'status']),
            'places'   => $places,
            'a_placer' => $aPlacer,
            'du'       => $debut,
            'au'       => $fin,
        ];
    }

    /**
     * Un autre OF occupe-t-il déjà ce créneau sur cette ligne ?
     *
     * Chevauchement au sens strict : deux intervalles se croisent dès que l'un
     * commence avant que l'autre finisse. Les OF sans créneau complet sont
     * ignorés — on ne peut pas entrer en conflit avec une date inconnue.
     */
    public function conflit(ProductionOrder $order, int $ligneId, Carbon $debut, Carbon $fin): ?ProductionOrder
    {
        $candidats = ProductionOrder::where('production_line_id', $ligneId)
            ->whereKeyNot($order->id)
            ->whereNotIn('status', self::NON_ORDONNANCABLES)
            ->whereNotNull('date_debut_prevue')
            ->get();

        foreach ($candidats as $autre) {
            $d = $this->debutDe($autre);
            $f = $this->finDe($autre);
            if (! $d || ! $f) {
                continue;
            }
            if ($debut->lt($f) && $d->lt($fin)) {
                return $autre;
            }
        }

        return null;
    }

    /**
     * Début planifié. Repli sur `date_fabrication_prevue` : c'est l'arbitrage que
     * la fiche OF applique déjà, et il fait apparaître l'existant au planning.
     */
    public function debutDe(ProductionOrder $order): ?Carbon
    {
        return $order->debut_effectif;
    }

    public function finDe(ProductionOrder $order): ?Carbon
    {
        return $order->fin_effectif;
    }

    /** Assemble une date et une heure « HH:MM » en un instant. */
    private function instant(?string $date, ?string $heure, string $defaut): ?Carbon
    {
        if (! $date) {
            return null;
        }
        $h = $heure && preg_match('/^\d{1,2}:\d{2}/', $heure) ? substr($heure, 0, 5) : $defaut;

        return Carbon::parse(Carbon::parse($date)->toDateString().' '.$h);
    }
}
