<?php

namespace App\Events;

use App\Models\DeliveryNote;
use Illuminate\Foundation\Events\Dispatchable;

// [Sync ERP] Bon de livraison validé — sorties stock effectuées.
class DeliveryNoteValidated
{
    use Dispatchable;

    public int $documentId;
    public string $documentType;
    public ?int $userId;
    public array $context = [];

    public function __construct(public DeliveryNote $deliveryNote)
    {
        $this->documentId   = $deliveryNote->id;
        $this->documentType = DeliveryNote::class;
        $this->userId       = auth()->id();
        $this->context      = ['order_id' => $deliveryNote->order_id];
    }
}
