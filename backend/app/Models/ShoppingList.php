<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingList extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;

    protected $fillable = [
        'rnd_user_id', 'menu_cycle_id', 'name', 'list_date', 'period_start', 'period_end',
        'days_span', 'list_type', 'status',
    ];

    protected $casts = [
        'list_date'    => 'date',
        'period_start' => 'date',
        'period_end'   => 'date',
        'days_span'    => 'integer',
    ];

    public function fss()
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function menuCycle()
    {
        return $this->belongsTo(MenuCycle::class);
    }

    public function items()
    {
        return $this->hasMany(ShoppingListItem::class);
    }
}
