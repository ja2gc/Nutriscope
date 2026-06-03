<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtractionTemplate extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'document_type', 'field_mappings', 'validation_rules',
        'version', 'is_active'
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'validation_rules' => 'array',
        'is_active' => 'boolean',
    ];

}

