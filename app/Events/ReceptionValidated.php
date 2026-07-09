<?php

namespace App\Events;

use App\Models\Reception;
use Illuminate\Foundation\Events\Dispatchable;

// [Sync ERP] Réception fournisseur validée — stock entré, BC mis à jour.
class ReceptionValidated
{
    use Dispatchable;

    public int $documentId;
    public string $documentType;
    public ?int $userId;
    public array $context = [];

    public function __construct(public Reception $reception)
    {
        $this->documentId   = $reception->id;
        $this->documentType = Reception::class;
        $this->userId       = auth()->id();
        $this->context      = ['warehouse_id' => $reception->warehouse_id];
    }
}
