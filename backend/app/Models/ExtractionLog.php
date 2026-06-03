<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtractionLog extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'screening_document_id', 'ocr_document_id', 'source_type',
        'raw_text', 'parsed_fields', 'confidence_scores',
        'errors', 'processing_time_ms'
    ];

    protected $casts = [
        'parsed_fields' => 'array',
        'confidence_scores' => 'array',
        'errors' => 'array',
    ];

}

