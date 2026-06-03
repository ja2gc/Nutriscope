<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'fss_user_id', 'name', 'list_date', 'period_start', 'period_end',
        'list_type', 'status',
    ];

    protected $casts = [
        'list_date'    => 'date',
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    public function fss()
    {
        return $this->belongsTo(User::class, 'fss_user_id');
    }

    public function items()
    {
        return $this->hasMany(ShoppingListItem::class);
    }
}
