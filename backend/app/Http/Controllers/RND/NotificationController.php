<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Announcement;
use App\Models\NcpRecord;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Notification source targets are addressed by their public uuid on the client
     * (deep-links open the announcement / PO / cycle by uuid), but source_id is a
     * bigint column holding the raw internal FK. Resolve each source to its uuid in
     * bulk (one query per module, no N+1) so the resource can expose source_uuid.
     */
    private const SOURCE_MODELS = [
        'announcements' => Announcement::class,
        'food_service' => PurchaseOrder::class,
        'ncp' => NcpRecord::class,
    ];

    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        $this->attachSourceUuids($notifications->getCollection());

        return NotificationResource::collection($notifications);
    }

    private function attachSourceUuids(Collection $notifications): void
    {
        foreach (self::SOURCE_MODELS as $module => $modelClass) {
            $ids = $notifications
                ->where('source_module', $module)
                ->pluck('source_id')
                ->filter()
                ->unique();

            if ($ids->isEmpty()) {
                continue;
            }

            $uuidById = $modelClass::whereIn('id', $ids)->pluck('uuid', 'id');

            $notifications
                ->where('source_module', $module)
                ->each(fn (Notification $n) => $n->source_uuid = $uuidById[$n->source_id] ?? null);
        }
    }

    public function unreadCount(): JsonResponse
    {
        $count = Notification::where('user_id', Auth::id())
            ->where('read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function read(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== Auth::id()) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $notification->update(['read' => true]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(): JsonResponse
    {
        Notification::where('user_id', Auth::id())->update(['read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
