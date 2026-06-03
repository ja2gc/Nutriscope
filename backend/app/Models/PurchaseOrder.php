<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'fss_user_id', 'shopping_list_id', 'supplier_id', 'po_number',
        'order_date', 'total_amount', 'status', 'receipt_image', 'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'order_date'   => 'date',
    ];

    public function fss()
    {
        return $this->belongsTo(User::class, 'fss_user_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
