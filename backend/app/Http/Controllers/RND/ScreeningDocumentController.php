<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScreeningDocumentResource;
use App\Jobs\DeleteQuarantinedClinicalFile;
use App\Jobs\RestoreQuarantinedClinicalFile;
use App\Models\ScreeningDocument;
use App\Policies\AuditPolicy;
use App\Services\Audit\AuditLogger;
use App\Services\ClinicalDocumentStorage;
use Illuminate\Http\JsonResponse;

class ScreeningDocumentController extends Controller
{
    public function __construct(
        private readonly AuditPolicy $auditPolicy,
        private readonly AuditLogger $auditLogger,
        private readonly ClinicalDocumentStorage $documentStorage,
    ) {}

    public function show(ScreeningDocument $screeningDocument): ScreeningDocumentResource
    {
        $this->authorizeDocument($screeningDocument);
        $this->recordAccess(AuditAction::Viewed, $screeningDocument);

        return new ScreeningDocumentResource($screeningDocument);
    }

    public function file(ScreeningDocument $screeningDocument)
    {
        $this->authorizeDocument($screeningDocument);
        $path = $this->documentStorage->resolve($screeningDocument->file_path);
        $this->recordAccess(AuditAction::Downloaded, $screeningDocument);

        return response()->file($path);
    }

    /**
     * DELETE /api/rnd/screening-documents/{screeningDocument}
     */
    public function destroy(ScreeningDocument $screeningDocument): JsonResponse
    {
        $this->authorizeDocument($screeningDocument);
        $this->auditLogger->assertAvailable();
        $move = $this->documentStorage->quarantineIfPresent($screeningDocument->file_path);

        try {
            $this->audited(function () use ($screeningDocument, $move): void {
                $this->auditLogger->withoutModelEvents(function () use ($screeningDocument): void {
                    $screeningDocument->delete();
                });
                $this->recordAccess(AuditAction::Deleted, $screeningDocument);
                if ($move !== null) {
                    DeleteQuarantinedClinicalFile::dispatch($move['quarantine'])->afterCommit();
                }
            });
        } catch (\Throwable $exception) {
            if ($move !== null) {
                try {
                    $this->documentStorage->restore($move);
                } catch (\Throwable $restoreException) {
                    $failures = [$restoreException];
                    try {
                        RestoreQuarantinedClinicalFile::dispatch($move);
                    } catch (\Throwable $dispatchException) {
                        $failures[] = $dispatchException;
                    }
                    report(new \RuntimeException(
                        sprintf('Clinical file compensation encountered %d failure(s).', count($failures)),
                        previous: $failures[0],
                    ));
                }
            }
            throw $exception;
        }

        return response()->json(['message' => 'Attachment deleted.']);
    }

    private function authorizeDocument(ScreeningDocument $document): void
    {
        $allowed = $document->ncpRecord !== null
            ? $this->auditPolicy->viewNcpTrail(request()->user(), $document->ncpRecord)
            : ($document->patient !== null
                && $this->auditPolicy->viewPatientTrail(request()->user(), $document->patient));
        abort_unless($allowed, 403);
    }

    private function recordAccess(AuditAction $action, ScreeningDocument $document): void
    {
        $this->auditLogger->record(
            $action,
            AuditCategory::Clinical,
            AuditDomain::Ncp,
            subject: $document,
            context: $document->ncpRecord ?? $document->patient,
            details: ['status' => $action === AuditAction::Deleted ? 204 : 200],
        );
    }
}
