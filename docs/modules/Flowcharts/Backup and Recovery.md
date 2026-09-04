# Backup and Recovery

This is the maintained administrator reference for the Phase 1.5 backup and whole-system recovery workflow. Automatic schedules are disabled by default in demo deployments.

## Automatic and Manual Restore Points

```mermaid
flowchart TD
    A["Admin Backup page"] --> A1["Use lifecycle tabs: Available, In progress, Failed, Recently Deleted"]
    A1 --> A2["Use category tabs: Daily, Weekly, Monthly, Manual"]
    A2 --> B{"Automatic schedules enabled?"}
    B -->|"None"| C["Show: Automatic backups are disabled"]
    B -->|"Any combination"| D["Show each next scheduled backup"]
    D --> E["Coordinator checks every 10 minutes after the 01:30 Asia/Manila target"]
    E --> F{"Enabled daily, weekly, or monthly period missing?"}
    F -->|"No"| G["Do nothing; period already satisfied"]
    F -->|"Yes"| H["Claim category and period with unique locks"]
    H --> I["One queued backup satisfies all categories due together"]
    A2 --> J["Admin selects Create backup now"]
    J --> K["Create independent manual restore point kept until Admin deletion"]
    I --> L["Run shared backup pipeline"]
    K --> L
    L --> M["Create encrypted MySQL archive"]
    M --> N["Incrementally copy new or changed private uploaded objects"]
    N --> O["Write immutable manifest with relationships, sizes, and checksums"]
    O --> P["Verify archive checksum, decryption, SQL entry, manifest, and protected files"]
    P --> Q{"Verification passed?"}
    Q -->|"No"| R["Mark Failed, show safe result, notify configured contact, and audit activity"]
    Q -->|"Yes"| S["Mark Available and audit activity"]
    S --> T["Apply automatic retention: 3 daily, 2 weekly, or 3 monthly; manual points do not auto-expire"]
```

Disabling a schedule stops future periods only. It does not delete or reclassify existing restore points. A manual backup never satisfies an automatic period.

## Retention and Recently Deleted

```mermaid
flowchart TD
    A["Available restore point reaches its last assigned expiry"] --> B{"Protected by active recovery?"}
    B -->|"Yes"| C["Keep Available until recovery finishes"]
    B -->|"No"| D["Move archive, manifest, and file references to Recently Deleted for 48 hours"]
    D --> E{"Admin acts before deadline?"}
    E -->|"Keep"| F["Return restore point to Available"]
    F --> G["Keep backup rescues the restore point only; it does not restore application data"]
    E -->|"Delete permanently or wait 48 hours"| H["Purge archive and manifest"]
    H --> I["Purge protected file copies only when no remaining restore point references them"]
    I --> J["Record purge result in Audit Logs"]
```

## Periodic Recovery Validation

```mermaid
flowchart TD
    A["Daily check after 03:00"] --> B{"Successful recovery test in the last 30 days?"}
    B -->|"Yes"| C["No drill needed"]
    B -->|"No"| D["Select latest verified restore point"]
    D --> E["Restore into a disposable temporary MySQL database"]
    E --> F["Verify schema, foreign keys, app boot, authentication schema, roles, password hashes, manifest, and files"]
    F --> G["Do not create users, credentials, sessions, or business fixtures"]
    G --> H{"Checks passed?"}
    H -->|"Yes"| I["Record latest successful recovery-test date"]
    H -->|"No"| J["Record safe failure result and notify"]
    I --> K["Always drop the drill database"]
    J --> K
```

## Admin Whole-System Restoration

```mermaid
flowchart TD
    A["Admin selects a verified whole-system restore point"] --> B["Review scope and the newer-data loss window"]
    B --> C["Enter current password and exact confirmation phrase"]
    C --> D{"Authorized and confirmed?"}
    D -->|"No"| E["Stop with no system changes"]
    D -->|"Yes"| F["Create Requested recovery and Audit Log entry"]
    F --> G["Queued job creates one verified pre-restore safety snapshot"]
    G --> H{"Safety snapshot verified?"}
    H -->|"No"| I["Mark Failed; production remains unchanged; notify and audit"]
    H -->|"Yes"| J["Restore selected archive into one new temporary MySQL database"]
    J --> K["Verify database integrity and matching uploaded files without mutations"]
    K --> L{"All preparation checks passed?"}
    L -->|"No"| I
    L -->|"Yes"| M["Show Ready"]
    M --> N{"Admin cancels before Switching?"}
    N -->|"Yes"| O["Drop temporary database, mark Cancelled, notify page, and audit"]
    N -->|"No"| P{"Restore enabled and single-Droplet prerequisites ready?"}
    P -->|"No"| Q["Remain Ready; production unchanged; enable only after acceptance"]
    P -->|"Yes"| R["Enter shared Redis maintenance mode immediately before switching"]
    R --> S["Transactionally restore application data and activate matching private files"]
    S --> T["Run basic production health and manifest checks"]
    T --> U{"Cutover healthy?"}
    U -->|"Yes"| V["Exit maintenance mode, mark Completed, and audit"]
    U -->|"No"| W["Automatically switch back to safety snapshot"]
    W --> X["Verify rollback, exit maintenance mode, mark Rolled Back, notify, and audit"]
```

After Completed, Failed, Rolled Back, or Cancelled, the safety snapshot remains a whole-system restore point for 48 hours. If restoration or rollback is still active at 48 hours, protection continues until the process reaches a terminal status, then the 48-hour window begins. Newer records are not merged or placed in a review database; after a successful older restore they exist only in this access-controlled safety snapshot.

## Recovery Boundaries

- Only Admin can change schedules or initiate/cancel recovery.
- Raw database archives, object keys, passwords, and provider credentials never enter the browser.
- Production is never the first restoration target.
- Recovery is whole-system only. There is no Technical Operator website role, separate review database, individual-record merge, or universal Record Trash.
- Preview/download of saved reports are read-only and reproducible report-cache PDFs are excluded from backup manifests.
- The DigitalOcean single-Droplet switcher preserves current backup metadata, recovery history, schedules, migrations, and Audit Logs while restoring application data and matching protected uploads. It stays disabled until production readiness is accepted.

## Related Documents

- [Admin Module](../admin.md)
- [Platform Requirements](../../operations/platform-requirements.md)
- [Backup and Recovery Guide](../../operations/backup-recovery.md)
- [Phase 2 Platform Handoff](../../operations/phase-2-platform-handoff.md)
