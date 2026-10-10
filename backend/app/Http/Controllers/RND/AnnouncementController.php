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
use App\Models\StoredObject;
use App\Services\Audit\AuditLogger;
use App\Services\NotificationService;
use App\Services\StoredObjectStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->with('user:id,uuid,name,first_name,last_name,role,profile_photo_stored_object_id')
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

    public function store(StoreAnnouncementRequest $request, NotificationService $notifications, StoredObjectStorage $objects): JsonResponse
    {
        $user = $request->user();
        [$data, $newObjects] = $this->storeAttachments($request->validated(), $objects);

        try {
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
        } catch (Throwable $exception) {
            $this->cleanupObjects($newObjects, $objects);
            throw $exception;
        }

        // Trigger A (rnd.md §7) — fan out to users matching the announcement's visibility.
        $notifications->fanOutAnnouncement($announcement);

        return response()->json([
            'data' => new AnnouncementResource($announcement->load('user:id,uuid,name,first_name,last_name,role,profile_photo_stored_object_id')),
        ], 201);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement, StoredObjectStorage $objects): JsonResponse
    {
        $user = $request->user();

        if ($announcement->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You can only edit your own announcements.'], 403);
        }

        $attachmentWasProvided = array_key_exists('attachments', $request->validated())
            || array_key_exists('attachment', $request->validated());
        $oldObjects = $attachmentWasProvided ? $this->storedAttachmentUuids($announcement->attachment) : [];
        [$data, $newObjects] = $this->storeAttachments($request->validated(), $objects);

        if (! in_array($user->role, ['Admin', 'RND'], true)) {
            unset($data['pinned']);
        }

        try {
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
        } catch (Throwable $exception) {
            $this->cleanupObjects($newObjects, $objects);
            throw $exception;
        }
        $this->deleteStoredObjects($oldObjects, $objects);
        $announcement->load('user:id,uuid,name,first_name,last_name,role,profile_photo_stored_object_id');

        return response()->json([
            'data' => new AnnouncementResource($announcement),
        ]);
    }

    public function destroy(Request $request, Announcement $announcement, StoredObjectStorage $objects): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'Admin' && $announcement->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You can only delete your own announcements.'], 403);
        }

        $oldObjects = $this->storedAttachmentUuids($announcement->attachment);
        $this->audited(function () use ($announcement): void {
            $announcement->delete();
            $this->auditLogger->recordMutation(AuditAction::Deleted, AuditDomain::System, $announcement, []);
        });
        $this->deleteStoredObjects($oldObjects, $objects);

        return response()->json(null, 204);
    }

    public function authorPhoto(Request $request, Announcement $announcement, StoredObjectStorage $objects): StreamedResponse
    {
        $user = $request->user();
        $visible = match ($user->role) {
            'Admin' => true,
            'FSS' => in_array($announcement->visibility, ['FSS', 'All'], true),
            'RND' => $announcement->visibility === 'All' || $announcement->user_id === $user->id,
            default => false,
        };
        abort_unless($visible, 404);

        $object = $announcement->user()->with('profilePhotoObject')->first()?->profilePhotoObject;
        abort_if($object === null, 404, 'Profile photo not found.');
        $stream = $objects->readStream($object);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $object->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeAttachments(array $data, StoredObjectStorage $objects): array
    {
        if (! array_key_exists('attachments', $data) && ! array_key_exists('attachment', $data)) {
            return [$data, []];
        }

        $attachments = array_key_exists('attachments', $data)
            ? array_values(array_filter($data['attachments'], fn (mixed $value): bool => is_string($value) && trim($value) !== ''))
            : (is_string($data['attachment'] ?? null) && $data['attachment'] !== '' ? [$data['attachment']] : []);
        unset($data['attachments']);

        $stored = [];
        try {
            foreach ($attachments as $index => $source) {
                preg_match('#\Adata:(image/(?:jpeg|png|webp));base64,(.*)\z#sD', $source, $matches);
                $bytes = base64_decode($matches[2] ?? '', true);
                if (! is_string($bytes) || $bytes === '') {
                    throw new \RuntimeException('Announcement image data is invalid.');
                }
                $stored[] = $objects->storeBytes($bytes, $matches[1], '', 'announcement', 'announcement-'.$index);
            }
        } catch (Throwable $exception) {
            $this->cleanupObjects($stored, $objects);
            throw $exception;
        }

        $data['attachment'] = $stored === [] ? null : json_encode([
            'version' => 1,
            'stored_object_uuids' => array_map(fn (StoredObject $object): string => $object->uuid, $stored),
        ], JSON_THROW_ON_ERROR);

        return [$data, $stored];
    }

    /** @return list<string> */
    private function storedAttachmentUuids(?string $attachment): array
    {
        $decoded = is_string($attachment) ? json_decode($attachment, true) : null;
        if (! is_array($decoded) || ($decoded['version'] ?? null) !== 1 || ! is_array($decoded['stored_object_uuids'] ?? null)) {
            return [];
        }

        return array_values(array_filter($decoded['stored_object_uuids'], 'is_string'));
    }

    /** @param list<StoredObject> $objects */
    private function cleanupObjects(array $objectsToDelete, StoredObjectStorage $objects): void
    {
        foreach ($objectsToDelete as $object) {
            $objects->deleteOrQueue($object);
        }
    }

    /** @param list<string> $uuids */
    private function deleteStoredObjects(array $uuids, StoredObjectStorage $objects): void
    {
        StoredObject::query()->where('purpose', 'announcement')->whereIn('uuid', $uuids)->get()
            ->each(fn (StoredObject $object) => $objects->deleteOrQueue($object));
    }
}
