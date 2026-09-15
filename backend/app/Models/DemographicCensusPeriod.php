<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class DemographicCensusPeriod extends Model
{
    protected $fillable = [
        'period_start',
        'period_end',
        'census',
        'source_count',
        'frozen_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'census' => 'array',
            'source_count' => 'integer',
            'frozen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Completed demographic census periods are immutable.');
        });
    }
}
