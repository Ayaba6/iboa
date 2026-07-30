<?php

namespace App\Console\Commands;

use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilCompatibilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [MTO §9] Mesure ce que la garde de compatibilité bobine NE PEUT PAS vérifier.
 *
 * `CoilCompatibilityService` bloque un écart avéré, mais autorise ce qu'il ne
 * peut pas comparer : une caractéristique absente d'un côté n'est pas une
 * correspondance, c'est une inconnue. Cette permissivité est transitoire et doit
 * rester CHIFFRÉE, sinon elle devient un angle mort permanent. C'est l'objet de
 * cette commande.
 *
 * Lecture seule, sans option de réparation. Compléter automatiquement la couleur
 * ou l'épaisseur d'une bobine à partir de son article serait inventer une
 * caractéristique physique — exactement le genre de donnée qu'une reprise de
 * traçabilité ne pardonne pas.
 *
 * Sort en code 1 dès qu'une anomalie de risque ÉLEVÉ est présente.
 */
class AuditCoilCompatibility extends Command
{
    protected $signature = 'a3:audit-coil-compatibility {--company= : Restreindre à une société}';

    protected $description = 'Recense les bobines dont les caractéristiques empêchent tout contrôle de compatibilité (lecture seule).';

    public function handle(): int
    {
        $companyId = $this->option('company');
        $eleve = 0;

        $this->info('[MTO §9] Compatibilité bobine — données manquantes et incohérences');
        $this->newLine();

        // ── 1. Fiches bobines incomplètes ────────────────────────────────────
        $coils = Coil::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'reference', 'product_id', 'stock_lot_id', 'color', 'thickness', 'width', 'nuance', 'coating', 'status']);

        $manques = [];
        foreach ($coils as $c) {
            $absents = [];
            foreach ([
                'article'   => $c->product_id,
                'lot'       => $c->stock_lot_id,
                'couleur'   => $c->color,
                'épaisseur' => $c->thickness,
                'largeur'   => $c->width,
                'nuance'    => $c->nuance,
                'revêtement' => $c->coating,
            ] as $label => $value) {
                if ($value === null || $value === '' || (is_numeric($value) && (float) $value <= 0)) {
                    $absents[] = $label;
                }
            }

            if ($absents === []) {
                continue;
            }

            // L'ARTICLE et le LOT sont structurants : sans eux, ni l'appartenance à
            // la nomenclature ni la traçabilité matière ne sont vérifiables.
            $risque = (in_array('article', $absents, true) || in_array('lot', $absents, true))
                ? 'ÉLEVÉ'
                : (count($absents) >= 3 ? 'MOYEN' : 'FAIBLE');

            if ($risque === 'ÉLEVÉ') {
                $eleve++;
            }

            $manques[] = [$c->reference, $c->status, implode(', ', $absents), $risque];
        }

        $this->line('  <options=bold>1. Fiches bobines incomplètes</>');
        if ($manques === []) {
            $this->info('     Aucune : toutes les bobines sont comparables.');
        } else {
            $this->table(['Bobine', 'Statut', 'Caractéristiques absentes', 'Risque'], $manques);
        }
        $this->newLine();

        // ── 2. Consommations historiquement incompatibles ────────────────────
        $this->line('  <options=bold>2. Consommations incompatibles au regard de la règle actuelle</>');

        $service = app(CoilCompatibilityService::class);
        $incompatibles = [];

        $consos = DB::table('production_consumptions as pc')
            ->join('production_orders as po', 'po.id', '=', 'pc.production_order_id')
            ->whereNull('pc.reversed_at')
            ->whereNotNull('pc.coil_id')
            ->when($companyId, fn ($q) => $q->where('po.company_id', $companyId))
            ->orderBy('pc.id')
            ->get(['pc.id', 'pc.coil_id', 'pc.production_order_id']);

        foreach ($consos as $row) {
            $order = ProductionOrder::find($row->production_order_id);
            $coil  = Coil::withTrashed()->find($row->coil_id);
            if (! $order || ! $coil) {
                continue;
            }

            // La bobine est relue dans son état ACTUEL : une bobine désormais
            // épuisée l'était rarement au moment de la consommation. Seuls les
            // motifs structurels (article, lot, dépôt, société) sont retenus ici —
            // les autres décriraient le présent, pas la décision d'alors.
            $motifs = array_values(array_filter(
                $service->blockingReasons($order, $coil),
                fn ($m) => ! str_contains($m, 'épuisée')
                    && ! str_contains($m, 'divisée')
                    && ! str_contains($m, 'statut qualité')
                    && ! str_contains($m, 'état opérationnel')
            ));

            if ($motifs !== []) {
                $eleve++;
                $incompatibles[] = [$order->number, $coil->reference, mb_strimwidth(implode(' ', $motifs), 0, 90, '…')];
            }
        }

        if ($incompatibles === []) {
            $this->info('     Aucune : toutes les consommations actives respectent la règle.');
        } else {
            $this->table(['OF', 'Bobine', 'Motif'], $incompatibles);
        }
        $this->newLine();

        // ── 3. Bobines engagées sur plusieurs OF ─────────────────────────────
        $this->line('  <options=bold>3. Bobines consommées par plusieurs OF</>');

        $partagees = DB::table('production_consumptions as pc')
            ->join('coils as c', 'c.id', '=', 'pc.coil_id')
            ->join('production_orders as po', 'po.id', '=', 'pc.production_order_id')
            ->whereNull('pc.reversed_at')
            ->when($companyId, fn ($q) => $q->where('po.company_id', $companyId))
            ->groupBy('c.id', 'c.reference')
            ->havingRaw('COUNT(DISTINCT pc.production_order_id) > 1')
            ->get(['c.reference', DB::raw('COUNT(DISTINCT pc.production_order_id) as nb_of')]);

        if ($partagees->isEmpty()) {
            $this->info('     Aucune.');
        } else {
            // Ce n'est PAS une anomalie en soi : une bobine de 5 tonnes alimente
            // légitimement plusieurs OF. C'est un point de vigilance sur la
            // répartition des coûts et la traçabilité, pas une erreur.
            $this->table(
                ['Bobine', 'Nombre d’OF'],
                $partagees->map(fn ($p) => [$p->reference, $p->nb_of])->all()
            );
            $this->line('     <fg=gray>Normal si la bobine est volumineuse — à surveiller pour la ventilation des coûts.</>');
        }
        $this->newLine();

        if ($eleve > 0) {
            $this->warn(sprintf('  %d anomalie(s) de risque ÉLEVÉ.', $eleve));

            return self::FAILURE;
        }

        $this->info('  Aucune anomalie de risque élevé.');

        return self::SUCCESS;
    }
}
