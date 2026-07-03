<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'causer_id' => ['nullable', 'string', 'exists:users,uuid'],
            'subject_type' => ['nullable', 'string'],
            'event' => ['nullable', 'string'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        if (! empty($data['causer_id'])) {
            $data['causer_id'] = User::idFromUuid($data['causer_id']);
        }

        $logs = Activity::with('causer')
            ->when($data['causer_id'] ?? null, fn ($query, $causerId) => $query->where('causer_id', $causerId))
            ->when($data['subject_type'] ?? null, fn ($query, $subjectType) => $query->where('subject_type', $subjectType))
            ->when($data['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($data['start'] ?? null, fn ($query, $start) => $query->whereDate('created_at', '>=', $start))
            ->when($data['end'] ?? null, fn ($query, $end) => $query->whereDate('created_at', '<=', $end))
            ->latest()
            ->paginate($data['per_page'] ?? 25);

        return AuditLogResource::collection($logs);
    }
}
