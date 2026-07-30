# Platform Portability and Backup Implementation Plan

**Execution note:** Implement task-by-task, test-first, with focused commits.

**Goal:** Deliver a locally verified, provider-neutral deployment layout and an Admin-friendly encrypted database-backup workflow that can be connected to selected production providers later.

**Architecture:** Laravel remains the authority for backup metadata, authorization, retention, recovery requests, and scheduling. `spatie/laravel-backup` creates encrypted MySQL archives on a configured Laravel disk; a small application layer tracks safe states and implements 3-daily, 2-weekly, 3-monthly retention plus a 48-hour Recently Deleted window. Docker uses the same backend image for web, worker, scheduler, and release commands without running migrations during ordinary process startup.

**Tech Stack:** PHP 8.4, Laravel 13, Sanctum, MySQL 8, Redis 8, Spatie Laravel Backup 10, Laravel Flysystem S3 adapter, Next.js 16, React 19, TypeScript, Vitest, PHPUnit 12, Docker Compose.

---

## File Map

### Deployment and configuration

- Modify `backend/Dockerfile`: install `mysqldump` client and keep one reusable runtime image.
- Modify `backend/docker-entrypoint.sh`: wait for MySQL and prepare caches without migrating.
- Create `backend/docker-release.sh`: explicit one-time migration/cache/link command.
- Modify `docker-compose.prod.yml`: define web, worker, scheduler, and release roles.
- Modify `backend/railway.toml`: remove migration from ordinary web startup.
- Modify `backend/.env.example` and `backend/.env.production.example`: document provider-neutral names only.
- Modify `backend/config/filesystems.php`: add `uploads` and private `backups` disks driven by config.
- Create `docs/operations/phase-2-platform-handoff.md`: provider-selection and cutover checklist.

### Backup backend

- Modify `backend/composer.json` and `backend/composer.lock`: install the production backup package and S3 adapter.
- Create `backend/config/backup.php`: database-only, encrypted backup-package configuration.
- Create `backend/config/nutriscope-backups.php`: application policy and alert configuration.
- Create migrations for `backup_runs` and `recovery_requests`.
- Create `BackupRun`, `RecoveryRequest`, and their factories.
- Create backup enums for state, source, retention tier, and incident type.
- Create `BackupArchiveRunner`, `BackupVerifier`, and `BackupRetentionService`.
- Create queued `CreateDatabaseBackup` job.
- Create `PurgeDeletedBackups` command.
- Create backup API resources, Form Requests, controllers, and routes.
- Create a queued maintainer notification.
- Modify scheduling, queue configuration, rate limiting, audit route inventory, and relevant audit enums.

### Backup frontend

- Create `frontend/types/backup.ts`.
- Create `frontend/services/backupService.ts`.
- Create focused components under `frontend/components/backups/`.
- Create `frontend/app/admin/backups/page.tsx`.
- Modify `frontend/components/layout/Sidebar.tsx`.

### Tests

- Create focused backend feature/unit tests for API authorization, workflow, retention, scheduling, deployment, and security.
- Create frontend service/component/page contract tests.
- Modify the existing production deployment contract so it no longer depends on outdated deployment documentation.

## Task 1: Make Runtime Processes Portable

**Files:**

- Modify: `backend/Dockerfile`
- Modify: `backend/docker-entrypoint.sh`
- Create: `backend/docker-release.sh`
- Modify: `docker-compose.prod.yml`
- Modify: `backend/railway.toml`
- Test: `backend/tests/Unit/ProductionDeploymentContractTest.php`

- [ ] Write failing deployment-contract tests asserting:

```php
$entrypoint = file_get_contents(base_path('docker-entrypoint.sh'));
$release = file_get_contents(base_path('docker-release.sh'));
$compose = file_get_contents(base_path('../docker-compose.prod.yml'));

$this->assertStringNotContainsString('migrate --force', $entrypoint);
$this->assertStringContainsString('migrate --force', $release);
$this->assertStringContainsString('backend_worker:', $compose);
$this->assertStringContainsString('queue:work redis --queue=backups,default', $compose);
$this->assertStringContainsString('backend_scheduler:', $compose);
$this->assertStringContainsString('schedule:work', $compose);
```

- [ ] Run:

```powershell
Set-Location backend
php artisan test --filter=ProductionDeploymentContractTest
```

Expected: failure because release, worker, and scheduler definitions do not exist.

- [ ] Install the MySQL client in `backend/Dockerfile`:

```dockerfile
apt-get update && apt-get install -y --no-install-recommends \
    default-mysql-client git curl zip unzip \
```

- [ ] Remove `php artisan migrate --force` from `backend/docker-entrypoint.sh`; retain database readiness, configuration cache, view cache, and `exec "$@"`.

- [ ] Create executable `backend/docker-release.sh`:

```bash
#!/bin/bash
set -e
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
```

- [ ] Add Compose services using the backend image:

```yaml
backend_worker:
  command: php artisan queue:work redis --queue=backups,default --sleep=3 --tries=3 --backoff=5 --timeout=900 --max-time=3600

backend_scheduler:
  command: php artisan schedule:work

backend_release:
  command: /usr/local/bin/docker-release.sh
  profiles: ["release"]
  restart: "no"
```

Remove fixed production `container_name` values so replicas are possible. Keep ports bound to loopback.

- [ ] Change Railway web `startCommand` to web startup only and add a release-command comment without pretending Railway process provisioning is complete.

- [ ] Run:

```powershell
docker compose -f docker-compose.yml -f docker-compose.prod.yml config --quiet
Set-Location backend
php artisan test --filter=ProductionDeploymentContractTest
```

Expected: both commands succeed.

- [ ] Commit only Task 1 files:

```powershell
git commit -m "build: separate production runtime roles"
```

## Task 2: Add Provider-Neutral Storage and Backup Package

**Files:**

- Modify: `backend/composer.json`
- Modify: `backend/composer.lock`
- Modify: `backend/config/filesystems.php`
- Create: `backend/config/backup.php`
- Create: `backend/config/nutriscope-backups.php`
- Modify: `backend/.env.example`
- Modify: `backend/.env.production.example`
- Test: `backend/tests/Unit/BackupConfigurationTest.php`

- [ ] Write a failing configuration test:

```php
#[Test]
public function backup_configuration_is_private_provider_neutral_and_database_only(): void
{
    $this->assertSame('backups', config('nutriscope-backups.disk'));
    $this->assertSame('public', config('filesystems.uploads'));
    $this->assertSame('s3', config('filesystems.disks.backups.driver'));
    $this->assertFalse(config('backup.backup.source.files.include'));
    $this->assertNotEmpty(config('backup.backup.password'));
}
```

- [ ] Run the test and confirm missing configuration failure.

- [ ] Install compatible packages:

```powershell
Set-Location backend
composer require spatie/laravel-backup:^10.3 league/flysystem-aws-s3-v3:^3.0 --with-all-dependencies
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag=backup-config
```

- [ ] Configure `filesystems.php`:

```php
'uploads' => env('UPLOADS_DISK', 'public'),
'disks' => [
    // existing disks
    'backups' => [
        'driver' => env('BACKUP_FILESYSTEM_DRIVER', 's3'),
        'key' => env('BACKUP_ACCESS_KEY_ID'),
        'secret' => env('BACKUP_SECRET_ACCESS_KEY'),
        'region' => env('BACKUP_DEFAULT_REGION', 'auto'),
        'bucket' => env('BACKUP_BUCKET'),
        'endpoint' => env('BACKUP_ENDPOINT'),
        'use_path_style_endpoint' => env('BACKUP_USE_PATH_STYLE_ENDPOINT', false),
        'visibility' => 'private',
        'throw' => true,
    ],
],
```

- [ ] Configure Spatie for MySQL only, the `backups` disk, and encrypted ZIP archives using `BACKUP_ARCHIVE_PASSWORD`. Exclude files by setting the source file include list to empty and use a distinct backup name.

- [ ] Create `nutriscope-backups.php` with immutable defaults:

```php
return [
    'disk' => env('BACKUP_DISK', 'backups'),
    'queue' => env('BACKUP_QUEUE', 'backups'),
    'daily_count' => 3,
    'weekly_count' => 2,
    'monthly_count' => 3,
    'recoverable_hours' => 48,
    'manual_rate_limit_per_hour' => 2,
    'alert_email' => env('BACKUP_ALERT_EMAIL'),
    'timezone' => env('BACKUP_TIMEZONE', 'Asia/Manila'),
];
```

- [ ] Add placeholder-free environment names with blank secret values. Never add real keys.

- [ ] Run configuration test and `composer audit`.

- [ ] Commit:

```powershell
git commit -m "build: add provider-neutral backup storage"
```

## Task 3: Add Backup Metadata Models

**Files:**

- Create: `backend/app/Enums/BackupState.php`
- Create: `backend/app/Enums/BackupSource.php`
- Create: `backend/app/Enums/BackupRetentionTier.php`
- Create: `backend/app/Enums/RecoveryIncidentType.php`
- Create: `backend/app/Models/BackupRun.php`
- Create: `backend/app/Models/RecoveryRequest.php`
- Create: `backend/database/factories/BackupRunFactory.php`
- Create: `backend/database/factories/RecoveryRequestFactory.php`
- Create: `backend/database/migrations/*_create_backup_runs_table.php`
- Create: `backend/database/migrations/*_create_recovery_requests_table.php`
- Test: `backend/tests/Feature/Backup/BackupModelTest.php`

- [ ] Write failing model tests for UUID route binding, casts, relationships, and protected states.

- [ ] Generate focused migrations:

```powershell
php artisan make:model BackupRun -mf
php artisan make:model RecoveryRequest -mf
```

- [ ] Create string-backed enums:

```php
enum BackupState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Verifying = 'verifying';
    case Completed = 'completed';
    case Failed = 'failed';
    case RecentlyDeleted = 'recently_deleted';
    case Purged = 'purged';
}
```

Add automatic/manual sources, daily/weekly/monthly retention tiers, and these recovery incident types: website unavailable, damaged database, accidentally deleted records, missing upload, and bad deployment.

- [ ] Create `backup_runs` with a UUID public identifier, state/source indexes, nullable requester FK, private object key, byte size, integrity value, encryption flag, sanitized failure fields, retention fields, and lifecycle timestamps.

- [ ] Create `recovery_requests` with UUID, backup/Admin FKs, incident type, bounded note, state, requested/resolved timestamps, and indexes.

- [ ] Define `$fillable`, casts, relationships, `HasPublicId`, and helpers:

```php
public function isProtectedFromDeletion(): bool
{
    return $this->state === BackupState::Completed
        && ($this->recoveryRequests()->where('state', 'requested')->exists()
            || static::verified()->latest('verified_at')->value('id') === $this->id);
}
```

- [ ] Run the focused model tests and migrations on the test database.

- [ ] Commit:

```powershell
git commit -m "feat: track backup and recovery states"
```

## Task 4: Implement Backup Execution and Verification

**Files:**

- Create: `backend/app/Contracts/BackupArchiveRunner.php`
- Create: `backend/app/Services/Backup/SpatieBackupArchiveRunner.php`
- Create: `backend/app/Services/Backup/BackupVerifier.php`
- Create: `backend/app/Jobs/CreateDatabaseBackup.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Modify: `backend/config/queue.php`
- Create: `backend/app/Notifications/BackupFailedNotification.php`
- Test: `backend/tests/Feature/Backup/CreateDatabaseBackupTest.php`
- Test: `backend/tests/Unit/Backup/BackupVerifierTest.php`

- [ ] Write failing tests using a fake runner and `Storage::fake('backups')` for success, empty-object failure, runner failure, duplicate-job lock, timeout state, and sanitized failure text.

- [ ] Define a boundary contract:

```php
interface BackupArchiveRunner
{
    public function runDatabaseOnly(): BackupArchiveResult;
}
```

`BackupArchiveResult` contains only private object key, bytes, integrity value, and encryption flag.

- [ ] Implement the Spatie adapter using the package command/events without exposing command output. Set explicit process and job timeouts.

- [ ] Implement `BackupVerifier`:

```php
public function verify(BackupArchiveResult $result): BackupArchiveResult
{
    $disk = Storage::disk(config('nutriscope-backups.disk'));
    throw_unless($disk->exists($result->objectKey), BackupVerificationFailed::class);
    $bytes = $disk->size($result->objectKey);
    throw_if($bytes < 1, BackupVerificationFailed::class);

    return $result->withBytes($bytes);
}
```

- [ ] Implement `CreateDatabaseBackup` as `ShouldQueue` and `ShouldBeUnique`, with queue name `backups`, 900-second timeout, retry/backoff, `WithoutOverlapping`, explicit `failed()`, state transitions, and failure notification when `BACKUP_ALERT_EMAIL` is configured.

- [ ] Ensure Redis `retry_after` exceeds 900 seconds.

- [ ] Bind the contract to the Spatie adapter in `AppServiceProvider`.

- [ ] Run focused tests and Pint.

- [ ] Commit:

```powershell
git commit -m "feat: create and verify database backups"
```

## Task 5: Implement Retention and Recently Deleted

**Files:**

- Create: `backend/app/Services/Backup/BackupRetentionService.php`
- Create: `backend/app/Console/Commands/PurgeDeletedBackups.php`
- Modify: `backend/bootstrap/app.php`
- Test: `backend/tests/Unit/Backup/BackupRetentionServiceTest.php`
- Test: `backend/tests/Feature/Backup/PurgeDeletedBackupsTest.php`
- Test: `backend/tests/Unit/BackupScheduleTest.php`

- [ ] Write deterministic tests with frozen time covering three latest days, two older week representatives, three older month representatives, overlapping tiers, manual backups, latest-good protection, failed-new-backup behavior, 48-hour purge, and purge failure.

- [ ] Implement retention selection by calendar bucket:

```php
$daily = $verified->take(3);
$weekly = $verified->reject->is($daily)
    ->groupBy(fn (BackupRun $run) => $run->verified_at->format('o-W'))
    ->map->first()->take(2);
$monthly = $verified->reject(fn ($run) => $daily->contains($run) || $weekly->contains($run))
    ->groupBy(fn (BackupRun $run) => $run->verified_at->format('Y-m'))
    ->map->first()->take(3);
```

Persist one highest-value tier per physical object; mark other eligible completed records Recently Deleted with `recoverable_until = now()->addHours(48)`.

- [ ] Implement purge to delete only expired Recently Deleted objects. On storage failure, leave object and metadata recoverable and report the exception.

- [ ] Schedule:

```php
$schedule->job(new CreateDatabaseBackup(BackupSource::Automatic))
    ->dailyAt('01:30')
    ->name('backups:create-daily')
    ->withoutOverlapping()
    ->onOneServer();

$schedule->command('backups:purge-deleted')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
```

- [ ] Run focused tests and `php artisan schedule:list`.

- [ ] Commit:

```powershell
git commit -m "feat: apply safe backup retention"
```

## Task 6: Add Secure Admin Backup API

**Files:**

- Create: `backend/app/Http/Resources/BackupRunResource.php`
- Create: `backend/app/Http/Requests/Admin/CreateBackupRequest.php`
- Create: `backend/app/Http/Requests/Admin/DeleteBackupRequest.php`
- Create: `backend/app/Http/Requests/Admin/KeepBackupRequest.php`
- Create: `backend/app/Http/Requests/Admin/CreateRecoveryRequest.php`
- Create: `backend/app/Http/Controllers/Admin/BackupController.php`
- Create: `backend/app/Http/Controllers/Admin/BackupRecoveryController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Modify: `backend/config/audit.php`
- Test: `backend/tests/Feature/Backup/AdminBackupApiTest.php`
- Test: `backend/tests/Feature/Audit/BackupAuditTest.php`

- [ ] Write failing API tests for Admin list/create/delete/keep/recovery, non-Admin 403, inactive 403, manual rate limiting, newest-backup protection, invalid state conflicts, note length, and privacy-safe JSON.

- [ ] Register a named limiter:

```php
RateLimiter::for('manual-backups', fn (Request $request) =>
    Limit::perHour(config('nutriscope-backups.manual_rate_limit_per_hour'))
        ->by('admin:'.$request->user()->getKey())
);
```

- [ ] Add Admin-only routes:

```php
Route::get('backups', [BackupController::class, 'index']);
Route::post('backups', [BackupController::class, 'store'])->middleware('throttle:manual-backups');
Route::delete('backups/{backupRun}', [BackupController::class, 'destroy']);
Route::post('backups/{backupRun}/keep', [BackupController::class, 'keep']);
Route::post('backups/{backupRun}/recovery-requests', BackupRecoveryController::class);
```

- [ ] Return only public UUID, state/source, timestamps, size, retention/expiry, safe failure summary, and allowed-action booleans. Never return disk, object key, checksum, credentials, or raw output.

- [ ] Use Form Request authorization and thin controllers delegating to services/actions.

- [ ] Add sanitized audit coverage using existing audit infrastructure and system domain. Include backup public UUID and outcome only.

- [ ] Run API and audit tests.

- [ ] Commit:

```powershell
git commit -m "feat: add secure backup administration API"
```

## Task 7: Build Nontechnical Admin UI

**Files:**

- Create: `frontend/types/backup.ts`
- Create: `frontend/services/backupService.ts`
- Create: `frontend/components/backups/BackupStatusSummary.tsx`
- Create: `frontend/components/backups/BackupList.tsx`
- Create: `frontend/components/backups/BackupActionDialog.tsx`
- Create: `frontend/components/backups/RecoveryRequestDialog.tsx`
- Create: `frontend/app/admin/backups/page.tsx`
- Modify: `frontend/components/layout/Sidebar.tsx`
- Test: `frontend/services/backupService.test.ts`
- Test: `frontend/components/backups/BackupPage.test.tsx`
- Test: `frontend/app/admin/backups/backup-page-contract.test.ts`

- [ ] Write failing frontend tests for response parsing, navigation position, loading/empty/failure states, active polling, disabled duplicate submission, confirmations, Recently Deleted keep action, recovery validation, status announcements, and no sensitive fields.

- [ ] Define exact DTO unions matching backend enums and allowed-action booleans.

- [ ] Implement service methods through `apiFetch` with safe error classes:

```ts
listBackups()
createBackup()
deleteBackup(id)
keepBackup(id)
requestRecovery(id, input)
```

- [ ] Build page using current `PageHeader`, `Card`, `Button`, `Badge`, `EmptyState`, and Lucide icons. Add **Backups** between **Audit Logs** and **Help**.

- [ ] Poll every five seconds only while a run is queued, running, or verifying. Stop polling when no work is active or component unmounts.

- [ ] Use plain labels:

```text
Healthy
Attention needed
Failed
Create backup now
Move to Recently Deleted
Keep backup
Request recovery
```

- [ ] Use accessible dialogs, focus restoration, 44-pixel targets, `role="status"` for progress, and `role="alert"` for failure.

- [ ] Run:

```powershell
Set-Location frontend
npm test -- BackupPage backup-page-contract backupService
npm run lint
npx tsc --noEmit
```

- [ ] Commit:

```powershell
git commit -m "feat: add admin backup recovery page"
```

## Task 8: Make Upload Storage Selectable

**Files:**

- Modify: backend services/controllers currently calling `Storage::disk('public')`
- Test: relevant existing attachment, report, branding, and profile-upload tests
- Create: `backend/tests/Unit/UploadDiskConfigurationTest.php`

- [ ] Inventory exact `Storage::disk('public')` consumers and classify true user uploads versus generated temporary/report artifacts.

- [ ] Write a failing contract test requiring true uploads to use:

```php
Storage::disk(config('filesystems.uploads'))
```

- [ ] Change only durable user-upload consumers to the configured uploads disk. Keep generated temporary files on local/private storage where required.

- [ ] Ensure URLs/downloads use Laravel disk APIs and do not assume `/storage` when the configured disk is S3-compatible.

- [ ] Run affected feature tests with both `Storage::fake('public')` and `Storage::fake('uploads')` configurations.

- [ ] Commit:

```powershell
git commit -m "refactor: make upload storage configurable"
```

## Task 9: Add Operations Documentation and Handoff

**Files:**

- Create: `docs/operations/platform-requirements.md`
- Create: `docs/operations/backup-recovery.md`
- Create: `docs/operations/phase-2-platform-handoff.md`
- Modify: `backend/tests/Unit/ProductionDeploymentContractTest.php`

- [ ] Write contract tests asserting documentation contains required process roles, environment-variable names, health paths, release command, DNS/HTTPS ownership, backup provider requirements, restore-test procedure, and explicit Phase 2 boundaries.

- [ ] Document provider-neutral requirements in plain language. Do not read or reuse the outdated `deployment.md`.

- [ ] Document recovery sequence:

```text
1. Protect selected backup.
2. Download to temporary operator environment.
3. Decrypt and restore into a new temporary MySQL database.
4. run migrations/status checks without modifying backup contents.
5. verify authentication and critical workflows.
6. schedule production cutover.
7. preserve old production database until acceptance.
```

- [ ] Add a ready-to-copy Phase 2 session prompt containing current commit, unfinished live-provider decisions, required credentials that the user must enter themselves, DNS facts to re-check, and verification criteria. Never include secret values.

- [ ] Run documentation contract tests.

- [ ] Commit:

```powershell
git commit -m "docs: add platform and recovery operations"
```

## Task 10: Full Verification and Neutral Repository Review

**Files:**

- Review all files changed by Tasks 1-9.

- [ ] Run backend formatting:

```powershell
Set-Location backend
vendor/bin/pint --dirty
```

- [ ] Run backend focused and full tests:

```powershell
php artisan test --filter=Backup
php artisan test
composer audit
```

- [ ] Run frontend checks:

```powershell
Set-Location frontend
npm test
npm run lint
npx tsc --noEmit
npm run build
```

- [ ] Run deployment checks:

```powershell
Set-Location ..
docker compose -f docker-compose.yml -f docker-compose.prod.yml config --quiet
docker compose -f docker-compose.yml -f docker-compose.prod.yml build backend frontend
```

- [ ] Inspect:

```powershell
git diff --check
git status --short
git diff --stat
```

Expected: clean project changes with existing unrelated workspace changes preserved.

- [ ] Review security invariants manually:

```text
No secret values committed.
No object key/checksum returned by API.
No Admin permanent-delete endpoint.
No automated production restore.
Newest verified backup cannot enter Recently Deleted.
Cleanup never runs after failed backup.
Provider selection remains open.
```

- [ ] Commit any verification-only corrections with a neutral message, then prepare completion report and Phase 2 handoff prompt.
