<?php

namespace App\Events;

use App\Modules\Quality\Models\QualityInspection;
use Illuminate\Foundation\Events\Dispatchable;

// [Sync ERP] Contrôle qualité tranché (conforme / non conforme / partiel).
class QualityControlValidated
{
    use Dispatchable;

    public int $documentId;
    public string $documentType;
    public ?int $userId;
    public array $context = [];

    public function __construct(public QualityInspection $inspection)
    {
        $this->documentId   = $inspection->id;
        $this->documentType = QualityInspection::class;
        $this->userId       = auth()->id();
        $this->context      = ['status' => $inspection->status, 'lot_number' => $inspection->lot_number];
    }
}
