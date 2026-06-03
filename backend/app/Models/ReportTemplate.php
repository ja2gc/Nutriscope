<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'type', 'name', 'blade_view', 'default_filters',
        'available_filters', 'description', 'is_active'
    ];

    protected $casts = [
        'default_filters' => 'array',
        'available_filters' => 'array',
        'is_active' => 'boolean',
    ];

}

