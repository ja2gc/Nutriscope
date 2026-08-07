# Backup and recovery guide

This guide is for an Admin using NutriScope. Raw archives, storage keys, database credentials, and encryption secrets never enter the browser.

## Automatic backup settings

Open **Administration > Backup & Recovery**. Daily, weekly, and monthly schedules are independent and **disabled by default**, so demo deployments create no automatic backups. Admin may enable any combination after storage, encryption, queue, scheduler, and lock readiness checks pass.

- Daily keeps the latest **3 daily** restore points.
- Weekly keeps the latest **2 weekly** restore points.
- Monthly keeps the latest **3 monthly** restore points.

The target time is 01:30 Asia/Manila. A coordinator runs every ten minutes and catches up the latest missing due period after downtime. When categories coincide, one archive and manifest satisfy all due categories. Disabling a schedule stops future runs only; existing restore points keep assigned retention. Disabling the final active schedule requires confirmation and every setting change is audited. When all are off, the page displays **Automatic backups are disabled.**

Manual backups use the same encryption, manifest, protected-file, and verification pipeline. They do not satisfy an automatic period and expire after seven days before entering Recently Deleted. Manual creation and recovery remain separate from schedule toggles.

## What a restore point contains

Each restore point combines:

- one AES-encrypted MySQL-only archive;
- an immutable private manifest with stored-object relationships, sizes, and SHA-256 checksums; and
- incremental checksum-addressed copies of durable private uploads.

NutriScope verifies archive existence, size, SHA-256, decryption, the expected SQL dump, manifest schema/checksum, and every referenced protected object. It does not copy reproducible report-cache PDFs, `.env`, logs, Redis data, containers, or HTTPS keys.

At least every 30 days, NutriScope restores the latest verified point into a disposable MySQL database. Checks are read-only: schema, foreign keys, application/authentication tables, role definitions, supported password hashes, manifest, files, and critical boot integrity. The drill creates no user, password, session, or business fixture and always drops its database. The page shows the latest successful recovery-test date.

## Available and Recently Deleted

- **Create backup now** creates a manual seven-day restore point.
- **Move to Recently Deleted** starts a 48-hour recovery window.
- **Keep backup** rescues the archive and matching manifest/files; it does not restore application data.
- Expired Recently Deleted items are purged, including protected file copies no remaining restore point references.

## Whole-system restoration

Admin selects a verified restore point, reviews the newer-data loss window, enters the current password, and types the exact confirmation phrase. Newer production data will be discarded after switching and retained only in one access-controlled pre-restore safety snapshot.

Laravel queues the workflow:

1. Protect the selected point and create/verify one current-system safety snapshot.
2. Restore the selected archive into one new temporary MySQL database.
3. Run non-mutating database checks and verify every matching uploaded object.
4. Show **Ready** only after preparation passes. Production remains unchanged before this point.
5. Enter maintenance mode only when a configured environment switcher is ready.
6. Switch database/files, run basic health checks, and mark **Completed**.
7. On switching or health-check failure, automatically switch back to the safety snapshot and mark **Rolled Back**.

Statuses are Requested, Preparing, Checking, Ready, Switching, Completed, Failed, Rolled Back, and Cancelled. Admin may cancel before Switching. Phase 1.5 stops safely at Ready when no production switcher is configured; Phase 2 supplies provider-specific switching. Failures or required intervention notify the initiating Admin and all recovery actions are audited.

The safety snapshot remains protected until the workflow reaches a terminal status, then for 48 hours. During that window it can support automatic rollback or another Admin-initiated whole-system restoration. There is no Technical Operator website role, review database, individual-record merge, arbitrary-row recovery, or universal Record Trash.

## Saved reports

Opening a report runs one authorized automatic prepare operation. It creates the saved identity when absent and refreshes important source-derived content when changed. Preview and download then stream the same private prepared bytes without mutation. Downloading or printing only creates a local copy.

`created_at`, report identity, creation-time branding/signatories, template version, and appearance version remain fixed. `updated_at` changes only for later content changes. Archive hides an inactive saved report. Phase 1.5 adds no finalized-report workflow and retains no immutable PDF unless an independently existing signed, submitted, finalized, or official-evidence workflow requires it.
