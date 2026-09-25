<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesSuggestionState extends Model
{
    protected $fillable = [
        'ncp_record_id',
        'fingerprint',
        'catalog_version',
        'validated_response',
        'dismissed_candidate_ids',
        'provider_metadata',
    ];

    protected $casts = [
        'validated_response' => 'array',
        'dismissed_candidate_ids' => 'array',
        'provider_metadata' => 'array',
    ];

    protected $attributes = [
        'dismissed_candidate_ids' => '[]',
    ];

    public function ncpRecord(): BelongsTo
    {
        return $this->belongsTo(NcpRecord::class);
    }
}
