# Backup and Recovery — Phase 1.5 Planned Flow

This flow extends the implemented Phase 1 database-backup foundation. The staged restoration and uploaded-file recovery steps are planned for Phase 1.5 and are not current production behavior.

## Automatic Backup and Retention

```mermaid
flowchart TD
    A["Daily schedule at 1:30 AM or Admin selects Create backup"] --> B{"Another backup active?"}
    B -->|"Yes"| C["Do not start a duplicate backup"]
    B -->|"No"| D["Create encrypted MySQL archive"]
    D --> E["Copy new or changed private uploaded files"]
    E --> F["Create object manifest with sizes and checksums"]
    F --> G["Store database archive and manifest in private backup storage"]
    G --> H["Verify checksum, decryptability, SQL contents, and referenced objects"]
    H --> I{"Verification passed?"}
    I -->|"No"| J["Mark Failed, retain evidence, and notify configured contact"]
    I -->|"Yes"| K["Mark Available and apply retention"]

    K --> L["Keep newest 3 daily restore points"]
    K --> M["Keep 2 weekly restore points"]
    K --> N["Keep 3 monthly restore points"]
    L --> O{"Backup outside retained set?"}
    M --> O
    N --> O
    O -->|"No"| P["Remain Available"]
    O -->|"Yes"| Q{"Protected by active recovery?"}
    Q -->|"Yes"| P
    Q -->|"No"| R["Move to Recently Deleted for 48 hours"]
    R --> S{"Admin selects Keep backup in time?"}
    S -->|"Yes"| P
    S -->|"No"| T["Permanently purge expired backup objects"]
```

## Admin Full-System Restoration

```mermaid
flowchart TD
    A["Admin opens Backup and Recovery"] --> B["Select an Available restore point"]
    B --> C["Review backup time, scope, file coverage, and newer-data warning"]
    C --> D{"Continue?"}
    D -->|"No"| E["Cancel with no system changes"]
    D -->|"Yes"| F["Reauthenticate and explicitly confirm"]
    F --> G["Protect selected restore point"]
    G --> H["Create safety snapshot of current database and uploaded objects"]
    H --> I["Create isolated temporary MySQL database and file-recovery area"]
    I --> J["Download, verify, decrypt, and import selected database archive"]
    J --> K["Restore and verify uploaded objects listed in the matching manifest"]
    K --> L["Run schema, integrity, sign-in, role, and critical-workflow checks"]
    L --> M{"All automated checks passed?"}

    M -->|"No"| N["Mark Failed and keep live system unchanged"]
    N --> O["Show safe failure details and retain safety snapshot"]

    M -->|"Yes"| P["Show Ready to switch"]
    P --> Q{"Admin confirms cutover?"}
    Q -->|"No"| R["Cancel temporary restore and keep live system unchanged"]
    Q -->|"Yes"| S["Enter maintenance mode and pause writes"]
    S --> T["Switch NutriScope to restored database and matching files"]
    T --> U["Run production health and workflow checks"]
    U --> V{"Cutover healthy?"}
    V -->|"Yes"| W["Exit maintenance mode and mark Completed"]
    V -->|"No"| X["Automatically switch back to safety snapshot"]
    X --> Y["Exit maintenance mode and mark Rolled Back"]
```

## Recovery Scope

```mermaid
flowchart TD
    A["Admin identifies a recovery need"] --> B{"What needs recovery?"}
    B -->|"Deleted backup archive"| C["Use Keep backup within 48 hours"]
    B -->|"Database damage or broad data loss"| D["Use staged full-system restoration"]
    B -->|"Missing uploaded file"| E["Recover protected object version when Phase 1.5 file recovery exists"]
    B -->|"One arbitrary database row"| F["Not supported by generic backup restore"]
    F --> G["Use a separately designed module-specific recovery process if later required"]
```

Backup restoration operates on a complete restore point. It does not provide a generic tool for copying arbitrary database rows into the live system.

## Safety Boundaries

- Only an authenticated Admin may initiate restoration.
- Raw database archives, object-storage credentials, and encryption secrets never enter the browser.
- Restoration always prepares and checks an isolated environment before production cutover.
- The live system remains unchanged when preparation or validation fails.
- A safety snapshot and automatic rollback protect the current state during cutover.
- Phase 2 provider selection must support the temporary database, workers, scheduler, maintenance mode, health checks, private object storage, and safe cutover required by this flow.

## Related Documents

- [Admin Module](../admin.md)
- [Platform Requirements](../../operations/platform-requirements.md)
- [Backup and Recovery Guide](../../operations/backup-recovery.md)
- [Phase 2 Platform Handoff](../../operations/phase-2-platform-handoff.md)
