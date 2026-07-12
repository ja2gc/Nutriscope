<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Models\ReportBranding;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\BrandingAssetStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The editable shared report header (Reports → Template Edit tab). Single row.
 */
class ReportBrandingController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BrandingAssetStorage $assetStorage,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => ReportBranding::singleton()]);
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

        $newAssets = [];
        $oldMoves = [];
        try {
            foreach (['logo_left', 'logo_right'] as $field) {
                if ($request->hasFile($field)) {
                    $newAssets[$field] = $this->assetStorage->store($request->file($field));
                    $data[$field.'_path'] = $newAssets[$field]['path'];
                }
                unset($data[$field]);
            }
            foreach ($newAssets as $field => $_asset) {
                $oldMoves[$field] = $this->assetStorage->quarantine($branding->getAttribute($field.'_path'));
            }

            $changedFields = collect(array_keys($data))
                ->filter(fn (string $field): bool => $branding->getAttribute($field) !== $data[$field])
                ->map(fn (string $field): string => match ($field) {
                    'logo_left_path' => 'logo_left',
                    'logo_right_path' => 'logo_right',
                    default => $field,
                })
                ->values()
                ->all();

            $this->audited(function () use ($branding, $data, $changedFields, $newAssets, $oldMoves): void {
                $branding->update($data);
                foreach ($newAssets as $asset) {
                    $this->assetStorage->releaseNew($asset['intent']);
                }
                foreach ($oldMoves as $move) {
                    if ($move !== null) {
                        $this->assetStorage->deleteAfterCommit($move);
                    }
                }
                if ($changedFields !== []) {
                    $this->auditLogger->record(
                        AuditAction::Updated,
                        AuditCategory::Operations,
                        AuditDomain::Reports,
                        subject: $branding,
                        details: ['changed_fields' => $changedFields, 'configuration' => 'branding'],
                    );
                }
            });
        } catch (\Throwable $exception) {
            foreach ($oldMoves as $move) {
                if ($move !== null) {
                    $this->assetStorage->restoreDurably($move);
                }
            }
            foreach ($newAssets as $asset) {
                $this->assetStorage->retry($asset['intent']);
            }
            throw $exception;
        }

        return response()->json(['data' => $branding->fresh()]);
    }
}
