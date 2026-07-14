<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditSetting extends Model
{
    public const RETENTION_ENABLED = 'retention_enabled';

    protected $fillable = ['key', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
