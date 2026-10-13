<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\UpdateReportBrandingRequest;
use App\Models\ReportBranding;
use App\Models\ReportConfigurationRevision;
use App\Models\StoredObject;
use App\Services\Audit\AuditLogger;
use App\Services\StoredObjectStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportBrandingController extends Controller
{
    public function __construct(
        private readonly StoredObjectStorage $storedObjects,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->publicData(ReportBranding::singleton())]);
    }

    public function logo(string $side): StreamedResponse
    {
        abort_unless(in_array($side, ['left', 'right'], true), 404);

        $branding = ReportBranding::singleton();
        $object = $side === 'left'
            ? $branding->logoLeftObject()->first()
            : $branding->logoRightObject()->first();
        abort_if($object === null, 404);

        return $this->streamImage($this->storedObjects->readStream($object), $object->mime_type, $object->bytes);
    }

    public function update(UpdateReportBrandingRequest $request): JsonResponse
    {
        $branding = ReportBranding::singleton();
        $data = $request->validated();
        $newObjects = [];
        foreach (['logo_left', 'logo_right'] as $field) {
            if ($request->hasFile($field)) {
                $object = $this->storedObjects->storeUpload($request->file($field), 'branding');
                $newObjects[$field] = $object;
                $data[$field.'_stored_object_id'] = $object->id;
                $data[$field.'_path'] = null;
            }
            unset($data[$field]);
        }
        try {
            $branding->fill($data);
            $changedFields = array_map(
                fn (string $field): string => str_starts_with($field, 'logo_left_')
                    ? 'logo_left'
                    : (str_starts_with($field, 'logo_right_') ? 'logo_right' : $field),
                array_keys($branding->getDirty()),
            );
            if ($changedFields !== []) {
                $this->audited(function () use ($branding, $changedFields): void {
                    $branding->save();
                    $this->auditLogger->recordMutation(
                        AuditAction::Updated,
                        AuditDomain::System,
                        $branding,
                        $changedFields,
                        ['configuration' => 'report_branding'],
                    );
                });
            }
        } catch (\Throwable $exception) {
            collect($newObjects)->each(fn ($object) => $this->storedObjects->deleteOrQueue($object));
            throw $exception;
        }

        return response()->json(['data' => $this->publicData($branding->fresh())]);
    }

    public function history(PaginatedRequest $request): JsonResponse
    {
        $history = ReportConfigurationRevision::query()
            ->where('kind', 'branding')
            ->with('editor:id,name,first_name,last_name')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($request->perPage());

        return response()->json([
            'data' => $history->getCollection()->map(function (ReportConfigurationRevision $revision): array {
                $snapshot = $revision->snapshot ?? [];
                $leftObjectId = $snapshot['logo_left_stored_object_id'] ?? null;
                $rightObjectId = $snapshot['logo_right_stored_object_id'] ?? null;
                unset($snapshot['logo_left_stored_object_id'], $snapshot['logo_right_stored_object_id']);

                return [
                    'id' => $revision->uuid,
                    'created_at' => $revision->created_at?->toISOString(),
                    'origin' => $revision->origin,
                    'editor' => $revision->editor ? ['name' => $revision->editor->display_name] : null,
                    ...$snapshot,
                    'logo_left_path' => $leftObjectId ? 'private' : ($snapshot['logo_left_path'] ?? null),
                    'logo_right_path' => $rightObjectId ? 'private' : ($snapshot['logo_right_path'] ?? null),
                ];
            })->values(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'last_page' => $history->lastPage(),
            ],
        ]);
    }

    public function historyLogo(string $revision, string $side): StreamedResponse
    {
        abort_unless(in_array($side, ['left', 'right'], true), 404);
        $entry = ReportConfigurationRevision::query()
            ->where('uuid', $revision)->where('kind', 'branding')->firstOrFail();
        $path = $entry->snapshot["logo_{$side}_path"] ?? null;
        $objectId = $entry->snapshot["logo_{$side}_stored_object_id"] ?? null;

        if ($objectId !== null) {
            $object = StoredObject::query()->where('purpose', 'branding')->findOrFail($objectId);

            return $this->streamImage($this->storedObjects->readStream($object), $object->mime_type, $object->bytes);
        }

        abort_unless(is_string($path) && $path !== '' && ! str_contains($path, '..'), 404);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        abort_unless(str_starts_with($mime, 'image/'), 404);
        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 404);

        return $this->streamImage($stream, $mime, (int) $disk->size($path));
    }

    private function publicData(ReportBranding $branding): array
    {
        return [
            ...$branding->only(['id', 'hospital_name', 'address', 'accreditation', 'service_name', 'province', 'lgu']),
            'logo_left_path' => $branding->logo_left_stored_object_id ? 'private' : $branding->logo_left_path,
            'logo_right_path' => $branding->logo_right_stored_object_id ? 'private' : $branding->logo_right_path,
        ];
    }

    private function streamImage(mixed $stream, string $mime, int $bytes): StreamedResponse
    {
        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $bytes,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
