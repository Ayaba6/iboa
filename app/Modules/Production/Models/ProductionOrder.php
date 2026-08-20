<?php
namespace App\Modules\Production\Models;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** [PRODUCTION] Ordre de fabrication. */
class ProductionOrder extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasAttachments;

    /**
     * [MTS §2] ORIGINES de l'ordre de fabrication — source UNIQUE.
     *
     * Le référentiel était recopié à TROIS endroits, chacun avec sa graphie :
     * la règle de validation du contrôleur, le sélecteur du formulaire et celui
     * de la liste. Ajouter une origine obligeait à penser aux trois — et c'est
     * précisément la dérive d'une liste recopiée de mémoire qui a laissé les
     * statuts fournisseurs au féminin mettre deux indicateurs à zéro.
     *
     * `prevision` et `besoin_interne` complètent les cinq origines exigées par le
     * cahier des charges MTS : elles manquaient au référentiel, alors que le
     * cahier les cite explicitement comme déclencheurs de production pour stock.
     */
    public const ORIGIN_MANUEL         = 'manuel';
    public const ORIGIN_COMMANDE       = 'commande_client';
    public const ORIGIN_STOCK_MINIMUM  = 'stock_minimum';
    public const ORIGIN_MRP            = 'mrp';
    public const ORIGIN_PREVISION      = 'prevision';
    public const ORIGIN_BESOIN_INTERNE = 'besoin_interne';

    /** Libellés complets — formulaire de création, fiche OF. */
    public const ORIGIN_LABELS = [
        self::ORIGIN_MANUEL         => 'Manuel',
        self::ORIGIN_COMMANDE       => 'Commande client',
        self::ORIGIN_STOCK_MINIMUM  => 'Stock minimum',
        self::ORIGIN_MRP            => 'Planification MRP',
        self::ORIGIN_PREVISION      => 'Prévision de vente',
        self::ORIGIN_BESOIN_INTERNE => 'Besoin interne',
    ];

    /**
     * Libellés abrégés — colonne étroite de la liste. Volontairement distincts
     * des complets : « Cde client » tient dans la colonne, « Commande client »
     * non. Ce sont les CLÉS qui doivent rester uniques, pas les mots affichés.
     */
    public const ORIGIN_LABELS_SHORT = [
        self::ORIGIN_MANUEL         => 'Manuel',
        self::ORIGIN_COMMANDE       => 'Cde client',
        self::ORIGIN_STOCK_MINIMUM  => 'Stock mini',
        self::ORIGIN_MRP            => 'MRP',
        self::ORIGIN_PREVISION      => 'Prévision',
        self::ORIGIN_BESOIN_INTERNE => 'Besoin int.',
    ];

    /** @return list<string> */
    public static function origins(): array
    {
        return array_keys(self::ORIGIN_LABELS);
    }

    protected $fillable = [
        'company_id','fiscal_year_id','number','client_id','order_id','product_id','bill_of_material_id',
        'production_line_id','sheet_type','thickness','color','length','usable_width',
        'quantity_requested','quantity_produced','status','launched_at','finished_at','responsible_id','notes','created_by',
        // [SAGE parité] en-tête + paramètres de production
        'site_planification','site_production','numero_optimisation','prepa_fabrication','reference_of',
        'designation','mode_lancement','priorite','date_fabrication_prevue','date_lancement','heure_lancement','observation',
        'rendement_standard','taux_perte','depot_produit_fini_id','depot_rebut_id','controle_qualite_obligatoire',
        // [Audit création OF] entête étendue + caractéristiques tôle bac
        'of_type','origin','atelier','bom_version','routing_version',
        'bom_snapshot','bom_snapshot_sha256','routing_snapshot','routing_snapshot_sha256','snapshotted_at',
        'depot_matiere_id','depot_qualite_id','responsable_atelier_id','operateur_prevu_id',
        'date_debut_prevue','date_fin_prevue','heure_debut_prevue','heure_fin_prevue',
        'temps_reglage','equipe_prevue','nb_operateurs','autoriser_cloture_partielle','autoriser_depassement_qte',
        'order_item_id','nb_ondes','profil','largeur_totale','longueur_standard','unite_production','poids_par_metre','poids_theorique',
        'couleur_ral','revetement','tolerance_longueur','tolerance_epaisseur',
        // §13.2 CDC — Validation financière avant lancement OF
        'financial_authorization','financial_authorized_at','financial_authorized_by','financial_notes','payment_mode','payment_rate',
        // [BUG-A3-MTO-FIN-001] Portée de la dérogation : jusqu'à quand, sur quel montant non couvert
        'financial_authorization_expires_at','financial_authorization_unpaid',
        // §13.10 CDC — Modification OF exceptionnelle, workflow 4 étapes
        'modification_status','modification_reason',
        'modification_requested_at','modification_requested_by',
        'modification_chef_avis_at','modification_chef_avis_by','modification_chef_comment',
        'modification_commercial_avis_at','modification_commercial_avis_by','modification_commercial_comment',
        'modification_finance_avis_at','modification_finance_avis_by','modification_finance_comment',
        'modification_dg_approved_at','modification_dg_approved_by','modification_dg_comment',
        // [X3] Suspension d'OF
        'suspended_from','suspended_at',
    ];
    protected $casts = [
        'thickness'=>'decimal:2','length'=>'decimal:2','usable_width'=>'decimal:1',
        'quantity_requested'=>'decimal:2','quantity_produced'=>'decimal:2','launched_at'=>'date','finished_at'=>'date',
        'financial_authorized_at'=>'datetime','payment_rate'=>'decimal:2',
        'financial_authorization_expires_at'=>'date','financial_authorization_unpaid'=>'integer',
        'modification_requested_at'=>'datetime','modification_chef_avis_at'=>'datetime',
        'modification_commercial_avis_at'=>'datetime','modification_finance_avis_at'=>'datetime',
        'modification_dg_approved_at'=>'datetime',
        'date_fabrication_prevue'=>'date','date_lancement'=>'date',
        'rendement_standard'=>'decimal:4','taux_perte'=>'decimal:4',
        'controle_qualite_obligatoire'=>'boolean',
        'date_debut_prevue'=>'date','date_fin_prevue'=>'date',
        'autoriser_cloture_partielle'=>'boolean','autoriser_depassement_qte'=>'boolean',
        'largeur_totale'=>'decimal:1','longueur_standard'=>'decimal:2',
        'poids_par_metre'=>'decimal:3','poids_theorique'=>'decimal:2',
        'tolerance_longueur'=>'decimal:2','tolerance_epaisseur'=>'decimal:3','temps_reglage'=>'decimal:2',
        'suspended_at'=>'datetime',
        'bom_snapshot'=>'array','routing_snapshot'=>'array','snapshotted_at'=>'datetime',
    ];

    /**
     * Début planifié EFFECTIF, replis compris.
     *
     * Deux colonnes de début coexistent : `date_debut_prevue`, la notion fine
     * (seule accompagnée d'heures), et `date_fabrication_prevue`, l'héritée. La
     * fiche OF arbitre déjà en affichant « début ?? fabrication » ; on applique
     * la même règle partout, pour qu'un OF ancien apparaisse au planning.
     *
     * ACCESSEUR et non attribut posé de l'extérieur : le tableau d'ordonnancement
     * calculait ces deux valeurs puis les ÉCRIVAIT sur le modèle. Eloquent les
     * rangeait alors dans le sac d'attributs, marquant l'objet indéfiniment
     * modifié sur deux colonnes qui n'existent pas — tout `save()` ultérieur
     * échouait. Un accesseur ne salit rien.
     */
    public function getDebutEffectifAttribute(): ?\Carbon\Carbon
    {
        $date = $this->date_debut_prevue ?: $this->date_fabrication_prevue;

        return $date ? $this->instantDe($date, $this->heure_debut_prevue, '00:00') : null;
    }

    /** Fin planifiée effective. Sans fin déclarée, l'OF occupe la journée de son début. */
    public function getFinEffectifAttribute(): ?\Carbon\Carbon
    {
        if (! $this->date_fin_prevue) {
            // Mieux vaut une occupation d'un jour, franche, qu'un créneau ouvert
            // que rien ne pourrait jamais chevaucher.
            return $this->debut_effectif?->copy()->endOfDay();
        }

        return $this->instantDe($this->date_fin_prevue, $this->heure_fin_prevue, '23:59');
    }

    /** Assemble une date et une heure « HH:MM » en un instant. */
    private function instantDe(mixed $date, ?string $heure, string $defaut): \Carbon\Carbon
    {
        $h = $heure && preg_match('/^\d{1,2}:\d{2}/', $heure) ? substr($heure, 0, 5) : $defaut;

        return \Carbon\Carbon::parse(\Carbon\Carbon::parse($date)->toDateString().' '.$h);
    }

    public function depotProduitFini(): BelongsTo { return $this->belongsTo(Warehouse::class, 'depot_produit_fini_id'); }
    public function depotRebut(): BelongsTo { return $this->belongsTo(Warehouse::class, 'depot_rebut_id'); }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function billOfMaterial(): BelongsTo { return $this->belongsTo(BillOfMaterial::class); }
    public function productionLine(): BelongsTo { return $this->belongsTo(ProductionLine::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_id'); }
    /** [BUG-A3-MTO-FIN-001] Auteur de la dérogation financière — obligatoire pour qu'elle compte. */
    public function financialAuthorizedBy(): BelongsTo { return $this->belongsTo(User::class, 'financial_authorized_by'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(ProductionOrderLine::class); }
    public function consumptions(): HasMany { return $this->hasMany(ProductionConsumption::class); }
    public function outputs(): HasMany { return $this->hasMany(ProductionOutput::class); }
    public function wastes(): HasMany { return $this->hasMany(ProductionWaste::class); }
    public function qualityControls(): HasMany { return $this->hasMany(ProductionQualityControl::class); }
    public function cost(): HasOne { return $this->hasOne(ProductionCost::class); }
    public function reservations(): HasMany { return $this->hasMany(\App\Models\StockReservation::class); }
    public function timeLogs(): HasMany { return $this->hasMany(ProductionTimeLog::class); }

    // §13.10 CDC — acteurs du circuit de modification exceptionnelle
    public function modificationRequestedBy(): BelongsTo  { return $this->belongsTo(User::class, 'modification_requested_by'); }
    public function modificationChefAvisBy(): BelongsTo    { return $this->belongsTo(User::class, 'modification_chef_avis_by'); }
    public function modificationCommercialAvisBy(): BelongsTo { return $this->belongsTo(User::class, 'modification_commercial_avis_by'); }
    public function modificationFinanceAvisBy(): BelongsTo { return $this->belongsTo(User::class, 'modification_finance_avis_by'); }
    public function modificationDgApprovedBy(): BelongsTo  { return $this->belongsTo(User::class, 'modification_dg_approved_by'); }
    public function operations(): HasMany { return $this->hasMany(ProductionOrderOperation::class)->orderBy('sequence'); }
    public function batches(): HasMany { return $this->hasMany(ProductionBatch::class); }

    public function isEditable(): bool { return in_array($this->status, ['brouillon','matiere_allouee','lance'], true); }
    public function isInProgress(): bool { return in_array($this->status, ['en_cours','termine_partiellement'], true); }

    /**
     * [§13.10 CDC] Un OF en_cours/termine_partiellement n'est éditable que si le
     * circuit de modification exceptionnelle (chef→commercial→finance→DG) a été
     * intégralement validé pour la demande en cours.
     */
    public function isEditableViaModification(): bool
    {
        return $this->isInProgress() && $this->modification_status === 'approuvee';
    }
    public function totalMeters(): float { return (float) $this->lines->sum('total_meters'); }
    public function statusLabel(): string { return match($this->status){'brouillon'=>'Brouillon','matiere_allouee'=>'Matière allouée','attente_chef'=>'En attente Chef Atelier','attente_responsable'=>'En attente Responsable Prod.','lance'=>'Lancé','en_cours'=>'En cours','termine_partiellement'=>'Terminé partiellement','termine'=>'Terminé','annule'=>'Annulé','suspendu'=>'Suspendu',default=>$this->status}; }
    public function statusColor(): string { return match($this->status){'brouillon'=>'gray','matiere_allouee'=>'amber','attente_chef'=>'orange','attente_responsable'=>'yellow','lance'=>'blue','en_cours'=>'sky','termine_partiellement'=>'teal','termine'=>'green','annule'=>'red','suspendu'=>'orange',default=>'gray'}; }
    public function isSuspendable(): bool { return in_array($this->status, ['lance', 'en_cours', 'termine_partiellement'], true); }

    /** [Dédoublonnage] OF actif dont la date de fin prévue est dépassée. */
    public function scopeEnRetard($q) { return $q->whereIn('status', ['lance', 'en_cours', 'termine_partiellement', 'suspendu'])->whereNotNull('date_fin_prevue')->whereDate('date_fin_prevue', '<', today()); }
    /** [Dédoublonnage] OF pas encore lancé (brouillon + circuit de validation). */
    public function scopeALancer($q) { return $q->whereIn('status', ['brouillon', 'matiere_allouee', 'attente_chef', 'attente_responsable']); }

    /** [PRO Respect programme] OF clôturés (terminés) sur la période, avec fin réelle. */
    public function scopeTermineEntre($q, $from, $to)
    {
        return $q->whereIn('status', ['termine', 'termine_partiellement'])
            ->whereNotNull('finished_at')->whereBetween('finished_at', [$from, $to]);
    }

    /**
     * [PRO Respect programme] Écart en jours entre la fin réelle et la fin prévue.
     * > 0 = retard, ≤ 0 = à l'heure/avance. Null si l'une des dates manque.
     */
    public function scheduleDelayDays(): ?int
    {
        if (! $this->finished_at || ! $this->date_fin_prevue) {
            return null;
        }
        return (int) $this->date_fin_prevue->startOfDay()->diffInDays($this->finished_at->startOfDay(), false);
    }

    /** OF clôturé dans les délais (fin réelle ≤ fin prévue). Null si non mesurable. */
    public function finishedOnTime(): ?bool
    {
        $d = $this->scheduleDelayDays();
        return $d === null ? null : $d <= 0;
    }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionOrderFactory::new();
    }
}
