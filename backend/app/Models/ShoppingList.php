<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingList extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;

    protected $fillable = [
        'rnd_user_id', 'name', 'list_date', 'period_start', 'period_end',
        'days_span', 'list_type', 'status', 'total_served_population',
        'coverage_status', 'uncovered_dates',
    ];

    protected $casts = [
        'list_date'    => 'date',
        'period_start' => 'date',
        'period_end'   => 'date',
        'days_span'    => 'integer',
        'total_served_population' => 'integer',
        'uncovered_dates' => 'array',
    ];

    public function fss()
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function items()
    {
        return $this->hasMany(ShoppingListItem::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
