<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OcrDocument extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id', 'assessment_id', 'file_path', 'extracted_text',
        'document_type', 'extraction_template_id', 'parsed_fields',
        'confidence_score', 'processing_time_ms', 'status'
    ];

    protected $casts = [
        'parsed_fields' => 'array',
        'confidence_score' => 'decimal:4',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

}

