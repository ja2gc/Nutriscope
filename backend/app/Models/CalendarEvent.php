<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'type', 'source_module', 'source_id',
        'event_date', 'status', 'deletable',
    ];

    protected $casts = [
        'event_date' => 'date',
        'deletable' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
