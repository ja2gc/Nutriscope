<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;

    protected $fillable = [
        'rnd_user_id', 'shopping_list_id', 'supplier_id', 'po_number', 'or_number',
        'order_date', 'received_date', 'total_amount', 'actual_budget_per_head_per_day',
        'status', 'lifecycle_status', 'converted_at', 'completed_at', 'archived_at',
        'receipt_image', 'notes',
    ];

    protected $casts = [
        'total_amount'  => 'decimal:2',
        'actual_budget_per_head_per_day' => 'decimal:2',
        'order_date'    => 'date',
        'received_date' => 'date',
        'converted_at'  => 'datetime',
        'completed_at'  => 'datetime',
        'archived_at'   => 'datetime',
    ];

    /** Single source of truth: total_amount is always the sum of its line items. */
    public function recalcTotal(): void
    {
        $this->total_amount = (float) $this->items()->sum('total_value');
        $this->save();
    }

    public function fss()
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shoppingList()
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function vendorGroups()
    {
        return $this->hasMany(PurchaseOrderVendorGroup::class);
    }

    public function attachments()
    {
        return $this->hasMany(PurchaseOrderAttachment::class);
    }

    public function programProjectActivity()
    {
        return $this->hasOne(ProgramProjectActivity::class);
    }
}
