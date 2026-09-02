# Phase 1.5 Storage and Recovery Design

Date: 2026-08-03

## Purpose

Harden NutriScope backups, private uploads, recovery, reports, and security before production relocation. Phase 1.5 stays provider-neutral. Phase 2 selects client-owned providers and implements provider-specific production switching.

## Approved decisions

- Admin initiates whole-system restoration. No Technical Operator website role exists.
- Restoration runs through protected queued jobs or commands and uses one temporary MySQL database.
- A successful restore removes newer data from production. That data remains only in the pre-restore safety snapshot.
- Safety snapshots expire 48 hours after the recovery reaches Completed, Failed, RolledBack, or Cancelled. Active recovery or rollback extends protection until a terminal status and starts the 48-hour clock then.
- The safety snapshot supports automatic rollback and Admin-initiated whole-system rollback during the 48-hour window. No review database, row merge, or arbitrary-record recovery exists.
- Reports show current important source data while preserving creation-time identity, `created_at`, branding, signatories, template version, and appearance version. `updated_at` records later content changes.
- Preview and download do not create, finalize, or mutate reports. Exact PDFs are preserved only by an existing explicit finalized, signed, submitted, or official-evidence workflow; Phase 1.5 adds no such workflow.

## Architecture

Laravel owns settings, workflow metadata, authorization, validation, orchestration, and audit events. Laravel filesystem disks provide storage neutrality. Small contracts exist only for database restore and production connection switching, which Laravel filesystem does not cover.

MySQL stores authoritative manifest metadata, checksums, object keys, and relationships. Each immutable manifest document stays beside its encrypted database archive in private backup object storage.

The selected single-Droplet deployment keeps primary private uploads on a persistent local Docker volume and stores encrypted backups plus protected upload copies in a private Cloudflare R2 bucket. Backup credentials access only that bucket. If primary uploads later move to object storage, their bucket and credentials must remain separate and must not permit backup deletion. Provider snapshots, object versioning, lifecycle rules, and bucket locks remain additional Phase 2 safeguards.

## Automatic schedules

`backup_schedule_settings` stores three booleans: daily, weekly, and monthly. All default to false. Only Admin can change them. Every change is validated and audited. Schedule settings do not use a state machine.

One coordinator targets 01:30 Asia/Manila and runs every ten minutes all day so it also supplies a scheduler heartbeat. For each enabled category it calculates the latest target at or before the current time: the latest 01:30 for daily, Sunday 01:30 for weekly, and first-of-month 01:30 for monthly. If today's, this Sunday's, or this month's candidate is still in the future, the calculation steps back one day, week, or month respectively. This recovers a missed weekly or monthly run without creating a burst of every historical daily backup after long downtime. Each category uses an idempotency key such as `daily:2026-08-03`, `weekly:2026-W31`, or `monthly:2026-08`. A unique database constraint plus queue uniqueness, `withoutOverlapping()`, and `onOneServer()` allow one logical run per category and period. Queue retries continue that same run and do not create another archive. An exhausted failed run stays visible for Admin action rather than causing automatic duplicate archives. One transaction claims every simultaneously missing category for one queued backup, so catch-up and coinciding schedules still create one archive and manifest.

Disabling a schedule stops future runs only. Existing restore points keep assigned retention. Disabling the final enabled schedule requires explicit confirmation. Manual backups use the same archive, manifest, and verification pipeline and remain independent from schedule settings. A manual backup has a fixed seven-day retention assignment before entering the existing 48-hour Recently Deleted flow: long enough for immediate operator use without adding a permanent fourth retention class. Keeping a deleted manual backup restores it for seven days from the Keep action, so repeated manual archives cannot become unbounded permanent records. “Keep backup” only rescues the archive; it never restores application data. A manual backup cannot satisfy an automatic daily, weekly, or monthly period; automatic period idempotency and retention remain schedule-derived.

Enabling any schedule requires safe checks for private backup storage, archive encryption, durable queue configuration, scheduler heartbeat, and a shared atomic-lock cache when multiple application instances are configured. The UI shows each enabled schedule's next due time or “Automatic backups are disabled.”

## Private uploaded files

A configurable `private_uploads` disk defaults to local private storage for development and uses S3-compatible storage in production.

A narrow `stored_objects` registry records immutable generated keys, disk class, purpose, detected MIME type, extension, bytes, SHA-256 checksum, original display name, and timestamps. Explicit nullable foreign keys from the existing domain tables link each file; no generic polymorphic file-owner framework is added. Domain models retain ownership and authorization. This registry is not a universal trash or record-recovery system.

Clinical documents, PO receipts/proofs, profile photos, private branding, and sensitive preserved report files use private storage. APIs return authorized application URLs, never provider URLs or object keys. Clinical downloads stream through Laravel so authorization and audit checks always run. Existing local paths and profile-photo data URLs receive bounded migration support; successful migration removes binary profile data from MySQL.

## Upload validation and images

Uploads use server-generated keys and extension allowlists. Validation checks detected MIME type, signature, decoded content, byte size, dimensions, and pixel count.

Ordinary profile and PO images are orientation-corrected, resized within configured bounds, stripped of GPS and unnecessary EXIF, and conservatively re-encoded. Clinical images preserve readable resolution: PNG stays lossless; JPEG uses high quality while being normalized to remove metadata. PDFs are validated but not converted. No JPEG, WebP, PNG, or PDF object is individually ZIP-wrapped. Checksums describe exact accepted bytes after normalization.

## File-aware restore points

Database archives remain encrypted MySQL-only ZIP files. Durable uploaded objects are not duplicated in each archive.

For every restore point, the backup job enumerates referenced stored objects. New or changed bytes are copied once under checksum-addressed protected keys. The immutable JSON manifest lists source object ID, protected key, size, SHA-256, purpose, and restore relationship. MySQL records manifest key, manifest checksum, object count, total bytes, and retention-category relationships.

One archive may hold daily, weekly, and monthly category relationships. Daily relationships expire after three days, weekly after two weeks, and monthly after three months; under the fixed schedule these retain the latest 3, 2, and 3 restore points. A shared archive remains until its last category relationship expires. The migration backfills existing automatic restore points from their current tier and expiry, so changing a toggle neither deletes nor reclassifies them. Protected file copies remain while any non-purged restore point references them. Purging removes only unreferenced copies after the 48-hour Recently Deleted window and active recovery protection.

## Verification and recovery tests

Every backup verifies archive existence and size, archive SHA-256, AES decryption, expected SQL entry, manifest checksum/schema, and each referenced object's existence, size, and checksum.

A daily coordinator checks for a verified restore point after 03:00 Asia/Manila and queues a recovery test only when no successful test exists in the preceding 30 days. The test restores the latest verified point into a disposable temporary MySQL database and always drops that database after recording the result. It runs schema, relational-integrity, application-boot, authentication-schema, role-definition, supported password-hash, manifest, object, and critical read-only workflow checks. Verification is non-mutating: it creates no users, credentials, sessions, fixtures, or modified business rows, and a recovery-test database can never be promoted. A unique job and scheduler overlap locks prevent concurrent tests. Sanitized results store timestamps and outcomes. The Admin page shows the latest successful recovery-test date. Raw SQL, credentials, passwords, archive paths, object keys, and checksums never reach the browser.

## Staged restoration

Recovery statuses are enum-backed with guarded transitions: Requested, Preparing, Checking, Ready, Switching, Completed, Failed, RolledBack, and Cancelled. No state-machine package is used.

1. Admin selects a verified restore point and reviews scope and newer-data loss.
2. Fresh authentication, exact confirmation, rate limiting, and auditing apply.
3. Queued orchestration protects the restore point and creates one current-system safety snapshot through the normal backup pipeline. If that snapshot does not verify, recovery stops before production changes.
4. The chosen archive restores into one temporary MySQL database.
5. Schema, relational integrity, application boot, authentication schema, role definitions, supported password-hash formats, critical read-only workflows, manifest, and matching uploads are checked without creating or changing records.
6. Preparation failure leaves production unchanged and marks Failed.
7. Once ready, NutriScope enters maintenance mode and invokes the environment-switching contract.
8. Basic health checks run against the restored system.
9. Success exits maintenance mode and marks Completed.
10. Failure switches back to the safety snapshot, verifies health, exits maintenance mode, and marks RolledBack.

Phase 1.5 implements orchestration, contracts, safe local/test behavior, status reporting, and failure handling. Phase 2 implements provider-specific production switching. Without a configured production switcher, recovery cannot advance beyond Ready.

External notification is queued when intervention remains necessary or recovery fails. Requests may be cancelled before Switching and become completed through successful orchestration.

## Reports

One authorized `POST` prepare operation creates a saved report identity when absent and refreshes important source-derived content when stale. The report page invokes it automatically before opening preview or download; there is no user-facing preparation control. A deterministic fingerprint of important source-derived data decides whether content is stale, avoiding table-specific refresh workflows. Saved metadata includes source identity, creation-time branding/signatories, template version, and appearance version. Preparation preserves `created_at` and identity; only `updated_at` changes after a content refresh. Preparation renders one temporary PDF with a 24-hour expiry on a private `report_cache` Laravel disk, keyed to the saved content/version hash. Production may place that prefix in primary private upload storage, but it is excluded from the stored-object registry, manifests, and backup retention.

After preparation, preview streams the prepared bytes and download streams those same bytes with attachment disposition. Both are read-only. Neither creates another report, changes timestamps, regenerates, or finalizes. If temporary bytes have expired, these endpoints return a preparation-required response and do not regenerate; the page repeats the authorized prepare operation. No Save, Regenerate, or Finalize button is added. Archive only hides an inactive saved report.

Legacy `reports.file_path` rows are classified during migration. A PDF moves to private durable storage only when independently existing data proves it is submitted, signed, finalized, or official evidence; `archived` status alone is not proof. Because no such explicit workflow currently exists, other legacy archived PDFs are reproducible cache: their rows retain identity/snapshot metadata, public bytes move to access-controlled private quarantine, and the old bytes are purged only after current preparation succeeds and a valid PDF is verified. Future preview/download uses prepared current bytes. Reproducible temporary PDFs never enter durable backup storage. Phase 1.5 adds no finalized-report workflow.

## Security and acceptance

- Client-owned accounts, MFA, recovery codes, and platform-managed secrets.
- Private buckets, least-privilege credentials, and separate upload/backup boundaries.
- Admin-only Form Requests/policies, rate limits, reauthentication, confirmation, maintenance mode, guarded workflow transitions, and audit coverage.
- Live database is never the first restoration target.
- Application credentials cannot change provider retention-lock configuration.

Tests cover all schedule combinations, default-off behavior, permissions, readiness, catch-up, overlap, retention, auditing, private-file access, upload validation, normalization, manifest deduplication, strong verification, file restoration, recovery transitions, maintenance timing, rollback, safety-snapshot expiry, report semantics, and privacy-safe APIs.

Completion requires reconciled operations docs, updated maintained flowchart, corrected Phase 2 handoff, focused and full verification, self-review, and a verified push to `main`.

## Standards basis

- [Laravel 13 filesystem](https://laravel.com/docs/13.x/filesystem) for private, provider-neutral disk access and temporary URLs where suitable.
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html) for allowlists, generated names, content checks, size limits, and storage outside public web paths.
- [Cloudflare R2 data security](https://developers.cloudflare.com/r2/reference/data-security/), [bucket locks](https://developers.cloudflare.com/r2/buckets/bucket-locks/), and [lifecycle rules](https://developers.cloudflare.com/r2/buckets/object-lifecycles/) as Phase 2 examples, not Phase 1.5 provider coupling.
