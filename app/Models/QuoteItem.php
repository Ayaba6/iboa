<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    protected $table = 'quote_items';

    protected $fillable = [
        'quote_id',
        'product_id',
        'description',
        'unit_id',
        // [Ventes] Coût FIGÉ à la saisie — le CUMP de l'article bouge à chaque
        // réception, une marge recalculée plus tard ne serait pas celle que le
        // commercial a vue en négociant.
        'unit_cost',
        'quantity',
        'nb_toles',
        'metrage_par_tole',
        // [BUG-A3-MTO-TECH-010] Caractéristiques techniques DEMANDÉES par le
        // client. Elles ne vivaient que sur l'ordre de fabrication, où elles
        // étaient héritées de la nomenclature — donc de ce que l'atelier sait
        // faire, pas de ce que le client a commandé. Deux commandes du même
        // article en deux couleurs étaient indistinguables au niveau vente.
        'sheet_type', 'color', 'couleur_ral', 'revetement', 'profil', 'nb_ondes',
        'thickness', 'usable_width', 'largeur_totale',
        'tolerance_longueur', 'tolerance_epaisseur',
        'unit_price',
        'discount_percent',
        'tax_rate_id',
        'tax_rate_value',
        'line_total_ht',
        'line_tax',
        'line_total_ttc',
        'sort_order',
    ];

    protected $casts = [
        'quantity'         => 'decimal:4',
        'unit_price'       => 'integer',
        'discount_percent' => 'decimal:2',
        'tax_rate_value'   => 'decimal:2',
        'line_total_ht'    => 'integer',
        'line_tax'         => 'integer',
        'line_total_ttc'   => 'integer',
        'sort_order'       => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
