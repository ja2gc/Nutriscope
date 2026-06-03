<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreeningDocument extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'patient_id', 'assessment_id', 'type', 'file_path',
        'extracted_data', 'mapped_fields', 'status',
        'confidence_score', 'reviewed_by', 'reviewed_at'
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'mapped_fields' => 'array',
        'confidence_score' => 'decimal:4',
        'reviewed_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

}

