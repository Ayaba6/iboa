<?php
namespace App\Modules\Production\Models;
use App\Models\Company;
use App\Models\Employee;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionQualityControl extends Model
{
    // [BUG-A3-QUALITY-DELETE-006] Suppression LOGIQUE. La suppression physique
    // effaçait une décision qualité sans laisser d'empreinte, alors que toutes
    // les gardes lisent le dernier contrôle : retirer un `non_conforme` faisait
    // redevenir « dernier » un `conforme` antérieur, et la marchandise repartait.
    use HasFactory, HasCreator, HasCompanyScope, SoftDeletes;

    protected $fillable = ['company_id','production_order_id','thickness_ok','length_ok','color_ok','visual_ok','status','reason','rejected_quantity','controller_id','controlled_at','created_by','deletion_reason','deleted_by'];
    protected $casts = ['thickness_ok'=>'boolean','length_ok'=>'boolean','color_ok'=>'boolean','visual_ok'=>'boolean','rejected_quantity'=>'decimal:2','controlled_at'=>'date'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function controller(): BelongsTo { return $this->belongsTo(Employee::class, 'controller_id'); }
    public function statusLabel(): string { return match($this->status){'conforme'=>'Conforme','non_conforme'=>'Non conforme','a_reprendre'=>'À reprendre',default=>$this->status}; }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionQualityControlFactory::new();
    }
}
