<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodServiceSetting extends Model
{
    protected $fillable = ['per_head_day_limit', 'updated_by'];

    protected $casts = [
        'per_head_day_limit' => 'decimal:2',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Single shared settings row — created on first access. */
    public static function singleton(): self
    {
        return static::firstOrCreate([], ['per_head_day_limit' => null]);
    }
}
