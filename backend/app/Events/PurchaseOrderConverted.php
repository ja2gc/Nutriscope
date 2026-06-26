<?php

namespace App\Events;

use App\Models\PurchaseOrder;

class PurchaseOrderConverted
{
    public function __construct(public PurchaseOrder $purchaseOrder)
    {
    }
}
