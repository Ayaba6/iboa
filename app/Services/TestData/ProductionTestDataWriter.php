<?php

namespace App\Services\TestData;

use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Données de test MTS/MTO] Écriture du périmètre AUTORISÉ, et de lui seul.
 *
 * PÉRIMÈTRE EXCLU PAR DÉCISION MÉTIER — `finished-goods-lots`
 *
 *   Aucun lot de produit fini n'est créé, aucun stock initial n'est posé sur les
 *   articles MTS, aucun mouvement d'entrée PF n'est écrit. La traçabilité lot des
 *   fabriqués (MTS R12) n'existe pas encore : la fabriquer à la main ferait
 *   passer le test #22 en mentant sur l'état du logiciel. Les produits finis
 *   restent donc à 0, ce qui suffit à faire naître le besoin et à créer les OF.
 *
 * IDEMPOTENCE — chaque mouvement porte une `idempotency_key` dérivée du lot de
 * test et du rôle de la ligne. `StockService::recordMovement()` renvoie le
 * mouvement existant plutôt que d'en créer un second. Relancer ne double rien.
 */
class ProductionTestDataWriter
{
    /** Périmètres refusables par --exclude. */
    public const EXCLUSIONS = ['finished-goods-lots'];

    /** Trace de tout ce qui a été fait, pour le rapport final. */
    private array $journal = [
        'depot' => null, 'emplacements' => [], 'articles' => [],
        'sous_produits' => [], 'lots' => [], 'bobines' => [],
        'mouvements' => [], 'exclus' => [], 'preserves' => [],
    ];

    public function __construct(private StockService $stock) {}

    /**
     * @param  list<string>  $exclusions
     */
    public function executer(array $rapport, string $module, array $exclusions): array
    {
        $depot = $this->depotDeTest();

        foreach ($rapport as $code => $article) {
            if (! $article['existe']) {
                $this->journal['exclus'][] = [$code, 'article absent de la base'];

                continue;
            }

            $this->completerChampsFaibleRisque($code, $article);

            match ($article['role']) {
                'sous_produit' => $this->traiterSousProduit($code, $article),
                'matiere_fil' => $this->creerLotsFilMachine($code, $article, $depot),
                'matiere_bobine' => $this->creerBobines($code, $article, $depot),
                'produit_fini' => $this->traiterProduitFini($code, $article, $exclusions),
                default => null,
            };
        }

        return $this->journal;
    }

    /**
     * Dépôt de test dédié — les données de test ne partagent aucun contenant
     * avec le réel. C'est ce qui rend la purge possible sans arbitrage.
     */
    private function depotDeTest(): object
    {
        $code = ProductionTestDataSpec::TEST_WAREHOUSE_CODE;
        $existant = DB::table('warehouses')->where('code', $code)->first();

        if ($existant) {
            $this->journal['depot'] = ['code' => $code, 'id' => $existant->id, 'action' => 'existant'];

            return $existant;
        }

        $id = DB::table('warehouses')->insertGetId([
            'company_id' => DB::table('companies')->value('id'),
            'code' => $code,
            'name' => 'Dépôt Matières Premières — TEST',
            'long_name' => 'Dépôt local de test — lot '.ProductionTestDataSpec::BATCH,
            'type' => 'matiere_premiere',
            'can_stock' => true, 'can_production' => true, 'can_transfer' => true,
            'can_sale' => false, 'can_delivery' => false, 'can_purchase' => false,
            'is_active' => true, 'is_default' => false,
            'allow_negative_stock' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->journal['depot'] = ['code' => $code, 'id' => $id, 'action' => 'créé'];

        // Emplacements de test : un par nature de matière, pour que les bobines
        // et le fil machine ne se retrouvent pas au même endroit physique.
        foreach ([
            ['TEST-FIL-01', 'Emplacement de test — fil machine', 'TEST', 'F'],
            ['TEST-BOB-01', 'Emplacement de test — bobines prélaquées', 'TEST', 'B'],
        ] as [$c, $n, $zone, $allee]) {
            DB::table('warehouse_locations')->insert([
                'warehouse_id' => $id, 'code' => $c, 'name' => $n,
                'zone' => $zone, 'aisle' => $allee, 'rack' => '01', 'level' => 1,
                'description' => 'Lot de test '.ProductionTestDataSpec::BATCH,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->journal['emplacements'][] = $c;
        }

        return DB::table('warehouses')->find($id);
    }

    /**
     * Complète les seuls champs classés « faible risque ».
     *
     * Ces champs ne racontent aucune histoire passée : un seuil absent n'a jamais
     * été lu par un mouvement. Les structurants, eux, ne sont jamais touchés ici.
     */
    private function completerChampsFaibleRisque(string $code, array $article): void
    {
        $maj = [];
        foreach ($article['ecarts'] as $e) {
            if ($e['verdict'] !== 'completable') {
                continue;
            }
            if (! in_array($e['champ'], ProductionTestDataSpec::FAIBLE_RISQUE, true)) {
                continue;
            }
            $maj[$e['champ']] = is_bool($e['attendu']) ? (int) $e['attendu'] : $e['attendu'];
            $this->journal['articles'][] = [
                'code' => $code, 'champ' => $e['champ'],
                'avant' => $e['actuel'], 'apres' => $e['attendu'],
            ];
        }

        // Les structurants divergents sont consignés comme préservés : le rapport
        // doit montrer ce qu'on a choisi de NE PAS faire autant que le reste.
        foreach ($article['ecarts'] as $e) {
            if ($e['verdict'] === 'conflit' || $e['verdict'] === 'impossible') {
                $this->journal['preserves'][] = [
                    'code' => $code, 'champ' => $e['champ'],
                    'actuel' => $e['actuel'], 'attendu' => $e['attendu'],
                    'risque' => $e['risque'],
                ];
            }
        }

        if ($maj) {
            $maj['updated_at'] = now();
            DB::table('products')->where('id', $article['id'])->update($maj);
        }
    }

    /**
     * Sous-produits : la vente standard ne se ferme QUE si rien de commercial
     * n'en dépend. Un article déjà devisé ou facturé garde sa valeur — on ne
     * réécrit pas la nature d'un article après qu'il a servi.
     */
    private function traiterSousProduit(string $code, array $article): void
    {
        $deps = $article['dependances'] ?? [];
        $commerciales = 0;
        foreach (['lignes devis', 'lignes commande', 'lignes livraison', 'lignes facture'] as $k) {
            $commerciales += $deps[$k] ?? 0;
        }
        $mouvements = $deps['mouvements'] ?? 0;

        $ligne = [
            'code' => $code,
            'devis' => $deps['lignes devis'] ?? 0,
            'commandes' => $deps['lignes commande'] ?? 0,
            'bl' => $deps['lignes livraison'] ?? 0,
            'factures' => $deps['lignes facture'] ?? 0,
            'mouvements' => $mouvements,
        ];

        $actuel = DB::table('products')->where('id', $article['id'])->value('is_sellable');

        if ($commerciales > 0) {
            $ligne['decision'] = 'PRÉSERVÉ — transactions commerciales réelles';
            $ligne['avant'] = $actuel;
            $ligne['apres'] = $actuel;
        } elseif ((int) $actuel === 0) {
            // « Conforme » et « corrigé lors d'une exécution antérieure » se
            // ressemblent en base mais ne disent pas la même chose. Le rapport ne
            // doit pas laisser croire qu'aucune main n'est passée par là.
            $ligne['decision'] = 'conforme à l’exécution (état déjà atteint)';
            $ligne['avant'] = 0;
            $ligne['apres'] = 0;
        } else {
            DB::table('products')->where('id', $article['id'])
                ->update(['is_sellable' => 0, 'updated_at' => now()]);
            $ligne['decision'] = 'CORRIGÉ — aucune dépendance commerciale';
            $ligne['avant'] = 1;
            $ligne['apres'] = 0;
        }

        $this->journal['sous_produits'][] = $ligne;
    }

    /**
     * Fil machine : deux lots par article, pour que FIFO, consommation partielle
     * et traçabilité aient quelque chose à mordre. Un stock d'un seul bloc ne
     * teste rien de tout cela.
     */
    private function creerLotsFilMachine(string $code, array $article, object $depot): void
    {
        $cible = $article['stock_cible'] ?? null;
        if (! $cible || empty($cible['lots'])) {
            return;
        }

        $emplacement = $this->emplacement($depot->id, 'TEST-FIL-01');
        $cout = $this->coutEntree($article['id']);

        foreach ($cible['lots'] as $lot) {
            $numero = "LOT-TEST-{$code}-{$lot['suffixe']}";
            $cle = ProductionTestDataSpec::BATCH."-{$code}-LOT-{$lot['suffixe']}";

            $mouvement = $this->stock->recordMovement([
                'product_id' => $article['id'],
                'warehouse_id' => $depot->id,
                'location_id' => $emplacement,
                'type' => 'entree',
                'quantity' => $lot['quantite'],
                'uom' => 'KG',
                'stock_uom' => 'KG',
                'unit_cost' => $cout,
                'lot_number' => $numero,
                'idempotency_key' => $cle,
                'occurred_at' => now(),
                'notes' => 'Stock initial de test — '.ProductionTestDataSpec::BATCH,
            ]);

            // `upsertLot()` ne reporte pas l'unité de tenue de stock sur le lot :
            // un lot sans unité laisse la quantité ininterprétable, et c'est
            // précisément l'anomalie relevée sur les lots réels de MPTBC0001.
            DB::table('stock_lots')
                ->where('product_id', $article['id'])->where('lot_number', $numero)
                ->update(['stock_uom' => 'KG', 'supplier_lot_number' => ProductionTestDataSpec::BATCH,
                    'quality_status' => 'libere', 'updated_at' => now()]);

            $this->journal['lots'][] = [
                'article' => $code, 'lot' => $numero,
                'quantite' => $lot['quantite'], 'unite' => 'KG',
                'depot' => $depot->code, 'mouvement' => $mouvement->id,
                'nouveau' => $mouvement->wasRecentlyCreated,
            ];
            $this->journal['mouvements'][] = $mouvement->id;
        }
    }

    /**
     * Bobines prélaquées : deux bobines physiques par article.
     *
     * L'objet de traçabilité est ici la BOBINE, portée par un lot — pas deux
     * stocks distincts. C'est la bobine qui porte couleur, épaisseur, largeur et
     * coefficient, et c'est elle que `CoilCompatibilityService` confronte à l'OF.
     * L'article n'entre jamais dans cette comparaison : aucun de ses champs
     * structurants n'a donc besoin d'être réécrit.
     */
    private function creerBobines(string $code, array $article, object $depot): void
    {
        $cible = $article['stock_cible'] ?? null;
        if (! $cible || empty($cible['bobines'])) {
            return;
        }

        $emplacement = $this->emplacement($depot->id, 'TEST-BOB-01');
        $coef = (float) $cible['coef'];
        $cout = $this->coutEntree($article['id']);
        $societe = DB::table('companies')->value('id');

        foreach ($cible['bobines'] as $b) {
            $reference = "BOB-TEST-{$code}-{$b['suffixe']}";
            $numeroLot = "LOT-TEST-{$code}-{$b['suffixe']}";
            $cle = ProductionTestDataSpec::BATCH."-{$code}-BOB-{$b['suffixe']}";
            $metres = (float) $b['metres'];
            $poids = round($metres * $coef, 3);

            // L'unité de tenue de stock de l'article est le mètre linéaire :
            // c'est en ML que bougent product_stocks et le lot. Le poids est
            // une CONSÉQUENCE du coefficient, jamais une seconde quantité.
            $mouvement = $this->stock->recordMovement([
                'product_id' => $article['id'],
                'warehouse_id' => $depot->id,
                'location_id' => $emplacement,
                'type' => 'entree',
                'quantity' => $metres,
                'uom' => 'ML',
                'stock_uom' => 'ML',
                'conversion_factor' => $coef,
                'unit_cost' => $cout,
                'lot_number' => $numeroLot,
                'idempotency_key' => $cle,
                'occurred_at' => now(),
                'notes' => sprintf('Stock initial de test — %s — %s ML = %s kg (coef %s)',
                    ProductionTestDataSpec::BATCH, $metres, $poids, $coef),
            ]);
            $this->journal['mouvements'][] = $mouvement->id;

            $lotId = DB::table('stock_lots')
                ->where('product_id', $article['id'])->where('lot_number', $numeroLot)->value('id');

            if ($lotId) {
                // Le coefficient vit aussi sur le lot : sans lui, la conversion
                // ML → kg serait à refaire de mémoire à chaque consommation.
                DB::table('stock_lots')->where('id', $lotId)->update([
                    'stock_uom' => 'ML',
                    'kg_per_linear_meter' => $coef,
                    'supplier_lot_number' => ProductionTestDataSpec::BATCH,
                    'quality_status' => 'libere',
                    'updated_at' => now(),
                ]);
            }

            $existante = DB::table('coils')->where('reference', $reference)->first();
            if ($existante) {
                $this->journal['bobines'][] = [
                    'article' => $code, 'reference' => $reference,
                    'metres' => $metres, 'poids' => $poids, 'nouveau' => false,
                ];

                continue;
            }

            DB::table('coils')->insert([
                'company_id' => $societe,
                'product_id' => $article['id'],
                'warehouse_id' => $depot->id,
                'reference' => $reference,
                'lot_number' => $numeroLot,
                'stock_lot_id' => $lotId,
                'supplier_reference' => ProductionTestDataSpec::BATCH,
                // Caractéristiques de compatibilité : c'est sur elles que porte
                // le refus d'une bobine orange face à une commande beige.
                'color' => $cible['couleur'],
                'thickness' => $cible['epaisseur'],
                'width' => $cible['largeur'],
                'coating' => 'Prélaqué',
                'initial_weight' => $poids,
                'remaining_weight' => $poids,
                'estimated_length' => $metres,
                'kg_per_linear_meter' => $coef,
                'cost_per_kg' => $poids > 0 ? round($cout * $metres / $poids, 4) : $cout,
                'is_stock_managed' => true,
                'lot_tracking' => true,
                'allow_negative_stock' => false,
                'received_at' => now(),
                'status' => 'disponible',
                'quality_status' => 'libere',
                'notes' => 'Bobine de test — '.ProductionTestDataSpec::BATCH
                    .' — largeur 1200 mm : HYPOTHÈSE de test, absente de l’article et des nomenclatures.',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $this->journal['bobines'][] = [
                'article' => $code, 'reference' => $reference,
                'metres' => $metres, 'poids' => $poids, 'nouveau' => true,
            ];
        }
    }

    /**
     * Produits finis MTS : seuils uniquement, stock laissé à zéro.
     *
     * Le zéro n'est pas un oubli : c'est lui qui, face au seuil, fait naître le
     * besoin que la planification doit détecter.
     */
    private function traiterProduitFini(string $code, array $article, array $exclusions): void
    {
        if (in_array('finished-goods-lots', $exclusions, true)) {
            $this->journal['exclus'][] = [
                $code,
                'lots et stock initial PF exclus (--exclude=finished-goods-lots) — dépend de MTS R12',
            ];

            return;
        }

        // Sans exclusion explicite, on refuse quand même : créer un lot de
        // produit fini sans sortie de production réelle fabriquerait une
        // traçabilité qui n'existe pas.
        $this->journal['exclus'][] = [
            $code,
            'lot PF non créé : aucune sortie de production réelle ne l’adosse (MTS R12)',
        ];
    }

    /**
     * Coût unitaire d'entrée du stock de test.
     *
     * Entrer au PMP courant laisse la moyenne pondérée INCHANGÉE : c'est la
     * seule façon de poser du stock de test sur un article déjà valorisé sans
     * déplacer sa valorisation réelle.
     *
     * MAIS ce raisonnement suppose que le PMP repose sur quelque chose. Trois
     * articles portaient un PMP sans le moindre mouvement pour l'adosser —
     * jusqu'à 250 000 F/kg de fil machine, économiquement absurde. Hériter d'une
     * valeur orpheline puis la multiplier par trois tonnes a produit 750 millions
     * de valorisation fictive. On n'hérite donc du PMP que s'il est ADOSSÉ à un
     * mouvement réel, hors lot de test.
     */
    private function coutEntree(int $productId): float
    {
        $adosse = DB::table('stock_movements')
            ->where('product_id', $productId)
            ->where(function ($q) {
                $q->whereNull('idempotency_key')
                    ->orWhere('idempotency_key', 'not like', ProductionTestDataSpec::BATCH.'%');
            })
            ->exists();

        if (! $adosse) {
            return ProductionTestDataSpec::COUT_TEST_DEFAUT;
        }

        $pmp = (float) DB::table('products')->where('id', $productId)->value('weighted_avg_cost');

        return $pmp > 0 ? $pmp : ProductionTestDataSpec::COUT_TEST_DEFAUT;
    }

    private function emplacement(int $depotId, string $code): ?int
    {
        return DB::table('warehouse_locations')
            ->where('warehouse_id', $depotId)->where('code', $code)->value('id');
    }

    /** Contrôles finaux §11 — mesurés, jamais supposés. */
    public function controles(): array
    {
        $depot = DB::table('warehouses')->where('code', ProductionTestDataSpec::TEST_WAREHOUSE_CODE)->first();
        if (! $depot) {
            return [];
        }

        $fil = DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('ps.warehouse_id', $depot->id)
            ->where('p.code_article', 'like', 'MPFAB%')
            ->sum('ps.quantity');

        $bob = DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('ps.warehouse_id', $depot->id)
            ->where('p.code_article', 'like', 'MPTBC%')
            ->sum('ps.quantity');

        $poidsBobines = Schema::hasTable('coils')
            ? (float) DB::table('coils')->where('warehouse_id', $depot->id)->sum('remaining_weight')
            : 0.0;

        $pf = DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('p.code_article', 'like', 'PFFAB%')->sum('ps.quantity');

        return [
            'fil_machine_kg' => (float) $fil,
            'bobines_ml' => (float) $bob,
            'bobines_kg' => $poidsBobines,
            'produits_finis' => (float) $pf,
            'stock_negatif' => DB::table('product_stocks')->where('quantity', '<', 0)->count(),
            'mouvements_lot_test' => DB::table('stock_movements')
                ->where('idempotency_key', 'like', ProductionTestDataSpec::BATCH.'%')->count(),
        ];
    }
}
