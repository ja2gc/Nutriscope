# Backup and recovery guide

This guide is for an administrator who does not use server commands.

## What happens automatically

NutriScope asks the worker to create an encrypted database backup each day. It verifies the archive and keeps:

- the newest **3 daily** restore points;
- **2 weekly** restore points after those;
- **3 monthly** restore points after those.

This is at most eight retained restore points, not fourteen daily copies. A backup removed by an administrator stays in **Recently Deleted** for **48 hours**, then the scheduler permanently removes it. Automatic retention uses the same recovery window.

## Use the Backups page

Open **Administration > Backups**.

- The status card shows whether the latest backup is verified, running, or failed.
- Select **Create backup** for an important checkpoint. It stays disabled while another backup runs. Wait for feedback; do not repeatedly click it.
- Select **Keep** to protect a restore point that is about to expire.
- Select **Delete** to move a backup to Recently Deleted. This is not immediate permanent deletion.
- After a live-system failure, select a verified restore point and choose **Request recovery**. Add a short incident note. This protects it and records a request for the technical operator.

Administrators cannot directly restore or download a raw database through the website. This prevents accidental overwrite and browser exposure of database contents.

## Technical recovery procedure

An authorized technical operator works outside the live website:

1. Protect the selected verified backup from retention.
2. Download it into a temporary access-controlled environment.
3. Decrypt it and restore it into a new temporary MySQL database.
4. Run migration-status and integrity checks without modifying the archive.
5. Verify sign-in and critical NutriScope workflows against the temporary database.
6. Schedule a controlled cutover and notify the client.
7. Keep the old database unchanged until the client accepts recovery.

Never import a backup directly over the live database as the first test.

## Backup limits

The database backup **does not include** the production `.env`, logs, Redis, Docker images, HTTPS certificates, uploaded files, or generated reports. Keep production configuration in the platform secret manager. Protect uploaded files with the storage provider's versioning or backup policy.

If a backup fails, the page records it and the configured alert address receives a notification. Preserve the error for the technical operator. Never place passwords, keys, patient details, or database content in the incident note.
