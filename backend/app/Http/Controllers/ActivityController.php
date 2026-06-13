<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Per-record change history (Spec 5). Returns the audit timeline for one subject
 * model — who, when, what changed (values already PHI-redacted at write time for
 * clinical models). Authorization rides on the route group's role middleware.
 * Decision D: starts on the highest-value subjects; add methods as more surface.
 */
class ActivityController extends Controller
{
    public function inventory(Inventory $inventory): JsonResponse
    {
        return $this->history($inventory);
    }

    public function purchaseOrder(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->history($purchaseOrder);
    }

    public function patient(Patient $patient): JsonResponse
    {
        return $this->history($patient);
    }

    private function history(Model $subject): JsonResponse
    {
        $items = Activity::where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->with('causer')
            ->latest()->limit(100)->get()
            ->map(fn (Activity $a) => [
                'id'          => $a->id,
                'event'       => $a->event,
                'description' => $a->description,
                'subject_id'  => $a->subject_id,
                'causer'      => $a->causer?->name ?? 'system',
                'changes'     => [
                    'old' => $a->properties['old'] ?? [],
                    'new' => $a->properties['attributes'] ?? [],
                ],
                'created_at'  => $a->created_at,
            ]);

        return response()->json(['data' => $items]);
    }
}
