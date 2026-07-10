<?php

namespace App\Events;

use App\Modules\Production\Models\ProductionOutput;
use Illuminate\Foundation\Events\Dispatchable;

// [Sync ERP] Déclaration de production enregistrée — produit fini entré en stock.
class ProductionDeclared
{
    use Dispatchable;

    public int $documentId;
    public string $documentType;
    public ?int $userId;
    public array $context = [];

    public function __construct(public ProductionOutput $output)
    {
        $this->documentId   = $output->id;
        $this->documentType = ProductionOutput::class;
        $this->userId       = auth()->id();
        $this->context      = [
            'production_order_id' => $output->production_order_id,
            'quantity'            => (float) $output->quantity,
        ];
    }
}
