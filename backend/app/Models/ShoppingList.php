<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingList extends Model
{
    use AuditsChanges;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'rnd_user_id', 'name', 'list_date', 'period_start', 'period_end',
        'days_span', 'list_type', 'procurement_track', 'status', 'total_served_population',
        'estimate_population', 'estimate_population_updated_at',
        'coverage_status', 'uncovered_dates',
    ];

    public function isSupplies(): bool
    {
        return $this->procurement_track === 'supplies';
    }

    protected $casts = [
        'list_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'days_span' => 'integer',
        'total_served_population' => 'integer',
        'estimate_population' => 'integer',
        'estimate_population_updated_at' => 'datetime',
        'uncovered_dates' => 'array',
    ];

    protected function auditAttributes(): array
    {
        return [
            'rnd_user_id', 'name', 'list_date', 'period_start', 'period_end', 'days_span',
            'list_type', 'procurement_track', 'status', 'total_served_population',
            'estimate_population', 'estimate_population_updated_at', 'coverage_status',
            'uncovered_dates',
        ];
    }

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
