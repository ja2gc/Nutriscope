<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'model', 'tokens_input', 'tokens_output',
        'tokens_total', 'endpoint',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
