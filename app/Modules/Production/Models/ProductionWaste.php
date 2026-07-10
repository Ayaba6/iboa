<?php
namespace App\Modules\Production\Models;
use App\Models\Company;
use App\Models\Employee;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionWaste extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;
    // §13.9 CDC — Causes codifiées des rebuts
    public const CAUSES = [
        'mauvaise_bobine'  => 'Mauvaise bobine (matière non conforme)',
        'mauvais_reglage'  => 'Mauvais réglage machine',
        'erreur_operateur' => 'Erreur opérateur',
        'panne_machine'    => 'Panne machine',
        'autre'            => 'Autre (voir observation)',
    ];

    protected $fillable = [
        'company_id','production_order_id','machine_id','operator_id',
        'type','quantity','weight','value','reason','created_by',
        // §13.9 CDC
        'cause','validated_by_chef','validated_by_quality','corrective_action',
    ];
    protected $casts = [
        'quantity'=>'decimal:2','weight'=>'decimal:2','value'=>'integer',
        'validated_by_chef'=>'boolean','validated_by_quality'=>'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function machine(): BelongsTo { return $this->belongsTo(ProductionMachine::class, 'machine_id'); }
    public function operator(): BelongsTo { return $this->belongsTo(Employee::class, 'operator_id'); }
    public function typeLabel(): string { return match($this->type){'reutilisable'=>'Réutilisable','non_reutilisable'=>'Non réutilisable','rebut'=>'Rebut',default=>$this->type}; }

    public function causeLabel(): string
    {
        return self::CAUSES[$this->cause] ?? ($this->cause ?? 'Non définie');
    }

    public function isFullyValidated(): bool
    {
        return $this->validated_by_chef && $this->validated_by_quality;
    }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionWasteFactory::new();
    }
}
