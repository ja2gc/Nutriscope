<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'category', 'contact', 'payment_terms', 'notes', 'address', 'additional_contacts', 'preferred_contact_id'];

    protected $casts = ['additional_contacts' => 'array'];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
