<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [MTO §1] Recense les ordres de fabrication portant un article MTO qui ne sont
 * rattachés à aucune commande client.
 *
 * Depuis `MtoOrderRequirementGuard`, ce cas ne peut plus se créer sans dérogation
 * motivée et journalisée. Restent les OF antérieurs à la règle : ils ne sont ni
 * modifiés ni rattachés d'office. Inventer la commande d'origine d'un OF déjà
 * produit reviendrait à fabriquer de l'historique — c'est précisément ce qu'un
 * audit doit interdire, pas ce qu'il doit faire.
 *
 * Lecture seule, strictement : aucune écriture, aucune option `--fix`. Le
 * rattachement d'un OF existant à une commande est une décision métier.
 *
 * Sort en code 1 si au moins un OF est signalé, pour être exploitable en
 * intégration continue.
 */
class AuditMtoOrders extends Command
{
    protected $signature = 'a3:audit-mto-orders {--company= : Restreindre à une société}';

    protected $description = 'Liste les OF MTO sans commande client rattachée (lecture seule).';

    public function handle(): int
    {
        $this->info('[MTO §1] OF portant un article MTO sans commande client');
        $this->newLine();

        $rows = DB::table('production_orders as po')
            ->join('products as p', 'p.id', '=', 'po.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'po.created_by')
            ->whereNull('po.deleted_at')
            ->whereNull('po.order_id')
            ->where('p.production_mode', 'mto')
            ->when($this->option('company'), fn ($q, $c) => $q->where('po.company_id', $c))
            ->orderBy('po.number')
            ->get([
                'po.id', 'po.number', 'po.status', 'po.created_at', 'po.quantity_requested',
                'po.quantity_produced', 'p.name as produit', 'p.production_mode',
                'u.name as createur',
            ]);

        if ($rows->isEmpty()) {
            $this->info('  Aucun OF MTO sans commande. Règle respectée sur l’ensemble du parc.');

            return self::SUCCESS;
        }

        $table = [];
        foreach ($rows as $r) {
            $consommations = DB::table('production_consumptions')
                ->where('production_order_id', $r->id)->whereNull('reversed_at')->count();

            $sorties = DB::table('production_outputs')
                ->where('production_order_id', $r->id)->sum('quantity');

            // Livraisons IMPUTABLES à cet OF : la seule chaîne fiable passe par une
            // réservation nominative portant l'OF. Un produit fini parti par le
            // stock général n'est pas rattachable à un OF précis — le dire, plutôt
            // que d'afficher un zéro qui se lirait comme « rien livré ».
            $livraisons = DB::table('stock_reservations')
                ->where('production_order_id', $r->id)
                ->whereNotNull('order_id')
                ->count();

            $table[] = [
                $r->number,
                $r->status,
                mb_strimwidth((string) $r->produit, 0, 34, '…'),
                substr((string) $r->created_at, 0, 10),
                $r->createur ?? '—',
                rtrim(rtrim(number_format((float) $r->quantity_produced, 2, ',', ' '), '0'), ','),
                $consommations,
                rtrim(rtrim(number_format((float) $sorties, 2, ',', ' '), '0'), ','),
                $livraisons,
                $this->regularisation($r->status, $consommations, (float) $sorties, $livraisons),
            ];
        }

        $this->table(
            ['OF', 'Statut', 'Produit', 'Créé le', 'Créateur', 'Qté prod.', 'Conso.', 'Sorties', 'Livr.', 'Régularisation'],
            $table
        );

        $this->newLine();
        $this->warn(sprintf('  %d OF MTO sans commande client.', $rows->count()));
        $this->line('  Aucun n’a été modifié. Le rattachement à une commande est une décision métier.');

        return self::FAILURE;
    }

    /**
     * Estime la difficulté d'une régularisation. Volontairement conservateur :
     * dès qu'un mouvement de stock ou une livraison existe, le rattachement
     * cesse d'être une simple correction de saisie.
     */
    private function regularisation(string $status, int $consommations, float $sorties, int $livraisons): string
    {
        if ($livraisons > 0) {
            return 'non — déjà livré';
        }
        if ($sorties > 0 || $consommations > 0) {
            return 'à instruire — stock mouvementé';
        }
        if (in_array($status, ['annule', 'termine'], true)) {
            return 'sans objet — OF clos';
        }

        return 'possible — aucun mouvement';
    }
}
