<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'fss_user_id', 'name', 'cycle_days', 'is_active',
        'week_start_date', 'status', 'activation_date',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'activation_date' => 'date',
        'is_active'       => 'boolean',
    ];

    public function fss()
    {
        return $this->belongsTo(User::class, 'fss_user_id');
    }

    public function days()
    {
        return $this->hasMany(MenuCycleDay::class);
    }
}
