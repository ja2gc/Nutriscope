<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportFileOperation extends Model
{
    public const PHASE_ACQUISITION = 'acquisition';

    public const PHASE_FINALIZED = 'finalized';

    protected $fillable = ['asset_scope', 'operation', 'phase', 'available_at', 'original_path', 'quarantine_path', 'attempts', 'last_error_code'];

    protected function casts(): array
    {
        return ['available_at' => 'datetime'];
    }
}
