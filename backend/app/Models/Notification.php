<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    use HasPublicId;
    use MassPrunable;

    public const ACTION_TYPES = ['po_awaiting_receipt', 'follow_up'];

    protected $fillable = [
        'user_id', 'title', 'message', 'type', 'source_module',
        'source_id', 'read', 'read_at', 'opened_at', 'resolved_at',
    ];

    protected $casts = [
        'read' => 'boolean',
        'read_at' => 'datetime',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        $cutoff = now()->subDays(3);

        return static::query()->where(function (Builder $query) use ($cutoff): void {
            $query->where(function (Builder $announcements) use ($cutoff): void {
                $announcements->where('type', 'announcement')
                    ->whereNotNull('opened_at')
                    ->where('opened_at', '<=', $cutoff);
            })->orWhere(function (Builder $actions) use ($cutoff): void {
                $actions->whereIn('type', self::ACTION_TYPES)
                    ->whereNotNull('resolved_at')
                    ->where('resolved_at', '<=', $cutoff);
            });
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
