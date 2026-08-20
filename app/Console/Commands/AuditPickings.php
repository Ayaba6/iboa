<?php

namespace App\Console\Commands;

use App\Models\SalesPicking;
use App\Models\SalesPickingAllocation;
use App\Models\SalesPickingControl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [Ventes §20] Audit des bons de préparation — LECTURE SEULE.
 *
 * Onze détections. La commande n'écrit jamais : elle constate et alerte.
 * Une incohérence n'est jamais réparée en silence — c'est la règle du projet :
 * on signale, on n'invente pas, on ne recolle pas l'historique.
 *
 * Retourne exit 1 en présence d'au moins une anomalie CRITIQUE.
 *
 * Les bons de chargement historiques (`bon_preparations`) ne sont PAS audités
 * ici : ils n'ont pas de lignes, restent LEGACY_UNQUANTIFIED, et leur appliquer
 * des invariants quantitatifs produirait de fausses anomalies.
 */
class AuditPickings extends Command
{
    protected $signature = 'a3:audit-pickings {--company= : Restreindre à une société}';

    protected $description = 'Audit des bons de préparation (reliquats, allocations, qualité, contrôles, cohérence aval).';

    private const EPSILON = 0.0005;

    public function handle(): int
    {
        $critical = 0;
        $informational = 0;
        $companyId = $this->option('company');

        $this->info('── Audit des bons de préparation ──');

        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_pickings')) {
            $this->warn('Table sales_pickings absente : workflow quantifié non déployé. Rien à auditer.');

            return self::SUCCESS;
        }

        $scope = fn ($query, string $alias = 'sp') => $companyId
            ? $query->where("{$alias}.company_id", $companyId)
            : $query;

        // 1. Préparation orpheline : pas de commande rattachée.
        $orphans = $scope(DB::table('sales_pickings as sp')
            ->leftJoin('orders as o', 'o.id', '=', 'sp.order_id')
            ->whereNull('o.id'))
            ->count();
        $this->line("1. Préparations sans commande : {$orphans}");
        $critical += $orphans;

        // 2. Somme des engagements > reliquat réel de la ligne de commande.
        //    Le reliquat est recalculé DEPUIS la commande, jamais depuis le
        //    snapshot du bon : c'est justement le snapshot qu'on vérifie.
        $overCommitted = DB::table('sales_picking_items as spi')
            ->join('sales_pickings as sp', 'sp.id', '=', 'spi.sales_picking_id')
            ->join('order_items as oi', 'oi.id', '=', 'spi.order_item_id')
            ->where('sp.status', '!=', SalesPicking::STATUS_ANNULE)
            ->when($companyId, fn ($q) => $q->where('sp.company_id', $companyId))
            // Sélection EXPLICITE : `select *` viole only_full_group_by, actif par
            // défaut sur MySQL 8. Seules les colonnes groupées ou agrégées sortent.
            ->groupBy('spi.order_item_id', 'oi.quantity', 'oi.delivered_quantity')
            ->havingRaw('SUM(spi.qty_remaining_snapshot) > (oi.quantity - COALESCE(oi.delivered_quantity, 0)) + ?', [self::EPSILON])
            ->get(['spi.order_item_id', 'oi.quantity', 'oi.delivered_quantity', DB::raw('SUM(spi.qty_remaining_snapshot) as engage')]);
        $this->line('2. Lignes de commande sur-engagées par les préparations : '.$overCommitted->count());
        $critical += $overCommitted->count();

        // 3. Allocations dépassant le reliquat figé de leur propre ligne.
        $overAllocated = DB::table('sales_picking_items as spi')
            ->join('sales_pickings as sp', 'sp.id', '=', 'spi.sales_picking_id')
            ->leftJoin('sales_picking_allocations as spa', function ($j) {
                $j->on('spa.sales_picking_item_id', '=', 'spi.id')
                    ->where('spa.status', '!=', SalesPickingAllocation::STATUS_ANNULEE);
            })
            ->where('sp.status', '!=', SalesPicking::STATUS_ANNULE)
            ->when($companyId, fn ($q) => $q->where('sp.company_id', $companyId))
            ->groupBy('spi.id', 'spi.qty_remaining_snapshot')
            ->havingRaw('COALESCE(SUM(spa.quantity), 0) > spi.qty_remaining_snapshot + ?', [self::EPSILON])
            ->get(['spi.id', 'spi.qty_remaining_snapshot', DB::raw('COALESCE(SUM(spa.quantity), 0) as alloue')]);
        $this->line('3. Lignes dont les allocations dépassent le reliquat : '.$overAllocated->count());
        $critical += $overAllocated->count();

        // 4. Lot sur-alloué : la somme des allocations actives dépasse le stock.
        //    C'est la détection qui aurait attrapé le défaut « 16 alloués sur un
        //    lot de 10 » avant qu'une course ne le révèle.
        $overAllocatedLots = DB::table('sales_picking_allocations as spa')
            ->join('stock_lots as sl', 'sl.id', '=', 'spa.stock_lot_id')
            ->where('spa.status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
            ->groupBy('spa.stock_lot_id', 'sl.lot_number', 'sl.quantity')
            ->havingRaw('SUM(spa.quantity) > sl.quantity + ?', [self::EPSILON])
            ->get(['spa.stock_lot_id', 'sl.lot_number', 'sl.quantity', DB::raw('SUM(spa.quantity) as alloue')]);
        $this->line('4. Lots alloués au-delà de leur stock : '.$overAllocatedLots->count());
        foreach ($overAllocatedLots as $row) {
            $this->warn("   lot {$row->lot_number} : stock {$row->quantity}, alloué {$row->alloue}");
        }
        $critical += $overAllocatedLots->count();

        // 5. Allocation sur un lot NON LIBÉRÉ (quarantaine, refusé, inconnu).
        $badQualityLots = DB::table('sales_picking_allocations as spa')
            ->join('stock_lots as sl', 'sl.id', '=', 'spa.stock_lot_id')
            ->where('spa.status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
            ->whereNotNull('sl.quality_status')
            ->where('sl.quality_status', '!=', 'libere')
            ->count();
        $this->line("5. Allocations sur un lot non libéré : {$badQualityLots}");
        $critical += $badQualityLots;

        // 5 bis. Allocation sur un lot NON VALORISÉ définitivement : le coût des
        //        ventes serait faux.
        $unvaluedLots = DB::table('sales_picking_allocations as spa')
            ->join('stock_lots as sl', 'sl.id', '=', 'spa.stock_lot_id')
            ->where('spa.status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
            ->where(fn ($q) => $q->whereNull('sl.valuation_status')
                ->orWhere('sl.valuation_status', '!=', 'valorisation_definitive'))
            ->count();
        $this->line("5 bis. Allocations sur un lot non valorisé définitivement : {$unvaluedLots}");
        $critical += $unvaluedLots;

        // 6. Allocation sur une bobine non libérée OU mère divisée.
        $badCoils = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('coils')) {
            $badCoils = DB::table('sales_picking_allocations as spa')
                ->join('coils as c', 'c.id', '=', 'spa.coil_id')
                ->where('spa.status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
                ->where(fn ($q) => $q->where('c.transformation_status', 'divisee')
                    ->orWhereNull('c.quality_status')
                    ->orWhereNotIn('c.quality_status', ['libere', 'libere_partiel']))
                ->count();
        }
        $this->line("6. Allocations sur bobine non libérée ou mère divisée : {$badCoils}");
        $critical += $badCoils;

        // 7. Bon VALIDÉ sans contrôle conforme actif : la séparation des tâches
        //    a été contournée, ou le contrôle est tombé après coup.
        $validatedWithoutControl = $scope(DB::table('sales_pickings as sp')
            ->where('sp.status', SalesPicking::STATUS_VALIDE)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sales_picking_controls as spc')
                    ->whereColumn('spc.sales_picking_id', 'sp.id')
                    ->whereNull('spc.invalidated_at')
                    ->where('spc.result', SalesPickingControl::RESULT_CONFORME);
            }))
            ->count();
        $this->line("7. Bons validés sans contrôle conforme : {$validatedWithoutControl}");
        $critical += $validatedWithoutControl;

        // 7 bis. Même acteur préparateur ET contrôleur, ou contrôleur ET validateur.
        $sameActor = $scope(DB::table('sales_pickings as sp')
            ->where(fn ($q) => $q->whereColumn('sp.started_by', 'sp.controlled_by')
                ->orWhereColumn('sp.controlled_by', 'sp.validated_by'))
            ->whereNotNull('sp.controlled_by'))
            ->count();
        $this->line("7 bis. Séparation des acteurs non respectée : {$sameActor}");
        $critical += $sameActor;

        // 8. Bon ANNULÉ conservant des allocations actives : les réservations
        //    n'ont pas été libérées, le stock reste immobilisé pour rien.
        $cancelledStillActive = DB::table('sales_picking_allocations as spa')
            ->join('sales_picking_items as spi', 'spi.id', '=', 'spa.sales_picking_item_id')
            ->join('sales_pickings as sp', 'sp.id', '=', 'spi.sales_picking_id')
            ->where('sp.status', SalesPicking::STATUS_ANNULE)
            ->where('spa.status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
            ->when($companyId, fn ($q) => $q->where('sp.company_id', $companyId))
            ->count();
        $this->line("8. Allocations actives sur un bon annulé : {$cancelledStillActive}");
        $critical += $cancelledStillActive;

        // 8 bis. Réservation encore « reserved » alors que le bon est annulé.
        $danglingReservations = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('stock_reservations')) {
            $danglingReservations = DB::table('sales_picking_allocations as spa')
                ->join('sales_picking_items as spi', 'spi.id', '=', 'spa.sales_picking_item_id')
                ->join('sales_pickings as sp', 'sp.id', '=', 'spi.sales_picking_id')
                ->join('stock_reservations as sr', 'sr.id', '=', 'spa.stock_reservation_id')
                ->where('sp.status', SalesPicking::STATUS_ANNULE)
                ->where('sr.status', 'reserved')
                ->when($companyId, fn ($q) => $q->where('sp.company_id', $companyId))
                ->count();
        }
        $this->line("8 bis. Réservations non libérées après annulation : {$danglingReservations}");
        $critical += $danglingReservations;

        // 9. Allocation sans réservation rattachée — informatif : la réservation
        //    est facultative tant que le flux amont ne la pose pas systématiquement.
        //    Ne JAMAIS compter en critique une absence dont la règle n'est pas tranchée.
        $withoutReservation = DB::table('sales_picking_allocations as spa')
            ->join('sales_picking_items as spi', 'spi.id', '=', 'spa.sales_picking_item_id')
            ->join('sales_pickings as sp', 'sp.id', '=', 'spi.sales_picking_id')
            ->where('spa.status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
            ->whereNull('spa.stock_reservation_id')
            ->when($companyId, fn ($q) => $q->where('sp.company_id', $companyId))
            ->count();
        $this->line("9. Allocations sans réservation (informatif) : {$withoutReservation}");
        $informational += $withoutReservation;

        // 10. Agrégats incohérents : l'invariant central du modèle de quantités.
        //     qty_validated ≤ qty_controlled ≤ qty_picked ≤ qty_allocated ≤ reliquat
        $badAggregates = DB::table('sales_picking_items as spi')
            ->join('sales_pickings as sp', 'sp.id', '=', 'spi.sales_picking_id')
            ->where('sp.status', '!=', SalesPicking::STATUS_ANNULE)
            ->when($companyId, fn ($q) => $q->where('sp.company_id', $companyId))
            ->where(fn ($q) => $q
                ->whereRaw('spi.qty_validated > spi.qty_controlled + ?', [self::EPSILON])
                ->orWhereRaw('spi.qty_controlled > spi.qty_picked + ?', [self::EPSILON])
                ->orWhereRaw('spi.qty_picked > spi.qty_allocated + ?', [self::EPSILON])
                ->orWhereRaw('spi.qty_allocated > spi.qty_remaining_snapshot + ?', [self::EPSILON]))
            ->count();
        $this->line("10. Lignes violant l'invariant de quantités : {$badAggregates}");
        $critical += $badAggregates;

        // 10 bis. qty_allocated désynchronisé de la somme réelle des allocations.
        $desyncAggregates = DB::table('sales_picking_items as spi')
            ->join('sales_pickings as sp', 'sp.id', '=', 'spi.sales_picking_id')
            ->leftJoin('sales_picking_allocations as spa', function ($j) {
                $j->on('spa.sales_picking_item_id', '=', 'spi.id')
                    ->where('spa.status', '!=', SalesPickingAllocation::STATUS_ANNULEE);
            })
            ->where('sp.status', '!=', SalesPicking::STATUS_ANNULE)
            ->when($companyId, fn ($q) => $q->where('sp.company_id', $companyId))
            ->groupBy('spi.id', 'spi.qty_allocated')
            // Epsilon INLINÉ, pas lié : Laravel lie les flottants en chaîne, et
            // SQLite compare alors un nombre à du texte — la condition serait
            // toujours fausse et la détection ne trouverait jamais rien.
            // Les autres détections y échappent parce que l'epsilon y est
            // ADDITIONNÉ à une colonne numérique, donc converti.
            // La valeur vient d'une constante de classe : aucune donnée externe.
            ->havingRaw('ABS(COALESCE(SUM(spa.quantity), 0) - spi.qty_allocated) > '.self::EPSILON)
            ->get(['spi.id', 'spi.qty_allocated', DB::raw('COALESCE(SUM(spa.quantity), 0) as somme_reelle')]);
        $this->line('10 bis. Agrégat qty_allocated désynchronisé : '.$desyncAggregates->count());
        $critical += $desyncAggregates->count();

        // 11. Cohérence aval : livré au-delà du validé en préparation.
        //     Tant que le bon de livraison ne consomme pas encore les quantités
        //     validées, la détection est déclarée NON APPLICABLE plutôt que
        //     rapportée à zéro — un zéro laisserait croire que c'est vérifié.
        $deliveryLinked = \Illuminate\Support\Facades\Schema::hasColumn('delivery_note_items', 'sales_picking_item_id');
        if ($deliveryLinked) {
            $overDelivered = DB::table('delivery_note_items as dni')
                ->join('sales_picking_items as spi', 'spi.id', '=', 'dni.sales_picking_item_id')
                ->groupBy('spi.id', 'spi.qty_validated')
                ->havingRaw('SUM(dni.quantity) > spi.qty_validated + ?', [self::EPSILON])
                ->get(['spi.id', 'spi.qty_validated', DB::raw('SUM(dni.quantity) as livre')]);
            $this->line('11. Lignes livrées au-delà du préparé validé : '.$overDelivered->count());
            $critical += $overDelivered->count();
        } else {
            $this->warn('11. Livré > préparé : NON APPLICABLE — le bon de livraison '
                .'n\'est pas encore rattaché aux lignes de préparation. La cohérence '
                .'aval ne peut donc pas être vérifiée, et n\'est pas rapportée comme saine.');
        }

        $this->newLine();
        $this->line("Anomalies informatives : {$informational}");

        if ($critical > 0) {
            $this->error("{$critical} anomalie(s) CRITIQUE(s) — voir ci-dessus. Aucune modification effectuée.");

            return self::FAILURE;
        }

        $this->info('AUDIT PRÉPARATIONS PROPRE — aucune anomalie critique détectée.');

        return self::SUCCESS;
    }
}
