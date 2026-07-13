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
        'depot_matiere_id','depot_qualite_id','responsable_atelier_id','operateur_prevu_id',
        'date_debut_prevue','date_fin_prevue','heure_debut_prevue','heure_fin_prevue',
        'temps_reglage','equipe_prevue','nb_operateurs','autoriser_cloture_partielle','autoriser_depassement_qte',
        'profil','largeur_totale','longueur_standard','unite_production','poids_par_metre','poids_theorique',
        'couleur_ral','revetement','tolerance_longueur','tolerance_epaisseur',
        // §13.2 CDC — Validation financière avant lancement OF
        'financial_authorization','financial_authorized_at','financial_authorized_by','financial_notes','payment_mode','payment_rate',
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
    ];

    public function depotProduitFini(): BelongsTo { return $this->belongsTo(Warehouse::class, 'depot_produit_fini_id'); }
    public function depotRebut(): BelongsTo { return $this->belongsTo(Warehouse::class, 'depot_rebut_id'); }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function billOfMaterial(): BelongsTo { return $this->belongsTo(BillOfMaterial::class); }
    public function productionLine(): BelongsTo { return $this->belongsTo(ProductionLine::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_id'); }
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
