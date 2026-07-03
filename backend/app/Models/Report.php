<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;
    use \App\Models\Concerns\HasPublicId;

    protected $fillable = [
        'user_id', 'title', 'type', 'filters', 'parameters', 'snapshot',
        'file_path', 'status', 'generated_at', 'expires_at'
    ];

    protected $casts = [
        'filters' => 'array',
        'parameters' => 'array',
        'snapshot' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}

