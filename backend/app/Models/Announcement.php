<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'category',
        'attachment',
        'pinned',
        'visibility',
        'audiences',
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'audiences' => 'array',
    ];

    /** @return list<string> */
    public function audienceRoles(): array
    {
        return $this->audiences ?? ($this->visibility === 'All' ? ['RND', 'FSS', 'Admin'] : [$this->visibility]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
