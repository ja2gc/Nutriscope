<?php

namespace App\Events;

use App\Models\PurchaseOrder;

class PurchaseOrderCompleted
{
    public function __construct(public PurchaseOrder $purchaseOrder) {}
}
