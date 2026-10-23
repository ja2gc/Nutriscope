# Backup and recovery maintenance

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: distinguish stored backup objects from evidence that the system can recover.

## Current owners

- [Backup services](../../backend/app/Services/Backup) orchestrate capture, verification, manifests, protected objects, restoration and environment switching.
- [Spatie configuration](../../backend/config/backup.php) declares the MySQL archive source/destination and archive encryption. Its ordinary file include list is empty; inspect the separate protected-object pipeline before judging overall file coverage.
- [Application backup settings](../../backend/config/nutriscope-backups.php) declare queue, recovery-control tables, retention and restore-enabled gate.
- [Filesystem config](../../backend/config/filesystems.php) separates backups, private_uploads, public/s3 storage and report_cache.
- [bootstrap/app.php](../../backend/bootstrap/app.php) schedules backup dispatch, retention, monitoring, purge and recovery-test dispatch. Scheduler/worker state affects the result.

The presence of a bucket/archive, a verified database dump or a successful unit test does **not** establish complete recovery. A whole-server move is also not automatically covered by the application's record-restore scope.

## Before relying on a restore point

Inventory database revision/schema, required protected uploads, legacy file references, conversion originals, object IDs and encryption keys. Verify what the manifest actually includes; inspect exclusions. Report cache is reproducible and separate from durable records. Confirm object bytes exist and can be read with authorized access, not merely that DB rows mention them.

Rehearse with synthetic data in an isolated temporary target before enabling recovery. Check schema/relationship compatibility, file integrity/access, archive-password availability, resource limits and interruption behavior. Record the exact application revision, procedure, result and remaining gaps without including backup contents or patient data.

BACKUP_RESTORE_ENABLED remains false in the example until readiness is established. Preserve APP_KEY and passwords needed to decrypt historical backups. R2 credentials and archive encryption passwords are different secrets.

## Migration and incident handling

Use [turnover](turnover.md) for writer freeze, coherent DB+file capture and cutover. Exact database/volume restoration commands depend on the approved topology and recovery facts; don't copy unverified destructive instructions into production.

Never delete volumes, purge a bucket or run migrate:fresh to recover a live system. Stop writers, preserve evidence and choose a tested restore or forward fix. Restoring an older DB without matching files/keys can damage consistency.

Establish backup contents, artifact coverage, target relationships, restore capacity and a successful isolated drill before relying on recovery.

Related: [operations](../developers/operations/README.md), [platform contract](platform-requirements.md), [environment](../developers/operations/environment.md), [testing](../developers/platform/testing.md).
