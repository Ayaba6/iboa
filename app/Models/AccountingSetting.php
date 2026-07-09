<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// [Maquette X3] Paramètres comptables — singleton par société.
class AccountingSetting extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'code', 'referentiel', 'regime_fiscal', 'plan_comptable',
        'base_currency', 'fiscal_year_id', 'effective_date', 'status', 'comment',
        'account_client_collectif', 'account_fournisseur_collectif', 'account_ventes',
        'account_achats', 'account_tva_collectee', 'account_tva_deductible',
        'account_stock_mp', 'account_stock_pf', 'account_variation_stock',
        'account_caisse', 'account_banque',
        'auto_ecriture_vente', 'auto_ecriture_achat', 'auto_comptabilisation_stock',
        'validation_obligatoire', 'interdire_periode_cloturee', 'lettrage_auto',
        'rapprochement_actif', 'analytique_obligatoire',
        'section_analytique_obligatoire', 'centre_cout_defaut_id',
        'axe_analytique_1', 'axe_analytique_2', 'axe_analytique_3', 'created_by',
    ];

    protected $casts = [
        'effective_date'                 => 'date',
        'auto_ecriture_vente'            => 'boolean',
        'auto_ecriture_achat'            => 'boolean',
        'auto_comptabilisation_stock'    => 'boolean',
        'validation_obligatoire'         => 'boolean',
        'interdire_periode_cloturee'     => 'boolean',
        'lettrage_auto'                  => 'boolean',
        'rapprochement_actif'            => 'boolean',
        'analytique_obligatoire'         => 'boolean',
        'section_analytique_obligatoire' => 'boolean',
    ];

    /** Mapping clé métier AccountingService → colonne compte paramétré. */
    public const ACCOUNT_KEYS = [
        'clients'        => 'account_client_collectif',
        'fournisseurs'   => 'account_fournisseur_collectif',
        'ventes'         => 'account_ventes',
        'achats'         => 'account_achats',
        'tva_collectee'  => 'account_tva_collectee',
        'tva_deductible' => 'account_tva_deductible',
        'caisse'         => 'account_caisse',
        'banque'         => 'account_banque',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function centreCoutDefaut(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'centre_cout_defaut_id');
    }

    /** Instance société courante avec défauts. */
    public static function current(): self
    {
        $setting = static::firstOrCreate(
            ['company_id' => currentCompany()->id],
            ['fiscal_year_id' => currentCompany()->current_fiscal_year_id]
        );
        if ($setting->wasRecentlyCreated) {
            $setting->refresh();
        }

        return $setting;
    }

    /** Compte paramétré pour une clé métier, ou null si non défini (→ fallback plan). */
    public function accountIdFor(string $key): ?int
    {
        $col = self::ACCOUNT_KEYS[$key] ?? null;

        return $col ? $this->{$col} : null;
    }
}
