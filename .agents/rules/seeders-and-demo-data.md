# Seeders and Demo Data

- Normal `db:seed`: deterministic, repeatable, offline, idempotent where appropriate.
- Use stable canonical identity keys; never fragile internal numeric IDs.
- Use relative dates for time-bound demos; keep chronology, status, and relationships valid.
- Build the smallest connected graph that exercises the real workflow.
- Seed source/input rows; call existing services/listeners when they normally generate derived rows.
- Do not fake system-generated reports, ledgers, snapshots, notifications, transitions, or audit noise.
- Seed twice and inspect for duplicates, orphan rows, stale dates, and invalid state combinations.
- Preserve unrelated/manual data unless an explicitly approved disposable demo refresh says otherwise.
- External stock or network sources may be used once to acquire approved local assets, never during normal seeding.
- Use existing factories, relationships, private storage helpers, MIME/size validation, and cleanup paths.
- If a domain has distinct catalog or calculation rules, preserve that boundary; do not clone a parallel database.
