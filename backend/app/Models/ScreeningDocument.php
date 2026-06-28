<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreeningDocument extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'patient_id', 'ncp_record_id', 'assessment_id', 'type', 'file_path', 'original_name',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function ncpRecord()
    {
        return $this->belongsTo(NcpRecord::class);
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }
}

