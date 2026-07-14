<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'type', 'name', 'blade_view', 'default_filters',
        'available_filters', 'signatories', 'description', 'is_active',
    ];

    protected $casts = [
        'default_filters' => 'array',
        'available_filters' => 'array',
        'signatories' => 'array',
        'is_active' => 'boolean',
    ];
}
