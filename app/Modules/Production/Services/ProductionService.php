<?php

namespace App\Modules\Production\Services;
use App\Services\DocumentSequenceService;

use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Notifications\ValidationStepNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Cycle de vie d'un ordre de fabrication (OF).
 *
 * Workflow : brouillon → lance → en_cours → termine
 *            (annulation possible depuis tout statut non clôturé)
 *
 * La consommation matière, les sorties produits finis et le coût de revient
 * sont gérés en P4/P5 (CoilConsumptionService, ProductionCostService).
 */
class ProductionService
{
    public function __construct(
        private DocumentSequenceService $sequences,
        private ProductionAccountingService $accounting,
    ) {}

    /** Crée un OF en brouillon avec numéro auto + lignes de détail. */
    public function create(array $data, array $lines = []): ProductionOrder
    {
        return DB::transaction(function () use ($data, $lines) {
            $company = currentCompany();

            $data['company_id']     = $company->id;
            $data['fiscal_year_id'] = $company->current_fiscal_year_id;
            $data['number']         = $this->sequences->nextNumber($company, 'ordre_fabrication');
            $data['status']         = 'brouillon';

            // [X3] Héritage des dépôts / ligne quand ils ne sont pas fournis
            // (OF manuel sans saisie, ou OF auto MTO depuis commande).
            $this->applyDefaults($data);

            $order = ProductionOrder::create($data);
            $this->syncLines($order, $lines);
            $this->recomputeQuantities($order);

            return $order->fresh('lines');
        });
    }

    /**
     * [X3] Renseigne les dépôts / ligne / site manquants à partir de l'article
     * puis des dépôts typés, pour qu'un OF soit exploitable sans ressaisie.
     * Ne remplace jamais une valeur déjà fournie (saisie manuelle prioritaire).
     */
    /** [X3] Pré-remplit un OF non persisté (formulaire création) avec les dépôts / ligne par défaut. */
    public function fillDefaultsForForm(ProductionOrder $order): ProductionOrder
    {
        $data = $order->getAttributes();
        $this->applyDefaults($data);
        foreach (['depot_matiere_id', 'depot_produit_fini_id', 'depot_qualite_id', 'production_line_id', 'responsible_id'] as $k) {
            if (empty($order->$k) && ! empty($data[$k])) {
                $order->$k = $data[$k];
            }
        }

        return $order;
    }

    private function applyDefaults(array &$data): void
    {
        $product = ! empty($data['product_id']) ? \App\Models\Product::find($data['product_id']) : null;

        $filled = fn (string $k) => ! empty($data[$k]);
        $warehouseByType = fn (string $type) => \App\Models\Warehouse::where('is_active', true)->where('type', $type)->value('id');

        // Dépôt matière : article (dépôt production) → dépôt matière première
        if (! $filled('depot_matiere_id')) {
            $data['depot_matiere_id'] = $product?->production_warehouse_id ?: $warehouseByType('matiere_premiere');
        }
        // Dépôt produit fini : article (dépôt vente) → dépôt produit fini
        if (! $filled('depot_produit_fini_id')) {
            $data['depot_produit_fini_id'] = $product?->sale_warehouse_id ?: $warehouseByType('produit_fini');
        }
        // Dépôt qualité : article (dépôt qualité)
        if (! $filled('depot_qualite_id') && $product?->quality_warehouse_id) {
            $data['depot_qualite_id'] = $product->quality_warehouse_id;
        }
        // Ligne de production : ligne dédiée à l'article → ligne du type d'article
        // (fer à béton vs tôle bac) → première ligne active.
        if (! $filled('production_line_id')) {
            $data['production_line_id'] = $this->defaultLineId($product);
        }
        // Nomenclature : rattache la BOM active de l'article (sinon la fiche affiche « — »
        // et l'allocation matière n'a pas de composants).
        if (! $filled('bill_of_material_id') && $product) {
            $data['bill_of_material_id'] = \App\Modules\Production\Models\BillOfMaterial::where('is_active', true)
                ->where('product_id', $product->id)->orderByDesc('id')->value('id');
        }
        // Responsable : à défaut, l'utilisateur courant
        if (! $filled('responsible_id')) {
            $data['responsible_id'] = Auth::id();
        }
    }

    /**
     * Ligne de production par défaut selon l'article :
     *   1. ligne explicitement dédiée à l'article (production_lines.product_id) ;
     *   2. ligne du bon type — fer à béton (code *FAB / nom « FER ») vs tôle bac ;
     *   3. repli : première ligne active.
     * Les lignes n'ont pas de colonne « type » → détection par nom/code.
     */
    private function defaultLineId(?\App\Models\Product $product): ?int
    {
        $Line = \App\Modules\Production\Models\ProductionLine::query()->where('is_active', true);

        if ($product) {
            if ($dedicated = (clone $Line)->where('product_id', $product->id)->value('id')) {
                return $dedicated;
            }

            $code  = strtoupper((string) $product->code_article);
            $name  = strtoupper((string) $product->name);
            $isFer = str_contains($code, 'FAB') || str_contains($name, 'FER');
            $needles = $isFer ? ['%fer%', 'LIGNE-FER%'] : ['%bac%', '%tôle%', '%tole%'];

            $typed = (clone $Line)->where(function ($w) use ($needles) {
                foreach ($needles as $p) {
                    $w->orWhere('name', 'like', $p)->orWhere('code', 'like', $p);
                }
            })->orderBy('id')->value('id');

            if ($typed) {
                return $typed;
            }
        }

        return (clone $Line)->orderBy('id')->value('id');
    }

    /** Met à jour un OF éditable (brouillon/lancé) + ses lignes. */
    public function update(ProductionOrder $order, array $data, array $lines = []): ProductionOrder
    {
        $viaModification = $order->isEditableViaModification();
        if (! $order->isEditable() && ! $viaModification) {
            throw ValidationException::withMessages(['status' => 'OF non modifiable dans son statut actuel.']);
        }

        return DB::transaction(function () use ($order, $data, $lines, $viaModification) {
            $order->update($data);
            $order->lines()->delete();
            $this->syncLines($order, $lines);
            $this->recomputeQuantities($order);

            // [§13.10 CDC] L'autorisation de modification est à usage unique :
            // une fois la modification appliquée, le circuit se réinitialise —
            // toute nouvelle modification d'un OF en_cours devra repasser par
            // les 4 étapes (chef → commercial → finance → DG).
            if ($viaModification) {
                $order->update(['modification_status' => 'aucune']);
            }

            return $order->fresh('lines');
        });
    }

    /** brouillon → matière allouée (allocation matière effectuée). */
    public function allocateMaterial(ProductionOrder $order): void
    {
        $this->assertStatus($order, 'brouillon');
        $order->update(['status' => 'matiere_allouee']);
    }

    /**
     * [§13.2 CDC] Vérifie la condition financière avant lancement.
     * Comptant : 100% encaissé. Acompte : ≥70%. Crédit : validation DAF/DG explicite.
     * Sans commande liée ou si déjà autorisé → passe.
     *
     * @throws ValidationException si condition non remplie
     */
    public function checkFinancialGate(ProductionOrder $order): void
    {
        // Déjà autorisé ou bypassé
        if (in_array($order->financial_authorization, ['approved', 'bypassed'], true)) {
            return;
        }

        // Pas de commande de vente liée → pas de gate financière
        if (! $order->order_id) {
            return;
        }

        $order->loadMissing('order.client');
        $salesOrder = $order->order;
        if (! $salesOrder) {
            return;
        }

        $client   = $salesOrder->client;
        $totalTtc = (float) $salesOrder->total_ttc;
        if ($totalTtc <= 0) {
            return;
        }

        // Encaissements déjà reçus pour cette commande :
        // orders → invoices(order_id) → client_payment_allocations(invoice_id)
        $invoiceIds = \App\Models\Invoice::where('order_id', $salesOrder->id)->pluck('id');
        $paidViaInvoice = $invoiceIds->isNotEmpty()
            ? (float) \App\Models\ClientPaymentAllocation::whereIn('invoice_id', $invoiceIds)
                ->whereHas('clientPayment', fn ($q) => $q->where('status', 'confirme'))
                ->sum('amount')
            : 0.0;

        // Acomptes non alloués reçus directement du client (is_acompte=true)
        $acompteLibre = (float) \App\Models\ClientPayment::where('client_id', $salesOrder->client_id)
            ->where('status', 'confirme')
            ->where('is_acompte', true)
            ->sum('unallocated_amount');

        $paid = $paidViaInvoice + $acompteLibre;
        $rate = $totalTtc > 0 ? round(($paid / $totalTtc) * 100, 1) : 0;

        // Déterminer le mode de paiement du client
        $paymentMode = $client?->payment_mode ?? 'credit'; // défaut = crédit si non défini

        $order->update(['payment_mode' => $paymentMode, 'payment_rate' => $rate]);

        if ($paymentMode === 'comptant' && $rate < 100) {
            $this->notifyFinancialGateBlocked($order, "Client comptant : paiement à {$rate}% — solde requis avant fabrication.");
            throw ValidationException::withMessages([
                'financial' => "Client comptant : paiement intégral requis avant fabrication ({$rate}% encaissé sur {$totalTtc} FCFA). Demandez l'autorisation financière.",
            ]);
        }

        if ($paymentMode === 'acompte' && $rate < 70) {
            $this->notifyFinancialGateBlocked($order, "Client acompte : {$rate}% encaissé — seuil 70% non atteint.");
            throw ValidationException::withMessages([
                'financial' => "Client acompte : acompte ≥ 70% requis ({$rate}% encaissé). Demandez l'autorisation DAF.",
            ]);
        }

        if ($paymentMode === 'credit') {
            $this->notifyFinancialGateBlocked($order, 'Client crédit — validation DAF/DG requise avant lancement OF.');
            throw ValidationException::withMessages([
                'financial' => 'Client crédit : validation DAF/DG obligatoire avant lancement OF. Demandez l\'autorisation financière.',
            ]);
        }

        // Condition remplie — on enregistre l'autorisation automatique
        $order->update([
            'financial_authorization'  => 'approved',
            'financial_authorized_at'  => now(),
            'financial_notes'          => "Autorisé automatiquement : {$paymentMode} / {$rate}%",
        ]);
    }

    // ─── §13.3 CDC : validation 2-niveaux avant lancement ───────────────────

    /** brouillon | matiere_allouee → attente_chef */
    public function submitForValidation(ProductionOrder $order): void
    {
        $this->assertStatus($order, ['brouillon', 'matiere_allouee']);
        $order->update(['status' => 'attente_chef']);

        // [CDC §13.3] OF soumis → Chef Atelier valide en premier.
        ValidationStepNotification::sendToRoles(
            ['chef_atelier'],
            title: 'OF en attente de validation',
            message: "OF {$order->number} soumis — validation Chef Atelier requise.",
            url: route('production.orders.show', $order),
            modelType: 'ProductionOrder',
            modelId: $order->id,
            type: 'of_submitted',
        );
    }

    /** attente_chef → attente_responsable */
    public function validateByChef(ProductionOrder $order): void
    {
        $this->assertStatus($order, 'attente_chef');
        $order->update(['status' => 'attente_responsable']);

        // [CDC §13.3] Chef Atelier validé → Responsable Production valide ensuite.
        ValidationStepNotification::sendToRoles(
            ['chef_production', 'directeur_usine'],
            title: 'OF — validation Responsable Production requise',
            message: "OF {$order->number} validé par le Chef Atelier — validation Responsable Production en attente.",
            url: route('production.orders.show', $order),
            modelType: 'ProductionOrder',
            modelId: $order->id,
            type: 'of_chef_validated',
        );
    }

    /** Notifie le DAF qu'une autorisation financière manuelle est requise pour lancer un OF. */
    private function notifyFinancialGateBlocked(ProductionOrder $order, string $reason): void
    {
        ValidationStepNotification::sendToRoles(
            ['daf'],
            title: 'Autorisation financière requise',
            message: "OF {$order->number} bloqué au lancement — {$reason}",
            url: route('production.orders.show', $order),
            modelType: 'ProductionOrder',
            modelId: $order->id,
            type: 'of_financial_gate_blocked',
            icon: 'currency-dollar',
            color: 'red',
        );
    }

    /** attente_responsable → matiere_allouee (prêt à lancer) */
    public function validateByResponsable(ProductionOrder $order): void
    {
        $this->assertStatus($order, 'attente_responsable');
        $order->update(['status' => 'matiere_allouee']);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** brouillon | matière allouée → lance */
    public function launch(ProductionOrder $order, bool $force = false): void
    {
        $this->assertStatus($order, ['brouillon', 'matiere_allouee']);

        // [Audit OF — nomenclature obligatoire] Un article marqué « fabriqué »
        // (is_manufacturable) ne peut pas être lancé sans nomenclature : sans BOM,
        // aucune consommation matière n'est calculable → stock matière faux, coût
        // de revient faux, traçabilité absente. Rattachez une nomenclature à l'OF.
        $order->loadMissing('product');
        if ($order->product?->is_manufacturable && ! $order->bill_of_material_id) {
            throw ValidationException::withMessages([
                'bill_of_material' => "Article fabriqué « {$order->product->name} » : nomenclature obligatoire avant lancement. Rattachez une BOM à l'OF (sinon aucune consommation matière ne sera tracée).",
            ]);
        }

        // §13.2 CDC — Gate financière obligatoire
        $this->checkFinancialGate($order);

        // [CDC §3 — cohérence stock] Rupture matière = lancement BLOQUÉ. On ne
        // fabrique pas sans composant disponible. Les articles allow_negative_stock
        // et les bobines (coils) sont déjà exclus par materialShortages(). Une
        // dérogation explicite ($force, réservée aux valideurs) peut passer outre.
        if (! $force) {
            $shortages = $this->materialShortages($order);
            if ($shortages) {
                $msg = collect($shortages)->map(function ($s) {
                    $base = sprintf('%s (besoin %s / dispo %s)', $s['product'],
                        number_format($s['need'], 0, ',', ' '), number_format($s['available'], 0, ',', ' '));
                    if (! empty($s['substitute']) && ! empty($s['substitute_covers'])) {
                        $base .= sprintf(' — substitut « %s » disponible (%s)', $s['substitute'],
                            number_format($s['substitute_available'], 0, ',', ' '));
                    }

                    return $base;
                })->implode(' · ');

                throw ValidationException::withMessages([
                    'material' => "Lancement bloqué — matière insuffisante : {$msg}. Réapprovisionnez le stock ou lancez en dérogation.",
                ]);
            }
        }

        $order->update(['status' => 'lance', 'launched_at' => now()]);

        // [§3] Chargement automatique des opérations depuis la gamme opératoire
        // (si une gamme existe et que les opérations n'ont pas déjà été générées).
        // Non bloquant : absence de gamme ou opérations déjà présentes = no-op.
        try {
            $order->loadMissing('billOfMaterial.routing');
            if (! $order->operations()->exists() && $order->billOfMaterial?->routing) {
                app(RoutingService::class)->generateWorkOrders($order);
            }
        } catch (\Throwable $e) {
            // gamme absente / déjà générée — on ne bloque pas le lancement
        }
    }

    /**
     * [§3 — option C] Pénuries de matière (avertissement NON bloquant).
     * Compare le besoin (BOM × quantité) au stock disponible (product_stocks).
     * Ignore les composants non suivis en product_stocks (ex. bobines, gérées
     * dans `coils`) pour éviter les faux positifs. Respecte allow_negative_stock.
     *
     * @return array<int,array{product:string,need:float,available:float}>
     */
    public function materialShortages(ProductionOrder $order): array
    {
        $order->loadMissing('billOfMaterial.lines.product', 'billOfMaterial.lines.substitute');
        $bom = $order->billOfMaterial;
        $qty = (float) ($order->quantity_requested ?: 0);
        if (! $bom || $qty <= 0) {
            return [];
        }

        $shortages = [];
        foreach ($bom->lines as $line) {
            $product = $line->product;
            if (! $product || $product->allow_negative_stock) {
                continue;
            }
            $rows = \App\Models\ProductStock::where('product_id', $product->id)->get(['quantity', 'reserved_quantity']);
            if ($rows->isEmpty()) {
                continue; // composant non suivi en product_stocks (bobines…) — pas d'alerte
            }
            $available = (float) $rows->sum(fn ($s) => (float) $s->quantity - (float) $s->reserved_quantity);
            $need = (float) $line->quantity_per_meter * $qty;
            if ($need > $available) {
                $row = ['product' => $product->name, 'need' => round($need, 2), 'available' => round($available, 2)];

                // [PRO-05] Remplacement contrôlé : si un substitut est défini sur la ligne
                // de nomenclature, on remonte sa disponibilité pour éclairer l'allocation.
                if ($line->substitute) {
                    $subRows = \App\Models\ProductStock::where('product_id', $line->substitute_product_id)
                        ->get(['quantity', 'reserved_quantity']);
                    $subAvail = (float) $subRows->sum(fn ($s) => (float) $s->quantity - (float) $s->reserved_quantity);
                    $row['substitute'] = $line->substitute->name;
                    $row['substitute_available'] = round($subAvail, 2);
                    $row['substitute_covers'] = $subAvail >= $need;
                }

                $shortages[] = $row;
            }
        }

        return $shortages;
    }

    /** lance → en_cours */
    public function start(ProductionOrder $order): void
    {
        $this->assertStatus($order, 'lance');
        $order->update(['status' => 'en_cours']);
    }

    /** en_cours → terminé partiellement (production partielle, reste à produire). */
    public function markPartiallyDone(ProductionOrder $order): void
    {
        $this->assertStatus($order, ['en_cours', 'termine_partiellement']);

        // [Paramétrage OF] « Autoriser clôture partielle » = Non → la clôture
        // partielle est interdite : l'OF doit produire la quantité demandée
        // avant toute clôture (règle contractuelle client / OF spéciale).
        // (=== false : un attribut non hydraté (null) suit le défaut métier « autorisé »)
        if ($order->autoriser_cloture_partielle === false) {
            throw ValidationException::withMessages([
                'status' => 'Clôture partielle non autorisée sur cet OF (paramètre « Autoriser clôture partielle » = Non). Produisez la quantité demandée ou modifiez le paramétrage.',
            ]);
        }

        $order->update(['status' => 'termine_partiellement']);
    }

    /** en_cours | terminé partiellement → termine */
    public function finish(ProductionOrder $order, bool $force = false): void
    {
        $this->assertStatus($order, ['en_cours', 'termine_partiellement']);

        // [CDC §13.3] Chef équipe valide les déclarations : un OF ne peut être
        // clôturé tant qu'une sortie de production n'a pas reçu le visa.
        $pending = $order->outputs()->where('status', '!=', 'validee')->count();
        if ($pending > 0) {
            throw ValidationException::withMessages([
                'outputs' => "{$pending} déclaration(s) de production en attente du visa chef d'équipe — clôture impossible.",
            ]);
        }

        // [Cohérence stock/production] Un OF sans aucune quantité produite ne peut
        // être clôturé « terminé » : cela créerait un OF fantôme qui bloque ensuite
        // la réservation du produit fini et la livraison (« quantité produite 0 »).
        // L'utilisateur doit d'abord déclarer la production (onglet Suivi) puis la
        // faire viser, ou annuler l'OF s'il n'a rien produit.
        if ((float) $order->quantity_produced <= 0) {
            throw ValidationException::withMessages([
                'quantity_produced' => "Aucune quantité produite déclarée sur cet OF — clôture impossible. Déclarez d'abord la production réelle (Suivi → Déclaration de production) et faites-la viser, ou annulez l'OF.",
            ]);
        }

        // [Cohérence demande/production] Clôturer « terminé » avec une quantité
        // produite inférieure à la quantité demandée abandonne le reliquat sans
        // trace. On bloque : soit « Terminer partiellement » (reste à produire),
        // soit clôture en dérogation explicite ($force) avec écart assumé.
        $requested = (float) $order->quantity_requested;
        $produced  = (float) $order->quantity_produced;
        if (! $force && $requested > 0 && $produced < $requested) {
            throw ValidationException::withMessages([
                'quantity_produced' => sprintf(
                    "Quantité produite (%s) inférieure à la quantité demandée (%s) — clôture définitive bloquée. Utilisez « Terminer partiellement » pour laisser le reliquat, ou confirmez la clôture avec écart.",
                    rtrim(rtrim(number_format($produced, 2, ',', ' '), '0'), ','),
                    rtrim(rtrim(number_format($requested, 2, ',', ' '), '0'), ',')
                ),
            ]);
        }

        // [Audit OF — consommation matière] Un OF avec nomenclature ne peut pas être
        // clôturé « terminé » sans AUCUNE matière consommée : ni bobine (consumptions),
        // ni composant BOM sorti du stock (mouvements liés aux déclarations). Sinon le
        // stock matière et le coût de revient sont faux. Dérogation valideur ($force).
        // (une BOM sans ligne n'a rien à consommer — garde inapplicable)
        if (! $force && $order->bill_of_material_id
            && \App\Modules\Production\Models\BomLine::where('bill_of_material_id', $order->bill_of_material_id)->exists()
            && $order->consumptions()->doesntExist()) {
            $outputIds = $order->outputs()->pluck('id');
            $hasComponentMoves = $outputIds->isNotEmpty()
                && \App\Models\StockMovement::where('type', 'sortie')
                    ->where('reference_type', \App\Modules\Production\Models\ProductionOutput::class)
                    ->whereIn('reference_id', $outputIds)
                    ->exists();
            if (! $hasComponentMoves) {
                throw ValidationException::withMessages([
                    'consumption' => 'Aucune matière consommée sur cet OF (nomenclature présente) — clôture bloquée. Déclarez la consommation bobine/composants, ou clôturez en dérogation avec écart assumé.',
                ]);
            }
        }

        // [Audit OF — gamme opératoire] Les opérations générées depuis la gamme
        // doivent être terminées avant la clôture définitive : un OF « terminé »
        // avec des opérations à faire fausse le suivi d'atelier (0/N) et les temps
        // réels. Dérogation valideur possible ($force).
        if (! $force) {
            $pendingOps = $order->operations()->where('status', '!=', 'done')->count();
            if ($pendingOps > 0) {
                throw ValidationException::withMessages([
                    'operations' => "{$pendingOps} opération(s) de gamme non terminée(s) — clôture bloquée. Terminez les opérations (section Opérations) ou clôturez en dérogation.",
                ]);
            }
        } else {
            // [Backflush gamme] Clôture en dérogation : l'OF est déclaré terminé,
            // donc les opérations restantes sont réputées réalisées au temps
            // standard (real = planned si aucun réel saisi). Sinon la fiche reste
            // à « 0/N — À faire » sur un OF terminé et les temps gamme sont perdus.
            $order->operations()
                ->where('status', '!=', 'done')
                ->update([
                    'status'       => 'done',
                    'real_minutes' => DB::raw('COALESCE(NULLIF(real_minutes, 0), planned_minutes)'),
                    'ended_at'     => now(),
                ]);
        }

        // [Audit OF — contrôle qualité obligatoire] Si l'OF exige un contrôle qualité
        // (controle_qualite_obligatoire), il doit exister au moins un contrôle
        // enregistré avant la clôture définitive. Dérogation valideur possible
        // ($force — même circuit que l'écart quantité, tracé côté contrôleur).
        if (! $force && $order->controle_qualite_obligatoire && $order->qualityControls()->doesntExist()) {
            throw ValidationException::withMessages([
                'quality' => 'Contrôle qualité obligatoire non réalisé — clôture impossible. Enregistrez au moins un contrôle (section Contrôle qualité) avant de terminer l\'OF.',
            ]);
        }

        $order->update(['status' => 'termine', 'finished_at' => now()]);

        // Pont comptable SYSCOHADA (no-op si désactivé — OFF par défaut)
        $this->accounting->postForOrder($order);

        // ── Automatismes post-clôture (best-effort : n'annulent jamais la clôture) ──
        $this->afterFinish($order);
    }

    /**
     * [Audit OF] Automatismes déclenchés à la clôture « terminé » :
     *  1. Lot de fabrication auto si aucun lot (traçabilité PF obligatoire) ;
     *  2. Réservation du produit fini pour le client de la commande liée
     *     (le BL consommera/libérera cette réservation à la validation) ;
     *  3. Coût de revient calculé automatiquement (recalculable ensuite).
     * Chaque étape est isolée : un échec (donnée manquante) est silencieux et
     * l'action reste faisable manuellement depuis la fiche OF.
     */
    private function afterFinish(ProductionOrder $order): void
    {
        // Verdict du dernier contrôle qualité (conditionne lot + réservation).
        $lastQc = $order->qualityControls()->latest('id')->first();
        $qcOk   = ! $order->controle_qualite_obligatoire || $lastQc?->status === 'conforme';

        // 1. Lot de fabrication automatique
        try {
            if ($order->batches()->doesntExist()) {
                app(BatchService::class)->createForOrder($order);
            }
        } catch (\Throwable $e) {
            // lot créable manuellement depuis la fiche
        }

        // 1bis + 2. Effets qualité (lot conforme, réservation PF client) —
        // logique partagée avec le contrôle qualité posé APRÈS clôture.
        $this->applyQualityOutcomes($order, $lastQc?->status, $qcOk);

        // 3. Coût de revient automatique
        try {
            app(ProductionCostService::class)->compute($order->fresh());
        } catch (\Throwable $e) {
            // recalculable depuis la fiche (« Calculer »)
        }
    }

    /**
     * [Audit OF] Effets d'un verdict qualité sur un OF terminé (ou en clôture) :
     * lot PF « conforme » + réservation automatique pour la commande liée.
     * Appelé à la clôture (afterFinish) ET quand un contrôle conforme est posé
     * après coup (ProductionQualityController) — source unique, pas de doublon.
     * Best-effort : chaque effet reste faisable manuellement depuis la fiche.
     */
    public function applyQualityOutcomes(ProductionOrder $order, ?string $qcStatus, ?bool $qualityAcquired = null): void
    {
        $qualityAcquired ??= (! $order->controle_qualite_obligatoire || $qcStatus === 'conforme');

        try {
            if ($qcStatus === 'conforme') {
                $order->batches()->where('status', 'en_cours')->update(['status' => 'conforme']);
            }
        } catch (\Throwable $e) {
            // statut lot modifiable manuellement
        }

        try {
            if ($qualityAcquired && $order->order_id && $order->product_id && (float) $order->quantity_produced > 0
                && ! \App\Models\StockReservation::where('production_order_id', $order->id)->where('status', 'reserved')->exists()) {
                app(ReservationService::class)->reserveForOrder($order->fresh());
            }
        } catch (\Throwable $e) {
            // réservation faisable manuellement (« Réserver pour le client »)
        }
    }

    /** Annulation depuis tout statut non clôturé. */
    public function cancel(ProductionOrder $order, ?string $reason = null): void
    {
        if (in_array($order->status, ['termine', 'annule'], true)) {
            throw ValidationException::withMessages(['status' => 'OF déjà clôturé — annulation impossible.']);
        }

        $note = $order->notes;
        if ($reason) {
            $note = trim(($note ? $note . "\n" : '') . 'Annulé : ' . $reason);
        }
        $order->update(['status' => 'annule', 'notes' => $note]);

        // [V4] Libère les réservations de produit fini liées à cet OF.
        app(ReservationService::class)->releaseForProductionOrder($order);
    }

    // ─── §13.10 CDC : modification OF exceptionnelle — 4 étapes séquentielles ──
    // Chef Production → Commercial → Finance → DG. Chaque étape exige que la
    // précédente ait été franchie ; toute étape peut rejeter, ce qui clôt la
    // demande (modification_status = 'refusee') sans toucher au statut de l'OF.

    /** OF lancé/en_cours/termine_partiellement → ouvre une demande de modification. */
    public function requestModification(ProductionOrder $order, string $reason, ?User $user = null): void
    {
        if (! in_array($order->status, ['lance', 'en_cours', 'termine_partiellement'], true)) {
            throw ValidationException::withMessages(['status' => 'Modification impossible dans ce statut.']);
        }
        if ($order->modification_status === 'en_attente') {
            throw ValidationException::withMessages(['status' => 'Une demande de modification est déjà en cours pour cet OF.']);
        }

        $order->update([
            'modification_status'             => 'en_attente',
            'modification_reason'             => $reason,
            'modification_requested_at'       => now(),
            'modification_requested_by'       => $user?->id ?? Auth::id(),
            // Reset des avis précédents — nouvelle demande, nouveau circuit.
            'modification_chef_avis_at'       => null, 'modification_chef_avis_by'       => null, 'modification_chef_comment'       => null,
            'modification_commercial_avis_at' => null, 'modification_commercial_avis_by' => null, 'modification_commercial_comment' => null,
            'modification_finance_avis_at'    => null, 'modification_finance_avis_by'    => null, 'modification_finance_comment'    => null,
            'modification_dg_approved_at'     => null, 'modification_dg_approved_by'     => null, 'modification_dg_comment'         => null,
        ]);

        // [CDC §13.10] Demande déposée → Chef Production donne le 1er avis.
        $this->notifyModificationStep($order, ['chef_production', 'directeur_usine'],
            'Modification OF — avis Chef Production requis',
            "Demande de modification sur OF {$order->number} : {$reason}");
    }

    /** Étape 1/4 — avis Chef Production. */
    public function giveModificationChefAvis(ProductionOrder $order, ?string $comment = null, ?User $user = null): void
    {
        $this->assertModificationPending($order);
        if ($order->modification_chef_avis_at) {
            throw ValidationException::withMessages(['status' => 'Avis Chef Production déjà donné.']);
        }
        $order->update([
            'modification_chef_avis_at' => now(),
            'modification_chef_avis_by' => $user?->id ?? Auth::id(),
            'modification_chef_comment' => $comment,
        ]);

        // [CDC §13.10] Avis Chef Production donné → Commercial avise ensuite.
        $this->notifyModificationStep($order, ['commercial'],
            'Modification OF — avis Commercial requis',
            "OF {$order->number} : avis Chef Production donné, votre avis est requis.");
    }

    /** Étape 2/4 — avis Commercial (exige l'avis Chef Production). */
    public function giveModificationCommercialAvis(ProductionOrder $order, ?string $comment = null, ?User $user = null): void
    {
        $this->assertModificationPending($order);
        if (! $order->modification_chef_avis_at) {
            throw ValidationException::withMessages(['status' => 'L\'avis Chef Production doit précéder l\'avis Commercial.']);
        }
        if ($order->modification_commercial_avis_at) {
            throw ValidationException::withMessages(['status' => 'Avis Commercial déjà donné.']);
        }
        $order->update([
            'modification_commercial_avis_at' => now(),
            'modification_commercial_avis_by' => $user?->id ?? Auth::id(),
            'modification_commercial_comment' => $comment,
        ]);

        // [CDC §13.10] Avis Commercial donné → Finance avise ensuite.
        $this->notifyModificationStep($order, ['daf', 'comptable'],
            'Modification OF — avis Finance requis',
            "OF {$order->number} : avis Commercial donné, votre avis Finance est requis.");
    }

    /** Étape 3/4 — avis Finance (exige l'avis Commercial). */
    public function giveModificationFinanceAvis(ProductionOrder $order, ?string $comment = null, ?User $user = null): void
    {
        $this->assertModificationPending($order);
        if (! $order->modification_commercial_avis_at) {
            throw ValidationException::withMessages(['status' => 'L\'avis Commercial doit précéder l\'avis Finance.']);
        }
        if ($order->modification_finance_avis_at) {
            throw ValidationException::withMessages(['status' => 'Avis Finance déjà donné.']);
        }
        $order->update([
            'modification_finance_avis_at' => now(),
            'modification_finance_avis_by' => $user?->id ?? Auth::id(),
            'modification_finance_comment' => $comment,
        ]);

        // [CDC §13.10] Avis Finance donné → DG valide en dernier.
        $this->notifyModificationStep($order, ['directeur'],
            'Modification OF — validation DG requise',
            "OF {$order->number} : tous les avis donnés, validation finale DG requise.");
    }

    /** Étape 4/4 — validation finale DG (exige l'avis Finance). Débloque l'édition. */
    public function approveModificationByDg(ProductionOrder $order, ?string $comment = null, ?User $user = null): void
    {
        $this->assertModificationPending($order);
        if (! $order->modification_finance_avis_at) {
            throw ValidationException::withMessages(['status' => 'L\'avis Finance doit précéder la validation DG.']);
        }
        $order->update([
            'modification_status'         => 'approuvee',
            'modification_dg_approved_at' => now(),
            'modification_dg_approved_by' => $user?->id ?? Auth::id(),
            'modification_dg_comment'     => $comment,
        ]);

        // [CDC §13.10] Modification approuvée → notifie le demandeur, OF éditable.
        $this->notifyModificationRequester($order, 'Modification OF approuvée',
            "Votre demande de modification sur OF {$order->number} est approuvée — vous pouvez éditer l'OF.");
    }

    /** Rejet à n'importe quelle étape en_attente — clôt la demande. */
    public function rejectModification(ProductionOrder $order, string $reason, ?User $user = null): void
    {
        $this->assertModificationPending($order);
        $order->update([
            'modification_status'     => 'refusee',
            'modification_dg_comment' => $reason,
        ]);

        // [CDC §13.10] Modification refusée → notifie le demandeur.
        $this->notifyModificationRequester($order, 'Modification OF refusée',
            "Votre demande de modification sur OF {$order->number} a été refusée : {$reason}");
    }

    private function notifyModificationStep(ProductionOrder $order, array $roles, string $title, string $message): void
    {
        ValidationStepNotification::sendToRoles(
            $roles, $title, $message,
            route('production.orders.show', $order),
            'ProductionOrder', $order->id,
            type: 'of_modification_step', icon: 'pencil-square', color: 'purple',
        );
    }

    private function notifyModificationRequester(ProductionOrder $order, string $title, string $message): void
    {
        $requester = $order->modification_requested_by ? User::find($order->modification_requested_by) : null;
        if (! $requester) {
            return;
        }
        $requester->notify(new ValidationStepNotification(
            $title, $message,
            route('production.orders.show', $order),
            'ProductionOrder', $order->id,
            type: 'of_modification_decided', icon: 'pencil-square', color: 'purple',
        ));
    }

    private function assertModificationPending(ProductionOrder $order): void
    {
        if ($order->modification_status !== 'en_attente') {
            throw ValidationException::withMessages(['status' => "Aucune demande de modification en attente (statut : {$order->modification_status})."]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function assertStatus(ProductionOrder $order, array|string $expected): void
    {
        $allowed = (array) $expected;
        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Transition invalide : l'OF doit être au statut « " . implode(' / ', $allowed) . " ».",
            ]);
        }
    }

    /** Recrée les lignes de détail (longueur × quantité → mètres). */
    private function syncLines(ProductionOrder $order, array $lines): void
    {
        foreach ($lines as $i => $row) {
            $length   = (float) ($row['length'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);
            if ($length <= 0 && $quantity <= 0) {
                continue;
            }
            $order->lines()->create([
                'length'       => $length,
                'quantity'     => $quantity,
                'total_meters' => round($length * $quantity, 2),
                'unit_id'      => $row['unit_id'] ?? null,
                'label'        => $row['label'] ?? null,
                'sort_order'   => $i,
            ]);
        }
    }

    /** Si des lignes existent, la quantité demandée = somme des quantités. */
    private function recomputeQuantities(ProductionOrder $order): void
    {
        $order->load('lines');
        if ($order->lines->isNotEmpty()) {
            $order->update(['quantity_requested' => $order->lines->sum('quantity')]);
        }
    }
}
