# Platform Portability and Backup Design

Date: 2026-07-31

## Purpose

Prepare NutriScope for relocation to an undecided hosting platform and add a safe, provider-neutral backup system. Phase 1 must be complete and locally testable without production hosting or object-storage credentials. Phase 2 will connect the prepared system to the selected providers, move production traffic, and prove recovery using real infrastructure.

## Design Goals

- Keep hosting and backup-provider choices open.
- Give nontechnical administrators a clear Backup & Recovery page.
- Store backups outside the application host through an S3-compatible private disk.
- Keep cost predictable with a small retention policy.
- Prevent accidental duplicate backups, unsafe deletion, secret exposure, and one-click production restores.
- Preserve the existing NutriScope visual language and authorization model.
- Keep deployment portable across a VPS or a managed container platform.
- Avoid infrastructure and application features that NutriScope does not currently need.

## Chosen Approach

Use Laravel's filesystem abstraction and an S3-compatible backup disk. Use `spatie/laravel-backup` for encrypted MySQL dump creation, storage integration, health checks, and backup events. Add a thin NutriScope layer for Admin-facing status, retention tiers, Recently Deleted behavior, auditing, and recovery requests.

Use separate runtime processes for:

- web requests;
- queued jobs, including backup creation;
- scheduled commands;
- one-time release tasks such as database migrations.

Do not hardcode a hosting vendor or backup-storage vendor. Production values will be supplied through environment variables in Phase 2.

## Phase 1 Scope

### Platform portability

- Keep development MySQL and Redis services available through the local Compose file.
- Make the production application definition usable with externally managed MySQL and Redis.
- Define separate backend web, queue-worker, and scheduler processes.
- Remove database migrations from ordinary web, worker, and scheduler container startup.
- Provide one explicit release command for migrations and Laravel cache preparation.
- Keep frontend-to-backend URLs configurable at build and runtime boundaries already used by the application.
- Document required health checks, process commands, environment variables, persistent services, DNS, and HTTPS responsibilities.
- Do not configure DNS, certificates, provider accounts, production databases, or production storage in Phase 1.

### Upload portability

- Add a dedicated configured disk for user-uploaded application files.
- Default that disk to the current public local disk for development.
- Allow Phase 2 to change the disk to an S3-compatible provider using environment values.
- Keep existing public-file behavior working locally.
- Do not migrate existing production uploads in Phase 1; the inspected deployment currently contains no material uploaded application files.

### Backup creation

- Create one encrypted, compressed logical MySQL backup each day.
- Run backup work on a dedicated queue so HTTP requests remain responsive.
- Use a unique queued job and an atomic overlap lock so only one backup can run at a time.
- Allow an Admin to request a manual backup through the same job.
- Rate-limit manual requests and disable the action while a backup is queued or running.
- Record only operational metadata in the application database; backup contents remain in private object storage.
- Never include `.env` files, credentials, Redis cache/session data, logs, temporary files, source-control data, or Docker images.
- Do not include uploaded files in every database archive. Uploaded-file protection is handled separately by the selected object-storage provider in Phase 2.

### Backup verification

A backup is successful only when:

- the backup command exits successfully;
- the destination object exists;
- its stored size is greater than zero;
- the stored object matches the backup record;
- the encrypted archive metadata is readable by the application.

Phase 1 tests these checks using fake storage and fake processes. Phase 2 must perform an actual download and restore into a temporary database before production backup status is accepted.

### Retention

Use a small grandfather-father-son policy:

- keep the latest three daily restore points;
- keep the latest two weekly restore points;
- keep the latest three monthly restore points;
- allow one physical backup to satisfy more than one tier;
- never delete the newest verified backup;
- never remove an older verified backup because a new backup failed;
- count manual backups within the same retention policy;
- show each backup's retention reason and expected expiry date.

Retention cleanup runs only after a newly created backup verifies successfully.

### Recently Deleted

- Admin deletion moves an eligible backup to Recently Deleted instead of immediately removing its object.
- The recovery window is 48 hours.
- Admin can select **Keep backup** during that window.
- A scheduled purge permanently removes expired Recently Deleted objects.
- The newest verified backup and queued, running, or recovery-requested backups cannot be deleted.
- There is no Admin-facing permanent-delete action.
- Provider lifecycle rules and immutability settings remain an independent Phase 2 safety layer.

### Recovery

The Admin interface does not overwrite the production database.

Admin can select **Request recovery**, choose a backup, state what failed, and confirm the request. The system records the request, alerts the technical maintainer, and marks the backup as protected from cleanup. A technical operator restores into a temporary database, verifies the restored application, and performs the production switch during Phase 2 or an incident.

This separates a simple nontechnical request from a destructive technical operation.

## Admin Experience

Add **Backups** to the Admin navigation between **Audit Logs** and **Help**. Route: `/admin/backups`. Page title: **Backup & Recovery**.

The page uses existing `PageHeader`, `Card`, `Button`, `Badge`, warm-neutral colors, green success, amber warning, red failure, and Lucide icons.

### Summary

Show:

- overall state: Healthy, Attention needed, or Failed;
- last successful backup;
- next automatic backup;
- protected database scope;
- backup-storage usage when available;
- last recovery-test date;
- a primary **Create backup now** action.

### Backup list

Use four simple sections:

- Available;
- In progress;
- Failed;
- Recently Deleted.

Each row shows creation time, automatic or manual source, size, state, retention reason, expiry, and available actions. Do not expose bucket paths, credentials, raw command output, SQL contents, patient information, or provider configuration.

### Feedback and accessibility

- Show real states: Queued, Running, Verifying, Completed, Failed.
- Poll while work is active; do not display fake percentages.
- Use `role="status"` or `aria-live` for progress and result feedback.
- Give plain-language errors and one next action.
- Keep touch targets at least 44 pixels.
- Require confirmation before deletion or recovery request.
- Prevent duplicate submissions.
- Preserve keyboard navigation and visible focus.

## Backend Components

Keep components small and single-purpose:

- `BackupRun`: operational record for a backup attempt and stored object.
- `RecoveryRequest`: non-destructive request linked to a protected backup.
- `CreateDatabaseBackup`: unique queued job controlling backup execution and status.
- `VerifyDatabaseBackup`: service validating the stored result.
- `ApplyBackupRetention`: service assigning retention tiers and moving expired records to Recently Deleted.
- `PurgeDeletedBackups`: scheduled command that removes objects after 48 hours.
- Admin controllers and Form Requests for listing, manual creation, deletion, keeping, and recovery requests.
- API resources returning privacy-safe status data.

Use dependency injection, Form Request authorization, Admin route middleware, atomic locks, bounded timeouts, retry/backoff, explicit failure handling, and configuration values accessed through `config()`.

Do not add Horizon, WebSockets, a generic workflow engine, a second application database, browser-side provider SDKs, or automated production restore.

## Data Model

### `backup_runs`

Store:

- identifier;
- state;
- source: automatic or manual;
- storage disk;
- private object identifier;
- stored byte size;
- checksum or provider integrity value when available;
- encryption status;
- requested-by Admin when manual;
- queued, started, completed, verified, deleted, recoverable-until, and purged timestamps;
- retention tier and expiry;
- sanitized failure code and message;
- timestamps.

Indexes support state, completion time, retention expiry, and recoverable-until queries.

### `recovery_requests`

Store:

- identifier;
- backup reference;
- requesting Admin;
- incident type;
- short operator note;
- state;
- requested and resolved timestamps;
- timestamps.

Do not store restored data, credentials, raw SQL, or provider secrets in either table.

## API

All endpoints remain under authenticated, active, Admin-only middleware.

- `GET /api/admin/backups`
- `POST /api/admin/backups`
- `DELETE /api/admin/backups/{backup}`
- `POST /api/admin/backups/{backup}/keep`
- `POST /api/admin/backups/{backup}/recovery-requests`

Mutating endpoints use dedicated Form Requests, authorization, rate limits where appropriate, idempotent state checks, and sanitized JSON errors.

## Scheduling

Use Asia/Manila application time:

- daily backup after normal low-activity hours;
- retention after a verified successful backup;
- Recently Deleted purge daily;
- backup health check daily.

Scheduled tasks use `withoutOverlapping()` and `onOneServer()`. The production environment must use a shared lock-capable cache when more than one application instance exists.

## Security

- Private backup bucket only.
- Encrypted backup archives; encryption secret supplied only through production secret storage.
- S3-compatible credentials scoped to the single backup bucket and minimum required actions.
- No production secret values in Git, logs, API responses, audit data, or frontend bundles.
- Admin-only actions and explicit audit events for create, failure, delete, keep, recovery request, and purge.
- Audit events contain identifiers and outcomes, not backup contents or credentials.
- Latest-good-backup protection enforced server-side.
- Production restoration remains operator-controlled.
- Client owns provider accounts, MFA, billing, and recovery codes in Phase 2.

## Error Handling

- Preflight failures create a failed run without attempting retention cleanup.
- Queue or process failures update the run, emit a sanitized audit event, and notify configured maintainers.
- Storage unavailability never deletes local metadata or older verified backups.
- Verification failure treats the object as unusable and prevents cleanup.
- Purge failure leaves the record recoverable and retries later.
- UI distinguishes authentication, authorization, temporary service failure, and backup failure.

## Testing

### Backend

- Admin authorization and non-Admin denial.
- Manual-backup rate limit and duplicate prevention.
- Queue dispatch and overlap protection.
- Successful, failed, and timed-out backup state transitions.
- Fake-storage verification.
- Retention selection for three daily, two weekly, and three monthly points.
- No duplicate physical objects for overlapping tiers.
- Latest verified backup protection.
- 48-hour delete, keep, and purge behavior.
- Recovery-request protection.
- Sanitized API and audit payloads.
- Scheduler registration and deployment-process contracts.
- Environment template contains names only, never real secrets.

### Frontend

- Admin navigation placement.
- Loading, empty, healthy, failed, and unavailable states.
- Manual-backup duplicate prevention and feedback.
- Delete confirmation, Recently Deleted, and Keep backup behavior.
- Recovery-request validation and feedback.
- Keyboard operation, status announcements, and responsive layout.

### Deployment

- Compose configuration renders successfully.
- Backend, worker, and scheduler use the same image with different commands.
- Ordinary process startup does not run migrations.
- Release command is explicit.
- Production build and existing test suites remain green.

## Phase 2 Boundary

Phase 2 will:

- select hosting, managed database/Redis, and backup-storage providers;
- create client-owned accounts and configure MFA;
- enter production environment values;
- configure bucket lifecycle, immutability, encryption, and billing alerts;
- provision web, worker, scheduler, database, and Redis services;
- migrate any required production data;
- run a real backup and temporary-database restore test;
- update Name.com DNS;
- provision platform-managed HTTPS or configure the selected reverse proxy;
- monitor the cutover and retire the old Droplet only after acceptance.

## Acceptance Criteria

Phase 1 is complete when:

- provider-neutral configuration is present and documented;
- web, worker, scheduler, and release responsibilities are separate;
- Admin can understand backup health and safely request supported actions;
- automatic and manual backup workflows pass local tests;
- retention and 48-hour recovery behavior pass deterministic tests;
- secrets and backup contents never reach the frontend or audit logs;
- production-provider credentials are not required for the test suite;
- no production relocation, DNS change, or live restore is falsely reported as complete.
