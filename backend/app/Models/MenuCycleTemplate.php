<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCycleTemplate extends Model
{
    protected $fillable = ['rnd_user_id', 'name', 'description', 'cycle_days'];

    protected $casts = ['cycle_days' => 'integer'];

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(MenuCycleTemplateDay::class, 'template_id');
    }
}
