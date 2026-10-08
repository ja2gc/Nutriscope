<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportArchiveSetting extends Model
{
    public const RETENTION = 'retention';

    protected $fillable = ['key', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function enabled(): bool
    {
        return (bool) (self::query()->where('key', self::RETENTION)->value('enabled') ?? false);
    }
}
