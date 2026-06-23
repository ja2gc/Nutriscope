<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single Standard Operating Procedure revision. Append-only: the latest row is
 * the current SOP; older rows form the history. {@see SopController}.
 */
class Sop extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'body', 'created_by',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
