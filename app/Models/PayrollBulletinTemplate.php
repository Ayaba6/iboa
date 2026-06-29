<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasCompanyScope;

/**
 * Modèle de mise en page des bulletins de paie PDF.
 */
class PayrollBulletinTemplate extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'code', 'libelle', 'description',
        'header_text', 'footer_text',
        'show_logo', 'show_company_address', 'show_employee_photo',
        'show_net_a_payer_box', 'show_cumuls', 'show_conges_solde', 'show_cout_employeur',
        'paper_size', 'orientation', 'primary_color',
        'is_default', 'is_active', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'show_logo'             => 'boolean',
        'show_company_address'  => 'boolean',
        'show_employee_photo'   => 'boolean',
        'show_net_a_payer_box'  => 'boolean',
        'show_cumuls'           => 'boolean',
        'show_conges_solde'     => 'boolean',
        'show_cout_employeur'   => 'boolean',
        'is_default'            => 'boolean',
        'is_active'             => 'boolean',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** Bulletins utilisant ce modèle */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'template_id');
    }

    // ─── Accesseurs ───────────────────────────────────────────────────────────

    public function getPrimaryColorHexAttribute(): string
    {
        return match ($this->primary_color) {
            'indigo' => '#4f46e5',
            'blue'   => '#2563eb',
            'green'  => '#16a34a',
            'red'    => '#dc2626',
            'orange' => '#ea580c',
            'teal'   => '#0d9488',
            default  => '#6b7280',
        };
    }

    public function getPrimaryColorLabelAttribute(): string
    {
        return match ($this->primary_color) {
            'indigo' => 'Indigo',
            'blue'   => 'Bleu',
            'green'  => 'Vert',
            'red'    => 'Rouge',
            'orange' => 'Orange',
            'teal'   => 'Teal',
            default  => 'Gris',
        };
    }

    /**
     * Retourne le nombre d'options d'affichage activées (pour résumé UI).
     */
    public function getActiveOptionsCountAttribute(): int
    {
        return collect([
            $this->show_logo, $this->show_company_address, $this->show_employee_photo,
            $this->show_net_a_payer_box, $this->show_cumuls,
            $this->show_conges_solde, $this->show_cout_employeur,
        ])->filter()->count();
    }
}
