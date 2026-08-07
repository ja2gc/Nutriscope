# Phase 1.5 Storage and Recovery Implementation Plan

**Goal:** Deliver private uploads, configurable automatic backup schedules, file-aware verified restore points, staged Admin recovery, corrected saved-report behavior, and reconciled operations documentation.

**Architecture:** Laravel owns settings, workflow metadata, authorization, and orchestration. Laravel filesystem disks own object I/O. Queued jobs perform backup and recovery work. A temporary MySQL database is always the first restoration target. Provider-specific production switching remains behind one contract until Phase 2.

**Working directories:** Run `php artisan`, Composer, and Pint commands from `backend/`. Run npm commands from `frontend/`.

**Dependency decision:** Use PHP GD and EXIF already installed by `backend/Dockerfile` for image decoding, orientation, resizing, metadata removal through re-encoding, and output. Add no image package. Local environments without these extensions reject normalization with a safe readiness error; tests that require them are skipped only when an extension is unavailable. Validate PDFs with Laravel/PHP file inspection, detected MIME, `%PDF-` header, `%%EOF` trailer, size limits, and non-inline authorized delivery; add no parser package in this phase.

**Task dependencies:** Tasks 1 and 2 establish backup creation and retention. Task 3 establishes durable private objects; Task 4 hardens their image inputs. Task 5 depends on Tasks 2–4. Task 6 depends on Task 5. Task 7 depends on Tasks 3–4 but not recovery switching. Task 8 follows behavior implementation, and Task 9 is the final gate.

## Task 1: Automatic backup settings and period-safe catch-up

**Create:**
- `backend/app/Models/BackupScheduleSetting.php`
- `backend/app/Models/BackupSchedulePeriod.php`
- `backend/app/Services/Backup/BackupReadiness.php`
- `backend/app/Services/Backup/DispatchDueBackups.php`
- `backend/app/Http/Requests/Admin/UpdateBackupSchedulesRequest.php`
- `backend/app/Http/Controllers/Admin/BackupScheduleController.php`
- `backend/database/migrations/2026_08_03_010001_create_backup_schedule_settings_table.php`
- `backend/database/migrations/2026_08_03_010002_create_backup_schedule_periods_table.php`
- `backend/tests/Feature/Backup/BackupScheduleSettingsTest.php`

**Modify:** `backend/bootstrap/app.php`, `backend/routes/api.php`, `backend/app/Models/BackupRun.php`, `backend/app/Jobs/CreateDatabaseBackup.php`, `backend/app/Http/Controllers/Admin/BackupController.php`, `backend/app/Http/Resources/BackupRunResource.php`, `backend/tests/Unit/BackupScheduleTest.php`, `frontend/types/backup.ts`, `frontend/services/backupService.ts`, `frontend/services/backupService.test.ts`, `frontend/app/admin/backups/page.tsx`, `frontend/components/backups/BackupStatusSummary.tsx`, `frontend/app/admin/backups/backup-page-contract.test.ts`.

Interfaces:
- `BackupScheduleSetting::current(): BackupScheduleSetting`
- `BackupReadiness::check(): array{storage:bool,encryption:bool,queue:bool,scheduler:bool,ready:bool}`
- `DispatchDueBackups::handle(CarbonImmutable $now): ?BackupRun`
- `BackupSchedulePeriod` unique key: `(category, period_key)`; fields `backup_run_id`, `expires_at`, and timestamps. Run state remains authoritative on `BackupRun` instead of being duplicated. The migration backfills current automatic rows from `retention_tier` and `retention_expires_at`.

TDD steps:

1. Add backend tests for all eight boolean combinations, default false, Admin authorization, final-disable confirmation, readiness failure, audit event, next-run output, period idempotency, catch-up, daily/weekly/monthly coincidence, multi-category expiry, and legacy retention backfill.
2. Run `php artisan test --compact tests/Feature/Backup/BackupScheduleSettingsTest.php tests/Unit/BackupScheduleTest.php`; expect failures for missing model/routes/coordinator.
3. Implement migrations, models, Form Request, readiness service, controller, and coordinator. Schedule `DispatchDueBackups::handle(now())` every ten minutes; it calculates the latest target at or before now, stepping back a day, week, or month when the current candidate has not reached 01:30. It claims all simultaneously due categories in one transaction and updates scheduler heartbeat even when all toggles are off. Use database uniqueness, queue uniqueness, `withoutOverlapping()`, and `onOneServer()`.
4. Run `php artisan test --compact tests/Feature/Backup/BackupScheduleSettingsTest.php tests/Unit/BackupScheduleTest.php`; expect pass.
5. Add frontend service/page tests, run `npm test -- backup-page-contract backupService`; expect missing schedule controls, then implement three toggles, readiness messages, final-disable dialog, and next-run labels.
6. Run `npm test -- backup-page-contract backupService`; expect pass.
7. Run `vendor/bin/pint --dirty --format agent` and commit: `feat: add automatic backup schedules`.

## Task 2: Explicit manual-backup retention

**Create:** `backend/tests/Feature/Backup/ManualBackupRetentionTest.php`.

**Modify:** `backend/config/nutriscope-backups.php`, `backend/app/Http/Controllers/Admin/BackupController.php`, `backend/app/Jobs/CreateDatabaseBackup.php`, `backend/app/Services/Backup/BackupRetentionService.php`, `backend/app/Models/BackupRun.php`.

Contract: manual backups use existing `retention_expires_at = verified_at + 7 days`; they never claim automatic period keys. Expired manual backups enter existing Recently Deleted for 48 hours. Keep sets a new `retention_expires_at = now() + 7 days`; purge remains unchanged.

1. Write tests for seven-day retention, no automatic-period satisfaction, Recently Deleted transition, Keep, and purge.
2. Run `php artisan test --compact tests/Feature/Backup/ManualBackupRetentionTest.php`; expect missing-field/behavior failures.
3. Add the fixed config and minimal metadata/retention branches.
4. Run `php artisan test --compact tests/Feature/Backup/ManualBackupRetentionTest.php tests/Feature/Backup/BackupRetentionServiceTest.php tests/Feature/Backup/PurgeDeletedBackupsTest.php tests/Feature/Backup/AdminBackupApiTest.php`; expect pass.
5. Run `vendor/bin/pint --dirty --format agent` and commit: `feat: bound manual backup retention`.

## Task 3: Private stored objects and authorized access

**Create:**
- `backend/app/Models/StoredObject.php`
- `backend/app/Services/StoredObjectStorage.php`
- `backend/app/Console/Commands/MigratePrivateStoredObjects.php`
- `backend/database/migrations/2026_08_03_010004_create_stored_objects_table.php`
- `backend/database/migrations/2026_08_03_010005_add_stored_object_references.php`
- `backend/tests/Feature/PrivateStoredObjectTest.php`

**Modify:** `backend/config/filesystems.php`, `backend/.env.example`, `backend/.env.production.example`, `backend/app/Services/ClinicalDocumentStorage.php`, `backend/app/Services/FSS/PurchaseOrderAttachmentStorage.php`, `backend/app/Http/Controllers/RND/AssessmentController.php`, `backend/app/Http/Controllers/RND/ScreeningDocumentController.php`, `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`, `backend/app/Http/Controllers/Auth/AuthController.php`, `backend/app/Http/Resources/UserResource.php`, `backend/app/Models/User.php`, `backend/app/Models/ScreeningDocument.php`, `backend/app/Models/PurchaseOrderAttachment.php`, `backend/app/Models/Report.php`, `backend/app/Models/ReportBranding.php`, `backend/routes/api.php`, `backend/tests/Feature/ProfileTest.php`, `backend/tests/Feature/PoAttachmentUploadTest.php`, and `backend/tests/Feature/Audit/SharedRndClinicalAccessTest.php`.

Interfaces:
- `StoredObjectStorage::storeUpload(UploadedFile $file, string $purpose): StoredObject`
- `StoredObjectStorage::storeBytes(string $bytes, string $mime, string $extension, string $purpose, ?string $originalName): StoredObject`
- Immutable generated keys: `<purpose>/<uuid>.<detected-extension>`.
- Explicit columns: `users.profile_photo_stored_object_id`, `screening_documents.stored_object_id`, `purchase_order_attachments.stored_object_id`, `reports.official_file_stored_object_id`, `report_brandings.logo_left_stored_object_id`, and `report_brandings.logo_right_stored_object_id`.

1. Write failing tests for private defaults, SHA-256/size/MIME metadata, immutable generated keys, authorized streaming, forbidden cross-role access, traversal rejection, and no provider URL/key exposure.
2. Run `php artisan test --compact tests/Feature/PrivateStoredObjectTest.php`; expect missing-feature failures.
3. Implement one registry and Laravel-disk service. Add `private_uploads` local-private default and S3-compatible environment names separate from backup credentials. Add explicit `stored_object_id` foreign keys to users, screening documents, PO attachments, reports, and report branding.
4. Refactor clinical, PO, and profile storage through the service while retaining bounded reads for legacy paths/data URLs. Implement the idempotent command to copy a legacy item, verify its checksum, update its explicit foreign key, then delete old bytes only after commit. Existing domain controllers continue enforcing access; do not add a universal file endpoint.
5. Run `php artisan test --compact tests/Feature/PrivateStoredObjectTest.php tests/Feature/ProfileTest.php tests/Feature/PoAttachmentUploadTest.php tests/Feature/Audit/SharedRndClinicalAccessTest.php`; expect pass.
6. Run `vendor/bin/pint --dirty --format agent` and commit: `feat: protect durable uploaded files`.

## Task 4: Image validation and GD normalization

**Create:** `backend/app/Services/Uploads/ImageNormalizer.php`, `backend/tests/Unit/Uploads/ImageNormalizerTest.php`.

**Create:** `backend/config/uploads.php`.

**Modify:** `backend/app/Http/Requests/Auth/UpdateProfileRequest.php`, `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`, `backend/app/Http/Controllers/RND/AssessmentController.php`, `backend/app/Services/StoredObjectStorage.php`.

Interface: `ImageNormalizer::normalize(UploadedFile $file, string $purpose): array{bytes:string,mime:string,extension:string,width:int,height:int,sha256:string}`. Allowed purposes are existing constants `profile`, `purchase_order`, and `clinical`. Ordinary mode bounds profile to 1024px and PO images to 2560px. Clinical mode does not downscale images within a 4096px/40-megapixel ceiling and encodes JPEG at quality 95 or PNG losslessly.

1. Write tests for spoofed extension/MIME, invalid decode, size/dimension/pixel limits, generated extension, orientation, resize, and stripped EXIF. Guard GD-dependent cases with PHPUnit extension requirements.
2. Run `php artisan test --compact tests/Unit/Uploads/ImageNormalizerTest.php`; expect missing-class failures.
3. Implement GD decode/re-encode with JPEG/PNG/WebP allowlist. PDF validation remains separate and unchanged bytes are stored.
4. Run `php artisan test --compact tests/Unit/Uploads/ImageNormalizerTest.php tests/Feature/ProfileTest.php tests/Feature/PoAttachmentUploadTest.php tests/Feature/AttachmentFeatureTest.php`; expect pass.
5. Run `vendor/bin/pint --dirty --format agent` and commit: `feat: harden uploaded images`.

## Task 5: Immutable manifests, incremental protected objects, and archive verification

**Create:**
- `backend/app/Models/BackupManifest.php`
- `backend/app/Models/BackupManifestObject.php`
- `backend/app/Services/Backup/ProtectedObjectStore.php`
- `backend/app/Services/Backup/BackupManifestService.php`
- `backend/app/Services/Backup/BackupArchiveInspector.php`
- `backend/database/migrations/2026_08_03_010006_create_backup_manifests_table.php`
- `backend/database/migrations/2026_08_03_010007_create_backup_manifest_objects_table.php`
- `backend/database/migrations/2026_08_03_010008_add_manifest_to_backup_runs_table.php`
- `backend/tests/Feature/Backup/FileAwareBackupTest.php`
- `backend/tests/Unit/Backup/BackupArchiveInspectorTest.php`

**Modify:** `backend/app/Jobs/CreateDatabaseBackup.php`, `backend/app/Services/Backup/BackupVerifier.php`, `backend/app/Services/Backup/BackupRetentionService.php`, `backend/app/Console/Commands/PurgeDeletedBackups.php`, `backend/app/Models/BackupRun.php`, `backend/app/Http/Resources/BackupRunResource.php`, `backend/database/factories/BackupRunFactory.php`, `backend/tests/Feature/Backup/CreateDatabaseBackupTest.php`, `backend/tests/Feature/Backup/BackupRetentionServiceTest.php`, `backend/tests/Feature/Backup/PurgeDeletedBackupsTest.php`, `backend/tests/Feature/Backup/BackupModelTest.php`, `backend/tests/Feature/Backup/AdminBackupApiTest.php`, `backend/tests/Unit/Backup/BackupVerifierTest.php`.

Interfaces:
- `ProtectedObjectStore::ensureProtected(StoredObject $object): array{key:string,bytes:int,sha256:string}`
- `BackupManifestService::create(BackupRun $run): BackupManifest`
- `BackupArchiveInspector::inspect(string $disk, string $key, string $password): array{bytes:int,sha256:string,sql_entry:string}`
- Manifest JSON version 1 contains exact protected keys, bytes, SHA-256, purpose, and stored-object UUID; object key is checksum-addressed.

1. Write failing tests for guarded `BackupRun::transitionTo(BackupState $state): void`, deduplicated unchanged objects, immutable manifest checksum, reference-aware purge, archive SHA-256, AES decryption failure, unsafe ZIP names, absent SQL, and object checksum mismatch.
2. Run `php artisan test --compact tests/Feature/Backup/FileAwareBackupTest.php tests/Unit/Backup/BackupArchiveInspectorTest.php`; expect missing-feature failures.
3. Implement the guarded model transition, models/services, and extensions to the existing backup job. Do not create a second backup pipeline or a state-machine package.
4. Run `php artisan test --compact tests/Feature/Backup/FileAwareBackupTest.php tests/Unit/Backup/BackupArchiveInspectorTest.php tests/Feature/Backup/CreateDatabaseBackupTest.php tests/Feature/Backup/BackupRetentionServiceTest.php tests/Feature/Backup/PurgeDeletedBackupsTest.php tests/Unit/Backup/BackupVerifierTest.php`; expect pass.
5. Run `vendor/bin/pint --dirty --format agent` and commit: `feat: verify file-aware restore points`.

## Task 6: Non-mutating recovery tests and staged restoration

**Create:**
- `backend/app/Enums/RecoveryStatus.php`
- `backend/app/Contracts/DatabaseRestoreManager.php`
- `backend/app/Contracts/EnvironmentSwitcher.php`
- `backend/app/Services/Backup/RecoveryVerifier.php`
- `backend/app/Services/Backup/UnsupportedEnvironmentSwitcher.php`
- `backend/app/Jobs/PrepareSystemRecovery.php`
- `backend/app/Jobs/RunBackupRecoveryTest.php`
- `backend/app/Models/RecoveryTest.php`
- `backend/app/Http/Requests/Admin/CancelRecoveryRequest.php`
- `backend/app/Http/Resources/RecoveryRequestResource.php`
- `backend/app/Notifications/RecoveryInterventionRequired.php`
- `backend/database/migrations/2026_08_03_010009_harden_recovery_requests_table.php`
- `backend/database/migrations/2026_08_03_010010_create_recovery_tests_table.php`
- `backend/tests/Feature/Backup/StagedRecoveryTest.php`.

**Modify:** `backend/bootstrap/app.php`, `backend/config/nutriscope-backups.php`, `backend/app/Enums/BackupSource.php`, `backend/app/Models/BackupRun.php`, `backend/app/Models/RecoveryRequest.php`, `backend/app/Http/Requests/Admin/CreateRecoveryRequest.php`, `backend/app/Http/Controllers/Admin/BackupRecoveryController.php`, `backend/routes/api.php`, `backend/app/Providers/AppServiceProvider.php`, `frontend/types/backup.ts`, `frontend/services/backupService.ts`, `frontend/app/admin/backups/page.tsx`, `frontend/components/backups/BackupRecoveryDialog.tsx`, `frontend/components/backups/BackupList.tsx`, `frontend/app/admin/backups/backup-page-contract.test.ts`, `frontend/services/backupService.test.ts`.

Interfaces:
- `DatabaseRestoreManager::restoreToTemporary(BackupRun $run, string $databaseName): array{name:string,disposable:bool,promotable:bool}`
- `EnvironmentSwitcher::available(): bool`, `switch(array $candidate): array{successful:bool,message:string}`, `rollback(string $safetySnapshotUuid): array{successful:bool,message:string}`
- `RecoveryVerifier::verify(array $database): array{passed:bool,checks:array<string,bool>}`; checks schema, foreign-key integrity, application boot, auth table/role definitions, and supported password-hash formats without mutations.

1. Write failing tests for allowed status transitions, fresh password confirmation, exact phrase, rate limiting, cancellation before Switching, temporary-DB-first order, one verified safety snapshot, matching-file verification, maintenance timing, health checks, automatic rollback, 48-hour expiry extension, 30-day recovery-test cadence, non-mutating checks, guaranteed temporary-database cleanup, external failure notification to the initiating Admin, audits, and safe API fields.
2. Run `php artisan test --compact tests/Feature/Backup/StagedRecoveryTest.php`; expect missing-feature failures. Run `npm test -- backup-page-contract backupService`; expect missing progress/cancellation behavior.
3. Implement enum, guarded `RecoveryRequest::transitionTo(RecoveryStatus $status): void`, contracts, jobs, and safe unsupported switcher. Schedule the recovery-test coordinator daily after 03:00; it dispatches the latest verified point only when the last successful test is more than 30 days old. The test database is disposable, never promotable, receives no fixture, and is dropped in a `finally` path.
4. Implement progress UI and latest successful recovery-test date.
5. Run `php artisan test --compact tests/Feature/Backup/StagedRecoveryTest.php tests/Feature/Backup/AdminBackupApiTest.php tests/Feature/OperationsAuditTest.php tests/Feature/Audit/SecurityAuditTest.php`; expect pass. Run `npm test -- backup-page-contract backupService`; expect pass.
6. Run `vendor/bin/pint --dirty --format agent` and commit: `feat: stage whole-system recovery`.

## Task 7: Authorized report preparation and legacy PDF retirement

**Create:** `backend/app/Actions/Reports/PrepareSavedReport.php`, `backend/app/Http/Requests/PrepareReportRequest.php`, `backend/database/migrations/2026_08_03_010011_add_saved_versions_to_reports_table.php`, `backend/tests/Feature/PreparedReportTest.php`, and `frontend/services/reportService.test.ts`.

**Modify:** `backend/config/filesystems.php`, `backend/app/Models/Report.php`, `backend/app/Models/ReportFileOperation.php`, `backend/app/Http/Resources/ReportResource.php`, `backend/app/Services/Reports/ReportService.php`, `backend/app/Services/Reports/ReportArchiveStorage.php`, `backend/app/Jobs/ProcessReportFileOperation.php`, `backend/app/Console/Commands/MigratePrivateStoredObjects.php`, `backend/app/Http/Controllers/ReportController.php`, `backend/routes/api.php`, `backend/tests/Feature/ReportControllerTest.php`, `backend/tests/Feature/ReportsBrowseTest.php`, `frontend/services/reportService.ts`, `frontend/components/reports/ReportsBrowser.tsx`, `frontend/components/ReportPreview.tsx`, `frontend/app/(rnd)/reports/page.test.ts`, `frontend/app/admin/reports/page.test.ts`.

Interface: `PrepareSavedReport::execute(User $actor, string $type, array $parameters): Report`. It creates identity once or refreshes stale source-derived content, preserves creation metadata, changes only `updated_at` on refresh, and writes a private temporary PDF keyed by the report's content/version hash. `POST /api/rnd/reports/{type}/prepare`, `POST /api/admin/reports/{type}/prepare`, and `POST /api/fss/reports/{type}/prepare` invoke it through the existing role groups. The page calls prepare automatically before preview/download; no button is added. Preview/download require a prepared report ID and matching temporary hash, stream identical bytes, and return HTTP 409 with `preparation_required` when bytes have expired. They never prepare or mutate through GET.

1. Write failing tests for prepare-once identity, creation metadata immutability, stale refresh, read-only preview/download, identical bytes, hide-only archive, and absence of Save/Regenerate/Finalize controls.
2. Run `php artisan test --compact tests/Feature/PreparedReportTest.php tests/Feature/ReportControllerTest.php`; expect missing preparation/read-only behavior. Run `npm test -- reportService "reports/page"`; expect missing automatic preparation behavior.
3. Implement preparation and private temporary rendering on a `report_cache` disk excluded from durable-object manifests. Extend the idempotent migration command to classify legacy `file_path` rows: copy a file to private durable storage only when existing data independently marks it as official; otherwise copy the public PDF to private quarantine, prepare and validate a current PDF, then clear `file_path` and create a finalized `ReportFileOperation` in one transaction. The queued operation deletes the public source and private quarantine after commit. A failed or unverifiable item remains tracked for retry. Add no finalized workflow.
4. Run `php artisan test --compact tests/Feature/PreparedReportTest.php tests/Feature/ReportControllerTest.php tests/Feature/ReportsBrowseTest.php`; expect pass. Run `npm test -- reportService "reports/page"`; expect pass.
5. Run `vendor/bin/pint --dirty --format agent` and commit: `fix: make report preparation explicit`.

## Task 8: Operations documentation and maintained flowchart

**Modify:** `docs/operations/platform-requirements.md`, `docs/operations/backup-recovery.md`, `docs/operations/phase-2-platform-handoff.md`, `docs/modules/Flowcharts/Backup and Recovery.md`, `backend/tests/Unit/PlatformOperationsDocumentationTest.php`, `backend/tests/Unit/ProductionDeploymentContractTest.php`.

1. Update documentation contract tests for disabled schedules, private uploads, manifests, non-mutating recovery checks, safety snapshot, report preparation, Phase 2 boundaries, and old-droplet acceptance.
2. Run `php artisan test --compact tests/Unit/PlatformOperationsDocumentationTest.php tests/Unit/ProductionDeploymentContractTest.php`; expect missing-text failures.
3. Reconcile documents and update only the existing Mermaid flowchart. Remove public-upload, Technical Operator, and external-manual-restore contradictions while preserving valid deployment requirements.
4. Re-run tests; expect pass.
5. Commit: `docs: reconcile storage and recovery operations`.

## Task 9: Verification, review corrections, and delivery

1. Run `vendor/bin/pint --dirty --format agent`.
2. Run `php artisan test --compact tests/Feature/Backup tests/Unit/Backup tests/Feature/PrivateStoredObjectTest.php tests/Unit/Uploads/ImageNormalizerTest.php tests/Feature/ProfileTest.php tests/Feature/PoAttachmentUploadTest.php tests/Feature/AttachmentFeatureTest.php tests/Feature/PreparedReportTest.php tests/Feature/ReportControllerTest.php tests/Feature/ReportsBrowseTest.php tests/Feature/OperationsAuditTest.php tests/Feature/Audit/SecurityAuditTest.php tests/Unit/PlatformOperationsDocumentationTest.php tests/Unit/ProductionDeploymentContractTest.php`; expect pass.
3. Run `php artisan test --compact` and `php composer.phar audit --locked` from `backend/`. Run `npm test`, `npm run lint`, `npx tsc --noEmit`, and `npm run build` from `frontend/`. Expect every command to exit 0.
4. From the repository root, run `docker compose -f docker-compose.yml config --quiet` and `docker compose -f docker-compose.prod.yml config --quiet`; expect exit code 0.
5. Review for secret exposure, browser leakage, authorization gaps, races, live-first restoration, provider coupling, dead code, duplication, and unnecessary abstractions. Add or update tests for every behavior-changing correction; documentation, formatting, and non-behavioral cleanup need no artificial regression test.
6. Run `git diff --check` and verify unrelated staged files remain untouched.
7. Commit neutral corrections: `chore: verify storage and recovery hardening`.
8. In the Phase 2 handoff, define the starting revision as the immutable `phase-1.5-complete` tag and the commit returned by `git rev-parse phase-1.5-complete^{commit}`. This avoids the impossible self-reference of writing a commit's own hash inside itself.
9. Commit the final reviewed files with neutral metadata, push `main`, verify `git rev-parse main` equals `git rev-parse origin/main`, create the neutral annotated tag `phase-1.5-complete` at that commit, push the tag, and verify the local and remote tag resolve to the same commit. Do not modify GitHub Actions.
