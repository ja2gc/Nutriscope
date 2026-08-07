<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupScheduleSetting extends Model
{
    protected $fillable = ['daily', 'weekly', 'monthly'];

    protected $attributes = [
        'daily' => false,
        'weekly' => false,
        'monthly' => false,
    ];

    protected function casts(): array
    {
        return [
            'daily' => 'boolean',
            'weekly' => 'boolean',
            'monthly' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->findOrFail(1);
    }

    public function anyEnabled(): bool
    {
        return $this->daily || $this->weekly || $this->monthly;
    }
}
