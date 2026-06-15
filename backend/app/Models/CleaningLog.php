<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleaningLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'fss_user_id',
        'item_name',
        'category',
        'status',
        'notes',
        'cleaned_at',
    ];

    protected $casts = [
        'cleaned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fss_user_id');
    }
}
