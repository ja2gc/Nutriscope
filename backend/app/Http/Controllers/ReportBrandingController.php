<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Models\ReportBranding;
use App\Services\Audit\AuditLogger;
use App\Services\StoredObjectStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportBrandingController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly StoredObjectStorage $storedObjects,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->publicData(ReportBranding::singleton())]);
    }

    public function update(Request $request): JsonResponse
    {
        $branding = ReportBranding::singleton();
        $this->auditLogger->assertAvailable();
        $data = $request->validate([
            'hospital_name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'accreditation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'province' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lgu' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo_left' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'logo_right' => ['sometimes', 'nullable', 'image', 'max:2048'],
        ]);
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
            $this->audited(function () use ($branding, $data): void {
                $branding->update($data);
                $this->auditLogger->record(
                    AuditAction::Updated,
                    AuditCategory::Operations,
                    AuditDomain::Reports,
                    subject: $branding,
                    details: ['changed_fields' => array_keys($data), 'configuration' => 'branding'],
                );
            });
        } catch (\Throwable $exception) {
            collect($newObjects)->each(fn ($object) => $this->storedObjects->deleteOrQueue($object));
            throw $exception;
        }

        return response()->json(['data' => $this->publicData($branding->fresh())]);
    }

    private function publicData(ReportBranding $branding): array
    {
        return [
            ...$branding->only(['id', 'hospital_name', 'address', 'accreditation', 'service_name', 'province', 'lgu']),
            'logo_left_path' => $branding->logo_left_stored_object_id ? 'private' : $branding->logo_left_path,
            'logo_right_path' => $branding->logo_right_stored_object_id ? 'private' : $branding->logo_right_path,
        ];
    }
}
