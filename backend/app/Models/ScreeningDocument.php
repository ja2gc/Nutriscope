<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreeningDocument extends Model
{
    use AuditsChanges;
    use HasFactory;
    use HasPublicId;

    protected bool $auditRedactValues = true;

    protected $fillable = [
        'patient_id', 'ncp_record_id', 'assessment_id', 'type', 'file_path', 'original_name',
    ];

    protected function auditAttributes(): array
    {
        return ['patient_id', 'ncp_record_id', 'assessment_id', 'type'];
    }

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
