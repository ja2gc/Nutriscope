<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealPrepLog extends Model
{
    use HasFactory;
    
    protected $fillable = ['fss_user_id', 'menu_cycle_day_id', 'prepared_quantity', 'status', 'notes'];

    protected $casts = [
        'prepared_quantity' => 'decimal:2',
    ];

    public function fssUser()
    {
        return $this->belongsTo(User::class, 'fss_user_id');
    }

    public function menuCycleDay()
    {
        return $this->belongsTo(MenuCycleDay::class);
    }

}

