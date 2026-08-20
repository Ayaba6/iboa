<?php

namespace App\Services\TestData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Données de test MTS/MTO] Auditeur — LECTURE SEULE, sans aucune exception.
 *
 * Compare l'existant à {@see ProductionTestDataSpec} et qualifie chaque écart.
 * Ne corrige rien : décider qu'un écart est une erreur plutôt qu'une décision
 * métier n'est pas de son ressort.
 *
 * TROIS VERDICTS PAR CHAMP
 *   conforme   — la base dit déjà ce que la campagne attend
 *   completable— champ à faible risque, vide ou divergent : proposable
 *   conflit    — champ structurant divergent : bloque, jamais écrasé en silence
 *
 * Le risque monte d'un cran dès que l'article porte des transactions : modifier
 * l'unité de stock d'un article jamais mouvementé ne coûte rien ; la modifier
 * sur un article déjà consommé réécrit le sens de son historique.
 */
class ProductionTestDataAuditor
{
    /** Tables scannées pour établir si un article « vit » déjà. */
    private const DEPENDANCES = [
        'mouvements' => ['stock_movements', 'product_id'],
        'lots' => ['stock_lots', 'product_id'],
        'bobines' => ['coils', 'product_id'],
        'reservations' => ['stock_reservations', 'product_id'],
        'lignes devis' => ['quote_items', 'product_id'],
        'lignes commande' => ['order_items', 'product_id'],
        'lignes livraison' => ['delivery_note_items', 'product_id'],
        'lignes facture' => ['invoice_items', 'product_id'],
        'lignes achat' => ['purchase_order_items', 'product_id'],
        'OF' => ['production_orders', 'product_id'],
        'gamme(s)' => ['routings', 'product_id'],
        // Le composant d'une nomenclature est aussi une dépendance : toucher son
        // unité casse le calcul de besoin de tous les produits qui l'emploient.
        'composant de nomenclature' => ['bom_lines', 'product_id'],
    ];

    /**
     * Audite les articles de la campagne.
     *
     * @param  string  $module  mts | mto | all
     * @return array<string, array<string, mixed>>
     */
    public function audit(string $module = 'all'): array
    {
        $spec = ProductionTestDataSpec::articles();
        $unites = DB::table('units')->pluck('id', 'name')->all();
        $depots = DB::table('warehouses')->pluck('id', 'code')->all();

        $rapport = [];

        foreach ($spec as $code => $def) {
            if ($module !== 'all' && $def['module'] !== 'commun' && $def['module'] !== $module) {
                continue;
            }

            $p = DB::table('products')->where('code_article', $code)->first();

            if (! $p) {
                $rapport[$code] = [
                    'existe' => false, 'module' => $def['module'], 'role' => $def['role'],
                    'ecarts' => [], 'dependances' => [], 'statut' => 'absent',
                    'stock_actuel' => null, 'stock_cible' => $def['stock'] ?? null,
                ];

                continue;
            }

            $dependances = $this->dependances((int) $p->id);
            $transactionne = array_sum($dependances) > 0;
            $ecarts = $this->comparer($p, $def['attendu'] ?? [], $unites, $transactionne);

            $rapport[$code] = [
                'existe' => true,
                'id' => (int) $p->id,
                'nom' => $p->name,
                'module' => $def['module'],
                'role' => $def['role'],
                'depot_cible' => $def['depot'] ?? null,
                'depot_existe' => isset($depots[$def['depot'] ?? '']),
                'dependances' => array_filter($dependances),
                'transactionne' => $transactionne,
                'ecarts' => $ecarts,
                'statut' => $this->statutArticle($ecarts),
                'stock_actuel' => $this->stockActuel((int) $p->id),
                'stock_cible' => $def['stock'] ?? null,
                'nomenclature' => $this->nomenclature((int) $p->id, $def['nomenclature'] ?? null),
            ];
        }

        return $rapport;
    }

    /** Compte, table par table, ce qui dépend de l'article. */
    public function dependances(int $productId): array
    {
        $out = [];
        foreach (self::DEPENDANCES as $libelle => [$table, $colonne]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $colonne)) {
                continue;
            }
            $q = DB::table($table)->where($colonne, $productId);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $out[$libelle] = $q->count();
        }

        return $out;
    }

    /**
     * Confronte chaque champ attendu à la valeur en base.
     *
     * @return list<array<string,mixed>>
     */
    private function comparer(object $p, array $attendu, array $unites, bool $transactionne): array
    {
        $ecarts = [];

        foreach ($attendu as $cle => $valeurAttendue) {
            [$champ, $actuel] = $this->resoudre($p, $cle, $unites);

            // La nuance n'a aucune colonne sur `products` : l'écart n'est pas
            // corrigeable, il est structurel. On le déclare sans le maquiller.
            if ($champ === null) {
                $ecarts[] = [
                    'champ' => $cle, 'actuel' => null, 'attendu' => $valeurAttendue,
                    'verdict' => 'impossible', 'risque' => 'structurel',
                    'note' => 'aucune colonne correspondante sur products',
                ];

                continue;
            }

            if ($this->equivalent($actuel, $valeurAttendue)) {
                continue;
            }

            $structurant = in_array($champ, ProductionTestDataSpec::STRUCTURANTS, true);
            $vide = $actuel === null || $actuel === '' || (is_numeric($actuel) && (float) $actuel == 0.0);

            $ecarts[] = [
                'champ' => $champ,
                'actuel' => $actuel,
                'attendu' => $valeurAttendue,
                'verdict' => $structurant ? 'conflit' : 'completable',
                // Le risque vient de la DÉPENDANCE, pas du champ. Modifier l'unité
                // d'un article jamais mouvementé ne réécrit aucun historique ;
                // la modifier sur un article déjà consommé, si. Un champ vide sur
                // un article transactionné reste donc élevé : ce vide a laissé
                // des traces dans tout ce qui l'a lu.
                'risque' => $structurant ? ($transactionne ? 'eleve' : 'moyen') : 'faible',
                'note' => $structurant && ! $transactionne
                    ? 'article sans transaction — correction possible sous --conflict=update'
                    : ($structurant ? 'article transactionné — préserver' : null),
            ];
        }

        return $ecarts;
    }

    /**
     * Traduit une clé de spécification en colonne réelle et lit sa valeur.
     *
     * @return array{0: ?string, 1: mixed}
     */
    private function resoudre(object $p, string $cle, array $unites): array
    {
        // « unite » désigne l'unité de tenue de stock, portée par unit_id.
        if ($cle === 'unite') {
            $id = $unites[$p->unit_id] ?? null;
            $nom = array_search($p->unit_id, $unites, true);

            return ['unit_id', $nom !== false ? $nom : null];
        }

        // Nuance : le schéma ne la porte que sur les bobines, pas sur l'article.
        if ($cle === 'nuance' && ! Schema::hasColumn('products', 'nuance')) {
            return [null, null];
        }

        if (! Schema::hasColumn('products', $cle)) {
            return [null, null];
        }

        return [$cle, $p->{$cle} ?? null];
    }

    /** Égalité tolérante : « 0.27 » et 0.270 disent la même chose. */
    private function equivalent(mixed $a, mixed $b): bool
    {
        if (is_bool($b)) {
            return (bool) $a === $b;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 1e-6;
        }

        return (string) $a === (string) $b;
    }

    private function statutArticle(array $ecarts): string
    {
        foreach ($ecarts as $e) {
            if ($e['verdict'] === 'conflit' && $e['risque'] === 'eleve') {
                return 'conflit_bloquant';
            }
        }
        foreach ($ecarts as $e) {
            if ($e['verdict'] === 'conflit') {
                return 'conflit';
            }
        }

        return $ecarts ? 'completable' : 'conforme';
    }

    /** Photographie du stock réel, dépôt par dépôt. */
    public function stockActuel(int $productId): array
    {
        $lignes = DB::table('product_stocks as ps')
            ->leftJoin('warehouses as w', 'w.id', '=', 'ps.warehouse_id')
            ->where('ps.product_id', $productId)
            ->select('w.code as depot', 'ps.quantity', 'ps.reserved_quantity')
            ->get()->map(fn ($r) => [
                'depot' => $r->depot, 'quantite' => (float) $r->quantity,
                'reservee' => (float) ($r->reserved_quantity ?? 0),
            ])->all();

        $lots = DB::table('stock_lots')->where('product_id', $productId)
            ->select('lot_number', 'quantity', 'stock_uom', 'source_type')->get()
            ->map(fn ($l) => [
                'lot' => $l->lot_number, 'quantite' => (float) $l->quantity,
                'uom' => $l->stock_uom, 'source' => $l->source_type,
            ])->all();

        $bobines = Schema::hasTable('coils')
            ? DB::table('coils')->where('product_id', $productId)
                ->select('reference', 'remaining_weight', 'estimated_length', 'color', 'thickness', 'status')->get()
                ->map(fn ($c) => [
                    'reference' => $c->reference, 'poids' => (float) $c->remaining_weight,
                    'metres' => (float) ($c->estimated_length ?? 0), 'couleur' => $c->color,
                    'epaisseur' => $c->thickness, 'statut' => $c->status,
                ])->all()
            : [];

        return [
            'total' => array_sum(array_column($lignes, 'quantite')),
            'lignes' => $lignes, 'lots' => $lots, 'bobines' => $bobines,
        ];
    }

    /**
     * Nomenclature active du produit, confrontée au mapping proposé.
     *
     * L'en-tête vit dans `bills_of_materials` ; `bom_lines.product_id` désigne
     * le COMPOSANT, jamais le produit fabriqué. Chercher le produit dans les
     * lignes ne trouve donc rien et laisse croire à une absence.
     */
    public function nomenclature(int $productId, ?array $propose): ?array
    {
        if (! Schema::hasTable('bills_of_materials')) {
            return null;
        }

        $bom = DB::table('bills_of_materials')
            ->where('product_id', $productId)->where('is_active', true)
            ->whereNull('deleted_at')->first();

        if (! $bom) {
            return ['existe' => false, 'propose' => $propose];
        }

        $lignes = DB::table('bom_lines as bl')
            ->leftJoin('products as p', 'p.id', '=', 'bl.product_id')
            ->where('bl.bill_of_material_id', $bom->id)
            ->select('p.code_article', 'bl.quantity_per_meter', 'bl.waste_rate')
            ->get()->map(fn ($l) => [
                'composant' => $l->code_article,
                'quantite' => (float) $l->quantity_per_meter,
                'perte' => (float) ($l->waste_rate ?? 0),
            ])->all();

        $ecart = null;
        if ($propose && $lignes) {
            $premiere = $lignes[0];
            if ($premiere['composant'] !== $propose['composant']) {
                $ecart = sprintf('composant %s en base, %s proposé', $premiere['composant'], $propose['composant']);
            } elseif (abs($premiere['quantite'] - (float) $propose['quantite']) > 1e-4) {
                $ecart = sprintf('consommation %s en base, %s proposée', $premiere['quantite'], $propose['quantite']);
            } elseif (abs($premiere['perte'] - (float) $propose['taux_perte']) > 1e-4) {
                $ecart = sprintf('taux de perte %s %% en base, %s %% proposé', $premiere['perte'], $propose['taux_perte']);
            }
        }

        return [
            'existe' => true, 'code' => $bom->code, 'statut' => $bom->statut,
            'lignes' => $lignes, 'ecart_vs_propose' => $ecart,
        ];
    }
}
