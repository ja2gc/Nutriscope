<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Announcement\UpdateAnnouncementRequest;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Services\Audit\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->with('user:id,uuid,name,first_name,last_name,role')
            ->when($request->string('announcement_id')->toString(), fn ($query, $id) => $query->where('uuid', $id))
            ->when($user->role === 'RND', function ($query) use ($user) {
                $query->where(function ($nested) use ($user) {
                    $nested->where('visibility', 'All')
                        ->orWhere('user_id', $user->id);
                });
            })
            ->when($user->role === 'FSS', function ($query) {
                $query->whereIn('visibility', ['FSS', 'All']);
            })
            ->when($user->role === 'Admin', fn ($query) => $query)
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return AnnouncementResource::collection($announcements);
    }

    public function store(StoreAnnouncementRequest $request, NotificationService $notifications): JsonResponse
    {
        $user = $request->user();
        $data = $this->normalizeAttachments($request->validated());

        $announcement = $this->audited(function () use ($data, $user): Announcement {
            $announcement = Announcement::create([
                ...$data,
                'user_id' => $user->id,
                'pinned' => in_array($user->role, ['Admin', 'RND'], true) ? (bool) ($data['pinned'] ?? false) : false,
            ]);
            $this->auditLogger->recordMutation(
                AuditAction::Created,
                AuditDomain::System,
                $announcement,
                array_map(
                    fn (string $field): string => match ($field) {
                        'body' => 'content',
                        'attachment' => 'attachment',
                        default => $field,
                    },
                    array_keys($announcement->getAttributes()),
                ),
            );

            return $announcement;
        });

        // Trigger A (rnd.md §7) — fan out to users matching the announcement's visibility.
        $notifications->fanOutAnnouncement($announcement);

        return response()->json([
            'data' => new AnnouncementResource($announcement->load('user:id,uuid,name,first_name,last_name,role')),
        ], 201);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'Admin' && $announcement->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You can only edit your own announcements.'], 403);
        }

        $data = $this->normalizeAttachments($request->validated());

        if (! in_array($user->role, ['Admin', 'RND'], true)) {
            unset($data['pinned']);
        }

        $this->audited(function () use ($announcement, $data): void {
            $announcement->update($data);
            $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::System,
                $announcement,
                array_map(
                    fn (string $field): string => match ($field) {
                        'body' => 'content',
                        'attachment' => 'attachment',
                        default => $field,
                    },
                    array_keys($announcement->getChanges()),
                ),
            );
        });
        $announcement->load('user:id,uuid,name,first_name,last_name,role');

        return response()->json([
            'data' => new AnnouncementResource($announcement),
        ]);
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'Admin' && $announcement->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You can only delete your own announcements.'], 403);
        }

        $this->audited(function () use ($announcement): void {
            $announcement->delete();
            $this->auditLogger->recordMutation(AuditAction::Deleted, AuditDomain::System, $announcement, []);
        });

        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAttachments(array $data): array
    {
        if (! array_key_exists('attachments', $data)) {
            return $data;
        }

        $attachments = array_values(array_filter(
            $data['attachments'],
            fn (mixed $attachment): bool => is_string($attachment) && trim($attachment) !== ''
        ));

        $data['attachment'] = match (count($attachments)) {
            0 => null,
            1 => $attachments[0],
            default => json_encode($attachments, JSON_THROW_ON_ERROR),
        };

        unset($data['attachments']);

        return $data;
    }
}
