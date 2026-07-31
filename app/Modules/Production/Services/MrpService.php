<?php

namespace App\Modules\Production\Services;

use App\Models\Product;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\PurchaseRequestService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] MRP simplifié — réapprovisionnement bobines (matières premières).
 *
 * Détecte les ruptures de stock matière : pour chaque produit-matière référencé
 * par des bobines, compare le poids disponible (somme des bobines non épuisées)
 * au seuil minimum du produit (stock_min, en kg). Génère une demande d'achat
 * pour les déficits, réutilisant le module Achats (PurchaseRequestService).
 */
class MrpService
{
    public function __construct(
        private PurchaseRequestService $purchaseRequests,
        private NetRequirementService $requirements,
        private ProductionService $production,
    ) {}

    /**
     * [MRP] Propositions d'ORDRE DE FABRICATION.
     *
     * Le MRP ne savait proposer que des achats : il ne regardait que les bobines
     * et sortait une demande d'achat. Les articles FABRIQUES pour le stock
     * n'etaient couverts par aucune proposition, alors que `origin = 'mrp'`
     * existait deja dans l'enumeration des OF sans qu'aucun code puisse la
     * produire.
     *
     * Les besoins viennent de NetRequirementService : meme regle que l'ecran de
     * planification MTS, pas une seconde implementation.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function productionProposals(): Collection
    {
        return $this->requirements->proposals();
    }

    /**
     * Genere les OF correspondant aux propositions retenues.
     *
     * Volontairement TOLERANT aux refus : un article dont l'OF est rejete (regle
     * metier, garde MTO, nomenclature devenue inactive) n'interrompt pas la
     * generation des autres. Chaque refus est rendu avec son motif plutot que
     * silencieusement avale.
     *
     * Naturellement idempotent : le besoin net deduit les OF deja planifies, donc
     * un second passage immediat ne propose plus rien.
     *
     * @param  array<int>  $productIds  ids retenus ; vide = toutes les propositions
     * @return array{created: list<ProductionOrder>, skipped: list<array{produit:string,raison:string}>}
     */
    public function generateProductionOrders(array $productIds = []): array
    {
        $proposals = $this->productionProposals();
        if ($productIds) {
            $proposals = $proposals->filter(fn ($r) => in_array($r['p']->id, $productIds, true))->values();
        }

        $created = [];
        $skipped = [];

        foreach ($proposals as $row) {
            try {
                $created[] = $this->production->create([
                    'product_id'          => $row['p']->id,
                    'bill_of_material_id' => $row['bom_id'],
                    'quantity_requested'  => $row['besoin'],
                    'origin'              => 'mrp',
                ], [], 'mrp');
            } catch (ValidationException $e) {
                $skipped[] = [
                    'produit' => $row['p']->name,
                    'raison'  => implode(' ', collect($e->errors())->flatten()->all()),
                ];
            }
        }

        return compact('created', 'skipped');
    }

    /**
     * Analyse des besoins matière (bobines sous seuil).
     *
     * @return Collection<int, array{product_id:int,product:string,available:float,min:float,deficit:float,supplier_id:?int,avg_cost_per_kg:float,estimated:int}>
     */
    public function analyze(): Collection
    {
        // Poids disponible par produit-matière (bobines non épuisées)
        $rows = Coil::query()
            ->whereNotNull('product_id')
            ->where('status', '!=', 'epuisee')
            ->selectRaw('product_id,
                         SUM(remaining_weight) as available,
                         AVG(cost_per_kg)      as avg_cost,
                         MAX(supplier_id)      as supplier_id')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // Produits-matière concernés (au moins une bobine, jamais ou non)
        $productIds = Coil::whereNotNull('product_id')->distinct()->pluck('product_id');
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $shortfalls = collect();
        foreach ($products as $id => $product) {
            $min = (float) ($product->stock_min ?? 0);
            if ($min <= 0) {
                continue; // pas de seuil défini → pas de suivi MRP
            }

            $available = (float) ($rows[$id]->available ?? 0);
            if ($available >= $min) {
                continue;
            }

            $deficit = round($min - $available, 2);
            $avgCost = (float) ($rows[$id]->avg_cost ?? 0);

            $shortfalls->push([
                'product_id'      => $id,
                'product'         => $product->name,
                'available'       => round($available, 2),
                'min'             => $min,
                'deficit'         => $deficit,
                'supplier_id'     => $rows[$id]->supplier_id ?? null,
                'avg_cost_per_kg' => round($avgCost, 2),
                'estimated'       => (int) round($deficit * $avgCost),
            ]);
        }

        return $shortfalls->sortByDesc('deficit')->values();
    }

    /**
     * Génère une demande d'achat pour les produits sélectionnés (ou tous les déficits).
     *
     * @param array<int> $productIds  ids à inclure ; vide = tous les déficits
     */
    public function generatePurchaseRequest(array $productIds = []): ?\App\Models\PurchaseRequest
    {
        $shortfalls = $this->analyze();
        if ($productIds) {
            $shortfalls = $shortfalls->whereIn('product_id', $productIds)->values();
        }
        if ($shortfalls->isEmpty()) {
            return null;
        }

        $items = $shortfalls->map(fn ($s) => [
            'product_id'      => $s['product_id'],
            'description'     => 'Réappro bobine — ' . $s['product'],
            'quantity'        => $s['deficit'],
            'estimated_price' => $s['avg_cost_per_kg'],
        ])->all();

        return $this->purchaseRequests->create([
            'justification' => 'Réapprovisionnement automatique bobines (MRP production)',
            'needed_at'     => now()->addWeek()->toDateString(),
            'items'         => $items,
        ]);
    }
}
