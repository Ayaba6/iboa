<?php

namespace App\Modules\Production\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * [MRP] Propositions de TRANSFERT entre dépôts.
 *
 * BASE RETENUE : la SUR-RÉSERVATION. Un dépôt dont les quantités réservées
 * dépassent le stock physique a promis une marchandise qu'il ne détient pas ;
 * un autre dépôt disposant d'un excédent peut la couvrir. Le manque est alors
 * réel et daté, pas théorique.
 *
 * POURQUOI PAS LES SEUILS PAR DÉPÔT — le chemin qu'on attendrait n'existe pas :
 * `product_sites` porte bien `stock_min`, `stock_max` et `stock_securite` par
 * site, mais `warehouses` ne porte AUCUNE colonne `site_id`. Le chaînage
 * article → site → dépôt est rompu au dernier maillon, et la table est vide.
 * Construire là-dessus reviendrait à inventer un rattachement.
 *
 * GARDE DE SÉCURITÉ — les dépôts de quarantaine, de rebuts et de chutes ne
 * peuvent jamais être SOURCE d'une proposition. Sortir de la marchandise
 * bloquée par la qualité vers un dépôt de vente contournerait exactement la
 * barrière posée sur la livraison. Le drapeau `can_transfer` ne suffit pas à
 * l'assurer : il vaut 1 sur les dix dépôts, quarantaine et rebuts compris — il
 * ne distingue donc rien aujourd'hui. Il reste honoré lorsqu'il est restrictif,
 * mais l'exclusion s'appuie sur le TYPE du dépôt.
 *
 * Service de LECTURE : il propose, il ne transfère pas. L'exécution reste au
 * module Stock (`stocks.transfers`), qui porte déjà ses propres contrôles.
 */
class TransferProposalService
{
    /** Types de dépôt jamais utilisables comme source d'un transfert. */
    public const SOURCES_INTERDITES = ['quarantaine', 'rebuts', 'chutes'];

    /**
     * @return Collection<int, array{
     *     product: Product, from: Warehouse, to: Warehouse,
     *     quantite: float, deficit: float, disponible_source: float
     * }>
     */
    public function proposals(): Collection
    {
        $stocks = ProductStock::query()
            ->selectRaw('product_id, warehouse_id, SUM(quantity) qty, SUM(reserved_quantity) reserved')
            ->groupBy('product_id', 'warehouse_id')
            ->get();

        if ($stocks->isEmpty()) {
            return collect();
        }

        $warehouses = Warehouse::whereIn('id', $stocks->pluck('warehouse_id')->unique())
            ->get()->keyBy('id');
        $products = Product::whereIn('id', $stocks->pluck('product_id')->unique())
            ->get()->keyBy('id');

        $propositions = collect();

        foreach ($stocks->groupBy('product_id') as $productId => $lignes) {
            $product = $products[$productId] ?? null;
            if (! $product) {
                continue;
            }

            $manques = [];
            $excedents = [];

            foreach ($lignes as $l) {
                $depot = $warehouses[$l->warehouse_id] ?? null;
                if (! $depot) {
                    continue;
                }

                $physique = (float) $l->qty;
                $reserve  = (float) $l->reserved;

                // Manque = ce qui est promis au-delà de ce qui est détenu.
                $manque = round(max(0.0, $reserve - $physique), 4);
                if ($manque > 0 && $this->peutRecevoir($depot)) {
                    $manques[] = ['depot' => $depot, 'quantite' => $manque];
                }

                // Excédent = disponible réel, réservations déduites.
                $excedent = round(max(0.0, $physique - $reserve), 4);
                if ($excedent > 0 && $this->peutFournir($depot)) {
                    $excedents[] = ['depot' => $depot, 'quantite' => $excedent];
                }
            }

            if ($manques === [] || $excedents === []) {
                continue;
            }

            // Le plus gros excédent couvre d'abord le plus gros manque : moins de
            // transferts pour un même volume déplacé.
            usort($manques, fn ($a, $b) => $b['quantite'] <=> $a['quantite']);
            usort($excedents, fn ($a, $b) => $b['quantite'] <=> $a['quantite']);

            foreach ($manques as &$manque) {
                foreach ($excedents as &$excedent) {
                    if ($manque['quantite'] <= 0) {
                        break;
                    }
                    if ($excedent['quantite'] <= 0 || $excedent['depot']->id === $manque['depot']->id) {
                        continue;
                    }

                    $quantite = round(min($manque['quantite'], $excedent['quantite']), 4);

                    $propositions->push([
                        'product'           => $product,
                        'from'              => $excedent['depot'],
                        'to'                => $manque['depot'],
                        'quantite'          => $quantite,
                        'deficit'           => $manque['quantite'],
                        'disponible_source' => $excedent['quantite'],
                    ]);

                    $manque['quantite']   = round($manque['quantite'] - $quantite, 4);
                    $excedent['quantite'] = round($excedent['quantite'] - $quantite, 4);
                }
                unset($excedent);
            }
            unset($manque);
        }

        return $propositions->sortByDesc('quantite')->values();
    }

    /**
     * Un dépôt peut-il FOURNIR ? Jamais depuis la quarantaine, les rebuts ou les
     * chutes — voir l'en-tête de classe.
     */
    private function peutFournir(Warehouse $depot): bool
    {
        if (in_array($depot->type, self::SOURCES_INTERDITES, true)) {
            return false;
        }

        return $this->actifEtTransferable($depot);
    }

    /** Un dépôt peut-il RECEVOIR ? Une quarantaine peut légitimement recevoir. */
    private function peutRecevoir(Warehouse $depot): bool
    {
        return $this->actifEtTransferable($depot);
    }

    private function actifEtTransferable(Warehouse $depot): bool
    {
        // `can_transfer` est aujourd'hui à 1 partout : on l'honore s'il devient
        // restrictif, sans jamais s'en remettre à lui seul.
        //
        // Le modèle le caste en BOOLÉEN : comparer à l'entier 0 ne détecte rien,
        // puisque `false !== 0`. Un drapeau non renseigné (null) reste permissif —
        // c'est une inconnue, pas une interdiction.
        $transferable = $depot->can_transfer === null || (bool) $depot->can_transfer;

        return (bool) $depot->is_active && $transferable;
    }
}
