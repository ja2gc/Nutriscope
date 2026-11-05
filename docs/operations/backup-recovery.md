# Backup and recovery maintenance

Document version: 1.0 | Issued: 5 November 2026

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

Related: [operations](../developers/operations/README.md), [platform contract](platform-requirements.md), [environment](environment.md), [testing](../developers/platform/testing.md).

## Manual recovery when the Admin screen is unavailable

Use this procedure when the application cannot open its backup/restore screens, the live database is damaged, or the operator must rebuild from an existing R2 restore point. Creating another backup does not repair damaged data: select a known-good point from before the incident. This is an operator recovery procedure, not an FSS/RND task.

**Target:** a separate, isolated Ubuntu recovery VPS with its own Docker engine and new empty volumes. Do not perform the import on a shared Docker engine or the live database. Keep the failed host and its data intact. No public DNS change, worker, scheduler or user traffic is permitted on the recovery target until verification and cutover approval.

Manual recovery is a full database import. Unlike the application's controlled recovery, it does not preserve newer live audit/recovery-control tables. Accounts, passwords, records, settings and historical control state revert to the chosen snapshot. Record and approve that data-loss window first.

### 1. Preserve the incident and collect prerequisites

1. Identify the incident owner and intended recovery point/time. Freeze writes on the failed system before final cutover, including mobile ingress, worker and scheduler, using [turnover](turnover.md#existing-system-migration-sequence). After blocking public web/mobile ingress and notifying users, the old host's operator can run `dc stop backend_scheduler backend_worker frontend backend` from its existing checkout with its own protected Compose shell loaded. This is state-changing: it stops the application, allows the configured worker grace period and leaves MySQL/Redis and their data in place. Check for other authorized writers. Keep an infrastructure snapshot/coherent copy of the failed database and volumes where possible; never erase them to make room for the restore.
2. Collect institution-controlled R2 access, the exact bucket/endpoint, original `APP_KEY`, the archive password used for the selected ZIP, compatible application source/images, and a separately initialized target database password. R2 credentials, archive password, Laravel key and target MySQL password are different values.
3. Identify the exact archive and its matching manifest from the private backup register or readable backup metadata. Sources are `backup_runs.object_key`, its archive hash, and the associated `backup_manifests` record/JSON. Do not assume two similarly timed R2 objects belong together. The manifest's `backup_uuid` must match the selected run. The archive is created before its manifest/final verification metadata, so its own completed backup row may not exist inside that SQL dump.
4. If metadata is lost, independently establish the pairing and restore completeness before accepting the result. A filename timestamp alone does not prove it. Missing archive passwords cannot be recovered from a new R2 token; missing protected bytes cannot be recreated from SQL.
5. Inventory legacy public/local uploads and generated artifacts outside `stored_objects`. The ordinary ZIP contains a database dump only; it is not a complete filesystem image. Recover additional required files from the coherent filesystem backup. Report cache can be regenerated, but that does not make every historical attachment reproducible.

Keep all paths, hashes and recovery records private. These instructions use placeholders rather than actual bucket paths or patient records.

### 2. Download the restore set from R2

In the institution's [Cloudflare dashboard](https://dash.cloudflare.com/), select **R2 Object Storage → the backup bucket → Objects**, open the chosen object and use **Download**. Download the selected database ZIP, its matching manifest JSON and each protected object named by that manifest into a restricted recovery directory. Keep the bucket private; do not enable public access or send signed download links through chat. See [R2 downloads](https://developers.cloudflare.com/r2/objects/download-objects/).

For a larger set, use an institution-approved AWS CLI installation on the trusted recovery host, following [Cloudflare's S3 CLI instructions](https://developers.cloudflare.com/r2/examples/aws/aws-cli/). Configure a dedicated profile with the backup bucket's read-capable S3 key, secret and region `auto`; it does not need deletion permission. Enter credentials through its private configuration prompts, not command arguments. Protect the resulting credential file and remove temporary access after recovery.

**State-changing locally; read-only against R2 — recovery host, operator, Bash:**

```sh
umask 077
RECOVERY_DIR=$(mktemp -d "$HOME/nutriscope-recovery.XXXXXXXX")
aws configure --profile nutriscope-recovery
read -rp 'Private R2 S3 endpoint: ' RECOVERY_ENDPOINT
read -rp 'Private bucket name: ' RECOVERY_BUCKET
read -rp 'Selected database ZIP object key: ' RECOVERY_ARCHIVE_KEY
read -rp 'Matching manifest JSON object key: ' RECOVERY_MANIFEST_KEY
aws --profile nutriscope-recovery --endpoint-url "$RECOVERY_ENDPOINT" s3 cp \
  "s3://$RECOVERY_BUCKET/$RECOVERY_ARCHIVE_KEY" "$RECOVERY_DIR/database.zip" --quiet
aws --profile nutriscope-recovery --endpoint-url "$RECOVERY_ENDPOINT" s3 cp \
  "s3://$RECOVERY_BUCKET/$RECOVERY_MANIFEST_KEY" "$RECOVERY_DIR/manifest.json" --quiet
```

Check each command's exit status before continuing. Record the download's archive checksum privately and compare it with independently retained `archive_sha256` when available. Computing a checksum for a download without a trusted expected value checks neither provenance nor completeness.

### 3. Prepare the isolated host and decrypt the archive

Follow [VPS setup](vps-setup.md) for Docker, a compatible checkout and runner-built image transfer. Do not start the normal full release yet. Configure the recovery target's protected production file using the **original** `APP_KEY`, the new target MySQL password and the intended compatible revision. Preserve credentials/keys privately; never copy an old dotenv file over unrelated existing production configuration.

From `~/Nutriscope` on this separate host, load the images and define the protected `DB_ROOT_PASSWORD`/`dc` shell function as in [environment setup](vps-setup.md#2-clone-and-configure). In this recovery session, `RECOVERY_DIR` refers to the directory created above. If downloading on another machine, transfer only the required restore set over independently verified SSH into a new restricted directory here and set `RECOVERY_DIR` to that absolute directory. Do not expose it under the web root.

The current ZIP uses AES-256 encryption. A ZIP program without AES support can reject a valid archive. The backend image already contains PHP's ZipArchive, used by [the application's archive inspector](../../backend/app/Services/Backup/BackupArchiveInspector.php). This extraction uses that image without starting Laravel, the database or the Admin UI.

**State-changing — isolated recovery host; writes only the decrypted SQL in the restricted recovery directory:**

```sh
read -rsp 'Password for this backup archive: ' RECOVERY_ZIP_PASSWORD
printf '\n'
export RECOVERY_ZIP_PASSWORD
docker run --rm --network none --env RECOVERY_ZIP_PASSWORD \
  --mount "type=bind,src=$RECOVERY_DIR,dst=/recovery" \
  --entrypoint php nutriscope-backend:latest -r '
$zip = new ZipArchive;
if ($zip->open("/recovery/database.zip") !== true || !$zip->setPassword(getenv("RECOVERY_ZIP_PASSWORD"))) {
    fwrite(STDERR, "Archive cannot be opened.\n"); exit(1);
}
$entries = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (!is_string($name) || str_contains($name, "..") || str_starts_with($name, "/") || preg_match("/^[A-Za-z]:/", $name)) {
        fwrite(STDERR, "Unsafe archive entry.\n"); exit(1);
    }
    if (str_ends_with(strtolower($name), ".sql")) { $entries[] = $name; }
}
if (count($entries) !== 1) { fwrite(STDERR, "Expected one SQL dump.\n"); exit(1); }
$input = $zip->getStream($entries[0]);
$output = fopen("/recovery/database.sql", "x");
if (!is_resource($input) || !is_resource($output)) { fwrite(STDERR, "Cannot decrypt or create SQL output.\n"); exit(1); }
$bytes = stream_copy_to_stream($input, $output);
fclose($input); fclose($output); $zip->close();
if ($bytes === false || $bytes < 1) { fwrite(STDERR, "SQL extraction failed.\n"); exit(1); }
'
extraction_status=$?
unset RECOVERY_ZIP_PASSWORD
test "$extraction_status" -eq 0
```

Stop on failure and do not import a partial `database.sql`. The exclusive output mode refuses to overwrite an existing file. Inspect the trusted dump's schema directives privately before import; stop if it selects unexpected databases or contains unexplained statements. Do not display SQL data in logs or screen shares.

### 4. Import into the new target database

**State-changing — isolated recovery VPS, deploy operator, `~/Nutriscope`; new database only:**

```sh
dc up -d mysql redis
dc cp "$RECOVERY_DIR/database.sql" mysql:/tmp/nutriscope-recovery.sql
dc exec -e MYSQL_HISTFILE=/dev/null mysql mysql --user=root --password
```

Enter the **new recovery target's** MySQL root password at the prompt. In the MySQL client, verify the target before importing. The current Compose database is `nutriscope`:

```sql
SELECT @@hostname;
SHOW DATABASES;
USE nutriscope;
SHOW TABLES;
```

Require the intended isolated server and an empty application database. If tables already exist, stop and resolve the target; do not drop them or import over them. After confirming the target:

```sql
SOURCE /tmp/nutriscope-recovery.sql;
SHOW TABLES;
SELECT COUNT(*) AS migration_rows FROM migrations;
SELECT COUNT(*) AS user_rows FROM users;
SELECT COUNT(*) AS stored_object_rows FROM stored_objects;
EXIT;
```

Watch for import errors; a client reaching the prompt does not prove every statement succeeded. Use [MySQL SQL-dump reload guidance](https://dev.mysql.com/doc/refman/8.0/en/reloading-sql-format-dumps.html). Do not run `migrate:fresh` or full demo seeding. Match the application revision to the restored migrations before applying any reviewed forward migrations.

### 5. Recover protected files using the manifest

[BackupManifestService](../../backend/app/Services/Backup/BackupManifestService.php) writes version-1 JSON with `backup_uuid` and `objects`. Each object contains `stored_object_uuid`, `protected_key`, `purpose`, `bytes` and `sha256`. [ProtectedObjectStore](../../backend/app/Services/Backup/ProtectedObjectStore.php) stores the corresponding private bytes in R2. These object keys are not the original application file paths.

For **every** manifest entry:

1. Match `stored_object_uuid` against `stored_objects.uuid` in the newly restored database. Read the target row's `storage_disk`, `object_key`, `bytes` and `sha256` in a private database client.
2. Require the UUID, size and hash to agree. For the documented local-volume topology, confirm the row belongs to the local private-upload disk. A different disk/driver needs its own storage-aware restore; do not redirect public or S3 rows into the local volume blindly.
3. Download the manifest's exact `protected_key` to a temporary file. Do not guess an object from the original filename or rename every protected file using its R2 hash as the application path.
4. Compare byte size and SHA-256 with the manifest/restored row. Then place the bytes under the **restored database row's `object_key`** in a local staging tree. Preserve the exact relative path.

Open the manifest in a private JSON viewer. In the isolated target's private MySQL session, the following read-only query retrieves the matching storage mapping; replace the UUID placeholder with the manifest entry's exact UUID. Do not publish the result:

```sql
SELECT uuid, storage_disk, object_key, bytes, sha256
FROM stored_objects
WHERE uuid = 'UUID_FROM_THIS_MANIFEST_ENTRY';
```

**State-changing locally; read-only against R2 — repeat on the isolated recovery host, private operator terminal:**

```sh
mkdir -p "$RECOVERY_DIR/private-uploads"
read -rp 'Manifest protected_key for this object: ' RECOVERY_PROTECTED_KEY
read -rp 'Matching restored row object_key: ' RECOVERY_TARGET_KEY
if [[ "$RECOVERY_TARGET_KEY" =~ ^[a-z0-9_-]+/[a-f0-9-]+\.[a-z0-9]+$ ]]; then
    aws --profile nutriscope-recovery --endpoint-url "$RECOVERY_ENDPOINT" s3 cp \
      "s3://$RECOVERY_BUCKET/$RECOVERY_PROTECTED_KEY" "$RECOVERY_DIR/object.bin" --quiet
else
    printf 'STOP: unexpected target path; review the storage mapping.\n'
fi
```

Only after the download succeeds and size/hash match, stage that object's bytes. `stat -c %s "$RECOVERY_DIR/object.bin"` and `sha256sum "$RECOVERY_DIR/object.bin"` are private read-only checks; do not publish their results. The following validates the target path again and refuses an overwrite:

```sh
if [[ "$RECOVERY_TARGET_KEY" =~ ^[a-z0-9_-]+/[a-f0-9-]+\.[a-z0-9]+$ ]]; then
    RECOVERY_TARGET_PATH="$RECOVERY_DIR/private-uploads/$RECOVERY_TARGET_KEY"
    if test -e "$RECOVERY_TARGET_PATH"; then
        printf 'STOP: target already staged; investigate the duplicate.\n'
    else
        mkdir -p "$(dirname "$RECOVERY_TARGET_PATH")" &&
        cp "$RECOVERY_DIR/object.bin" "$RECOVERY_TARGET_PATH"
    fi
fi
```

Repeat until the manifest count, verified object count and complete staged tree agree. A manifest with zero objects does not prove absence of legacy uploads; use the earlier file inventory. Stop if any required object is missing or mismatched.

**State-changing — isolated host only; create the stopped backend container/volume, then populate an empty destination:**

```sh
dc create --no-build backend
docker run --rm --network none --entrypoint sh \
  --mount "type=bind,src=$RECOVERY_DIR/private-uploads,dst=/recovered,readonly" \
  --mount type=volume,src=nutriscope_private_uploads,dst=/destination \
  nutriscope-backend:latest -c '
test -z "$(find /destination -mindepth 1 -maxdepth 1 -print -quit)" || exit 1
cp -a /recovered/. /destination/ && chown -R www-data:www-data /destination
'
```

The volume name comes from the current production Compose file. This command must never run against the failed/live host's existing volume. Restore legacy file locations separately from their matching filesystem backup. Do not restore reproducible report cache as a substitute for durable uploaded files.

### 6. Verify, then cut over

1. Keep public ingress and scheduled writers disabled. Check migration/schema compatibility, authentication, role boundaries, foreign-key relationships, sanitized counts, matching file manifests and authorized sample downloads. The [RecoveryVerifier](../../backend/app/Services/Backup/RecoveryVerifier.php) defines the application's schema/password/FK checks; use it as a maintenance reference, not a claim that manually importing SQL ran those checks.
2. Review restored backup/recovery/schedule state before starting jobs. A full dump can contain in-progress backup/recovery rows captured at backup time. Do not fabricate Completed status, blindly delete control history or assume restored schedule settings should immediately run. Fresh Redis also does not recreate old queued jobs. Resolve required control-state reconciliation through an operator-reviewed procedure.
3. After initial data and schema are compatible, use the [ordinary reviewed release step](vps-setup.md#3-load-images-restoreinitialize-and-start) to apply intended forward migrations/template/census work. Start HTTP first for controlled acceptance, then enable worker/scheduler only after the state review. Reconfigure isolated test mail/AI/backup access so acceptance cannot accidentally notify real users or prune retained incident backups.
4. Complete the [acceptance checklist](maintenance.md#acceptance-checklist). A shell import, an HTTP health response and an R2 object are separate checks; none alone proves recovery.
5. Arrange the approved one-writer cutover through [turnover](turnover.md#existing-system-migration-sequence). Preserve old-host rollback evidence, switch the selected DNS/SSH settings and confirm production web/mobile behavior. After new writes begin, switching back requires reconciling those writes.
6. Establish a new usable backup and isolated restore result. Remove plaintext SQL, temporary restore files, copied database-container SQL and temporary credentials through the institution's secure cleanup process after acceptance/retention approval. Retain protected incident evidence and original R2 archives according to policy; never run broad bucket or volume cleanup.

This manual route bypasses the Admin screen, not encryption, authorization, data consistency or recovery verification. Where a matching archive/manifest, keys or required files are unavailable, report the specific missing prerequisite and recover that evidence before accepting the installation.
