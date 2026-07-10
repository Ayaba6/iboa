<?php

namespace App\Events;

use App\Modules\Production\Models\MachineMaintenance;
use Illuminate\Foundation\Events\Dispatchable;

// [Sync ERP] Intervention de maintenance clôturée — machine à nouveau disponible.
class MaintenanceInterventionClosed
{
    use Dispatchable;

    public int $documentId;
    public string $documentType;
    public ?int $userId;
    public array $context = [];

    public function __construct(public MachineMaintenance $intervention)
    {
        $this->documentId   = $intervention->id;
        $this->documentType = MachineMaintenance::class;
        $this->userId       = auth()->id();
        $this->context      = [
            'machine_id'       => $intervention->machine_id,
            'downtime_minutes' => (float) $intervention->downtime_minutes,
        ];
    }
}
