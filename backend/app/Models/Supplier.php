<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    use \App\Models\Concerns\HasPublicId;

    protected $fillable = ['name', 'category', 'contact', 'payment_terms', 'notes', 'address'];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

}

