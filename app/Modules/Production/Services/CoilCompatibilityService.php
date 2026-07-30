<?php

namespace App\Modules\Production\Services;

use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockReservation;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * [MTO — règle 9] Compatibilité entre une bobine et l'ordre de fabrication qui
 * veut la consommer.
 *
 * Avant ce service, `exists:coils,id` était le seul contrôle : n'importe quelle
 * bobine de l'entreprise pouvait être engagée sur n'importe quel OF. Une bobine
 * beige de 27/100 pouvait alimenter un OF de tôle orange de 40/100 sans qu'aucun
 * message n'apparaisse — l'erreur ne se voyait qu'en sortie d'atelier.
 *
 * DEUX NIVEAUX, volontairement distincts :
 *
 *   BLOCAGE   — incohérences structurelles (société, article hors nomenclature,
 *               lot, dépôt, état de la bobine, réservation d'un autre OF) et
 *               écarts avérés sur une caractéristique renseignée des deux côtés.
 *
 *   AVERTISSEMENT — caractéristique absente d'un côté ou des deux. On autorise,
 *               et on journalise. Bloquer serait ici prétendre savoir : deux
 *               valeurs absentes ne sont pas deux valeurs égales. Le parc actuel
 *               ne porte aucune couleur ni épaisseur sur ses bobines ; refuser
 *               l'inconnu arrêterait l'atelier sans rien démontrer.
 *
 * Cette permissivité est TRANSITOIRE et mesurée : `a3:audit-coil-compatibility`
 * chiffre en permanence ce qui reste invérifiable. Elle disparaîtra d'elle-même
 * à mesure que les fiches bobines se complètent.
 *
 * ÉCARTS STRUCTURELS DU MODÈLE, assumés et non signalés à chaque consommation :
 *   - la NUANCE existe sur la bobine (`coils.nuance`) mais l'OF n'a aucun champ
 *     en face ; il n'y a rien à comparer, pas une donnée manquante ;
 *   - le PROFIL existe sur l'OF (`production_orders.profil`) mais pas sur la
 *     bobine — un profil naît du profilage, il n'est pas porté par la matière.
 * Émettre un avertissement à chaque consommation pour ces deux-là produirait un
 * bruit permanent sans information. Ils sont signalés une fois, par l'audit.
 *
 * Le POIDS disponible n'est pas contrôlé ici : CoilConsumptionService le fait
 * déjà, à la quantité près et sous verrou. Le dupliquer produirait deux messages
 * concurrents pour une même cause.
 */
class CoilCompatibilityService
{
    /**
     * Vérifie la compatibilité. Lève si un motif bloquant est trouvé, journalise
     * les avertissements sinon.
     *
     * @throws ValidationException
     */
    public function assertCompatible(ProductionOrder $order, Coil $coil): void
    {
        $blocking = $this->blockingReasons($order, $coil);

        if ($blocking !== []) {
            throw ValidationException::withMessages([
                'coil_id' => sprintf(
                    'Bobine %s incompatible avec l’OF %s : %s',
                    $coil->reference, $order->number, implode(' ', $blocking)
                ),
            ]);
        }

        foreach ($this->warnings($order, $coil) as $warning) {
            Log::channel('security')->notice('production.coil_compatibility.non_verifiee', [
                'of'      => $order->number,
                'bobine'  => $coil->reference,
                'critere' => $warning['critere'],
                'of_valeur'     => $warning['of'],
                'bobine_valeur' => $warning['bobine'],
                'message' => $warning['message'],
            ]);
        }
    }

    /**
     * Motifs BLOQUANTS. Retournés tous ensemble plutôt qu'au premier trouvé :
     * l'opérateur corrige une fois, pas quatre fois de suite.
     *
     * @return list<string>
     */
    public function blockingReasons(ProductionOrder $order, Coil $coil): array
    {
        $reasons = [];

        // 1. Cloisonnement société — jamais franchissable.
        if ($coil->company_id && $order->company_id && (int) $coil->company_id !== (int) $order->company_id) {
            $reasons[] = 'elle appartient à une autre société.';
        }

        // 2. État opérationnel : divisée, transformée, bloquée qualité, épuisée,
        //    ou sans solde. Le modèle centralise déjà cette décision.
        if (! $coil->isOperationallyActive()) {
            $reasons[] = $this->inactiveReason($coil);
        }

        // 3. L'article de la bobine doit figurer parmi les composants autorisés.
        if (($authorized = $this->authorizedComponentIds($order)) !== null
            && $coil->product_id
            && ! in_array((int) $coil->product_id, $authorized, true)) {
            $reasons[] = sprintf(
                'son article (#%d) ne figure pas parmi les composants de la nomenclature.',
                $coil->product_id
            );
        }

        // 4. Cohérence du lot : un lot ne peut pas porter un autre article que la
        //    bobine qui s'y rattache — sinon la traçabilité matière est fausse.
        if ($coil->stock_lot_id && $coil->product_id) {
            $lotProduct = StockLot::whereKey($coil->stock_lot_id)->value('product_id');
            if ($lotProduct && (int) $lotProduct !== (int) $coil->product_id) {
                $reasons[] = sprintf(
                    'son lot de stock porte l’article #%d alors que la bobine porte l’article #%d.',
                    $lotProduct, $coil->product_id
                );
            }
        }

        // 5. Dépôt : si l'OF impose un dépôt matière et que la bobine est ailleurs,
        //    la consommer reviendrait à sortir d'un stock qui n'alimente pas cet OF.
        if ($order->depot_matiere_id && $coil->warehouse_id
            && (int) $order->depot_matiere_id !== (int) $coil->warehouse_id) {
            $reasons[] = sprintf(
                'elle est au dépôt #%d alors que l’OF consomme au dépôt #%d.',
                $coil->warehouse_id, $order->depot_matiere_id
            );
        }

        // 6. Matière déjà promise à un autre OF.
        if ($this->fullyReservedByAnotherOrder($order, $coil)) {
            $reasons[] = 'sa matière est intégralement réservée à un autre ordre de fabrication.';
        }

        // 7. Caractéristiques physiques renseignées des deux côtés et divergentes.
        foreach ($this->characteristicComparisons($order, $coil) as $c) {
            if ($c['verdict'] === 'divergent') {
                $reasons[] = $c['message'];
            }
        }

        return $reasons;
    }

    /**
     * AVERTISSEMENTS : ce qui n'a pas pu être vérifié faute de donnée.
     *
     * @return list<array{critere:string,of:string|null,bobine:string|null,message:string}>
     */
    public function warnings(ProductionOrder $order, Coil $coil): array
    {
        $warnings = [];

        if ($this->authorizedComponentIds($order) === null) {
            $warnings[] = [
                'critere' => 'nomenclature', 'of' => null, 'bobine' => null,
                'message' => 'OF sans nomenclature exploitable : l’appartenance de l’article aux composants n’a pas pu être vérifiée.',
            ];
        }

        foreach ($this->characteristicComparisons($order, $coil) as $c) {
            if ($c['verdict'] === 'invérifiable') {
                $warnings[] = [
                    'critere' => $c['critere'], 'of' => $c['of'], 'bobine' => $c['bobine'],
                    'message' => $c['message'],
                ];
            }
        }

        return $warnings;
    }

    /** Raccourci pour les sélecteurs et les tests. */
    public function isCompatible(ProductionOrder $order, Coil $coil): bool
    {
        return $this->blockingReasons($order, $coil) === [];
    }

    /**
     * Requête des bobines proposables pour cet OF : utilisable comme matière,
     * valorisée, du bon article et du bon dépôt.
     *
     * Ce filtre est un CONFORT D'ÉCRAN, jamais une garantie : il réduit ce que
     * l'opérateur peut choisir, il ne remplace pas `assertCompatible()`, qui
     * seul s'oppose à une requête forgée.
     */
    public function compatibleCoilsQuery(ProductionOrder $order): Builder
    {
        $query = Coil::usableAsMaterial()
            ->where('valuation_status', 'valorisation_definitive')
            ->where('cost_per_kg', '>', 0);

        if (($authorized = $this->authorizedComponentIds($order)) !== null) {
            $query->whereIn('product_id', $authorized);
        }

        if ($order->depot_matiere_id) {
            // Une bobine sans dépôt reste proposable : son emplacement est inconnu,
            // pas contradictoire — c'est le sens du niveau « avertissement ».
            $query->where(fn ($q) => $q->whereNull('warehouse_id')
                ->orWhere('warehouse_id', $order->depot_matiere_id));
        }

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Articles autorisés : composants de la nomenclature de l'OF, substituts
     * compris — un substitut déclaré EST un composant autorisé.
     *
     * Retourne null quand aucune liste n'est exploitable (OF sans nomenclature,
     * ou nomenclature sans ligne active). null ≠ liste vide : l'un veut dire
     * « rien à opposer », l'autre « rien n'est autorisé ».
     *
     * @return list<int>|null
     */
    private function authorizedComponentIds(ProductionOrder $order): ?array
    {
        if (! $order->bill_of_material_id) {
            return null;
        }

        $rows = DB::table('bom_lines')
            ->where('bill_of_material_id', $order->bill_of_material_id)
            ->where(fn ($q) => $q->whereNull('statut')->orWhere('statut', 'actif'))
            ->get(['product_id', 'substitute_product_id']);

        $ids = [];
        foreach ($rows as $r) {
            if ($r->product_id) {
                $ids[] = (int) $r->product_id;
            }
            if ($r->substitute_product_id) {
                $ids[] = (int) $r->substitute_product_id;
            }
        }

        return $ids === [] ? null : array_values(array_unique($ids));
    }

    /**
     * La matière de cette bobine est-elle intégralement promise à un autre OF ?
     *
     * Limite assumée du modèle : les réservations portent sur un couple
     * (article, dépôt), jamais sur une bobine nommée. La protection s'exerce donc
     * à cette granularité. On ne bloque que si, une fois retirées les
     * réservations des AUTRES ordres de fabrication, il ne reste rien de
     * disponible — un stock abondant ne doit pas devenir inconsommable au
     * prétexte qu'un autre OF en a réservé une part.
     *
     * Les réservations de VENTE sont exclues : elles protègent des produits
     * finis promis à un client, pas de la matière première.
     */
    private function fullyReservedByAnotherOrder(ProductionOrder $order, Coil $coil): bool
    {
        if (! $coil->product_id || ! $coil->warehouse_id) {
            return false;
        }

        $reservedByOthers = (float) StockReservation::where('product_id', $coil->product_id)
            ->where('warehouse_id', $coil->warehouse_id)
            ->where('status', 'reserved')
            ->whereNotNull('production_order_id')
            ->where('production_order_id', '!=', $order->id)
            ->sum('quantity');

        if ($reservedByOthers <= 0) {
            return false;
        }

        $inStock = (float) ProductStock::where('product_id', $coil->product_id)
            ->where('warehouse_id', $coil->warehouse_id)
            ->sum('quantity');

        return $inStock - $reservedByOthers <= 0;
    }

    /**
     * Compare les caractéristiques appariables. Chaque entrée porte un verdict :
     * « conforme », « divergent » ou « invérifiable ».
     *
     * @return list<array{critere:string,of:string|null,bobine:string|null,verdict:string,message:string}>
     */
    private function characteristicComparisons(ProductionOrder $order, Coil $coil): array
    {
        return [
            $this->compareText('couleur', $order->color, $coil->color),
            // La largeur de l'OF se lit sur `largeur_totale` : `usable_width` est la
            // largeur UTILE après profilage, en aval de la bobine — les comparer
            // reviendrait à opposer la tôle finie à la matière qui l'a produite.
            $this->compareNumeric(
                'largeur', $order->largeur_totale, $coil->width, 'mm',
                (float) config('production.coil_compatibility.width_tolerance_mm', 1.0)
            ),
            $this->compareNumeric(
                'épaisseur', $order->thickness, $coil->thickness, 'mm',
                // L'OF peut porter sa propre tolérance ; elle prime sur le défaut.
                $order->tolerance_epaisseur !== null && (float) $order->tolerance_epaisseur > 0
                    ? (float) $order->tolerance_epaisseur
                    : (float) config('production.coil_compatibility.thickness_tolerance_mm', 0.01)
            ),
            $this->compareText('revêtement', $order->revetement, $coil->coating),
        ];
    }

    /** @return array{critere:string,of:string|null,bobine:string|null,verdict:string,message:string} */
    private function compareText(string $critere, mixed $ofValue, mixed $coilValue): array
    {
        $a = $this->normalize($ofValue);
        $b = $this->normalize($coilValue);

        if ($a === null || $b === null) {
            return [
                'critere' => $critere,
                'of' => $ofValue !== null ? (string) $ofValue : null,
                'bobine' => $coilValue !== null ? (string) $coilValue : null,
                'verdict' => 'invérifiable',
                'message' => sprintf(
                    '%s non vérifiée (OF : %s, bobine : %s).',
                    $this->capitalize($critere), $ofValue ?: '—', $coilValue ?: '—'
                ),
            ];
        }

        return [
            'critere' => $critere,
            'of' => (string) $ofValue,
            'bobine' => (string) $coilValue,
            'verdict' => $a === $b ? 'conforme' : 'divergent',
            'message' => sprintf(
                '%s attendue « %s », bobine « %s ».',
                $this->capitalize($critere), $ofValue, $coilValue
            ),
        ];
    }

    /**
     * Comparaison DÉCIMALE avec tolérance — jamais de comparaison de chaînes :
     * MySQL rend « 0.30 » là où la saisie disait 0.3, et « 0.30 » !== « 0.3 ».
     *
     * @return array{critere:string,of:string|null,bobine:string|null,verdict:string,message:string}
     */
    private function compareNumeric(string $critere, mixed $ofValue, mixed $coilValue, string $unit, float $tolerance): array
    {
        $a = $this->numeric($ofValue);
        $b = $this->numeric($coilValue);

        if ($a === null || $b === null) {
            return [
                'critere' => $critere,
                'of' => $a !== null ? (string) $a : null,
                'bobine' => $b !== null ? (string) $b : null,
                'verdict' => 'invérifiable',
                'message' => sprintf(
                    '%s non vérifiée (OF : %s, bobine : %s).',
                    $this->capitalize($critere), $a ?? '—', $b ?? '—'
                ),
            ];
        }

        $ecart = abs($a - $b);

        return [
            'critere' => $critere,
            'of' => (string) $a,
            'bobine' => (string) $b,
            // Le `1e-9` absorbe la représentation binaire des flottants : sans lui,
            // un écart théoriquement égal à la tolérance peut la dépasser d'un
            // milliardième et faire échouer un contrôle pourtant exact.
            'verdict' => $ecart <= $tolerance + 1e-9 ? 'conforme' : 'divergent',
            'message' => sprintf(
                '%s attendue %s %s, bobine %s %s (écart %s, tolérance %s).',
                $this->capitalize($critere), $a, $unit, $b, $unit,
                rtrim(rtrim(number_format($ecart, 3, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($tolerance, 3, '.', ''), '0'), '.')
            ),
        ];
    }

    /**
     * Majuscule initiale sûre en UTF-8. `ucfirst()` travaille sur l'octet : sur
     * « épaisseur », il laisse le « é » intact et rend une phrase qui commence en
     * minuscule là où « Couleur » et « Largeur » sont capitalisés.
     */
    private function capitalize(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($s, 1, null, 'UTF-8');
    }

    /** Normalise une chaîne comparable : casse, accents et espaces neutralisés. */
    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/u', ' ', $s);
    }

    /** Valeur numérique exploitable, ou null. Un zéro n'est pas une valeur renseignée. */
    private function numeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $f = (float) $value;

        return $f > 0 ? $f : null;
    }

    /** Cause précise de l'indisponibilité — « bobine inutilisable » n'aide personne. */
    private function inactiveReason(Coil $coil): string
    {
        if ($coil->isSplit() || $coil->transformation_status === Coil::TRANSFO_TRANSFORMED) {
            return 'elle a été divisée ou transformée : sa matière appartient désormais à ses bobines filles.';
        }
        if ($coil->quality_status !== null && in_array($coil->quality_status, Coil::QUALITY_BLOCKING, true)) {
            return sprintf('son statut qualité « %s » interdit la consommation.', $coil->quality_status);
        }
        if ($coil->status === 'epuisee' || (float) $coil->remaining_weight <= 0) {
            return 'elle est épuisée.';
        }

        return 'elle n’est pas dans un état opérationnel exploitable.';
    }
}
