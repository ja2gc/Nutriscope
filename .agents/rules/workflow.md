# Universal Workflow Rules

- This file holds universal rules only. Topic files hold reusable technical context/contracts, never one-off product decisions.

## Scope and phases

- Current explicit request + scope = authority. Interpret literally.
- Investigate/audit/diagnose/review = read-only evidence report. Do not fix.
- Plan only = plan only. No code, data, config, asset, doc, or DB edits.
- Honor phase gates. Stop and wait at an explicit decision gate.
- Approved implementation → finish implementation, proportional verification, and requested delivery.
- `[MUST ANSWER]` → give 3 materially different options, recommendation, tradeoffs, blast radius.
- Ask only when an unresolved choice materially changes scope, risk, data, security, or external state.
- Later additions extend unfinished scope unless clearly replace it. “Do not reply” = silence.

## Truth and diagnosis

- Current code, migrations, routes, tests, config templates, and verified runtime = truth.
- Docs, memory, screenshots, pasted logs, and old plans = context; verify before relying on them.
- No guessing. Use official current sources for volatile security, nutrition, licensing, standards, providers, and framework behavior.
- Diagnose root cause and all affected consumers before changing anything.
- Consider 2–3 small approaches; choose safest, simplest compatible fix.
- After 3 failed fixes, stop stacking patches and reassess.
- Existing source ≠ rendered/live behavior. Check the actual page, artifact, or deployed path when relevant.

## Code, Git, and safety

- Check Git first. Preserve unrelated tracked/untracked changes. Stage task files only.
- Use `rg`/`rg --files` to search. Use `apply_patch` for edits.
- Never use destructive Git (`reset --hard`, checkout overwrite) without exact user authorization.
- Destructive DB, production, storage, or cleanup action → resolve exact target and get action-time authorization unless explicitly authorized for that exact action.
- `migrate:fresh` only on a confirmed disposable database.
- Never read, print, log, package, commit, or expose secrets, credentials, private keys, backup contents, real prod env values, or private object paths.
- Never commit provider/API keys or secret-bearing files.
- Preserve live `APP_KEY`; never replace production config with an example.
- Forward-only migrations. Do not rewrite applied migration history.
- Server-side authorization is mandatory. Public UUID boundaries stay intact; never guess internal numeric IDs.
- Shared features such as notifications and announcements must be checked across every relevant role, not one role only.
- Do not add AI/assistant/Codex/Claude/co-author/contributor/generated-by attribution.
- Do not modify GitHub Actions unless a proven task-scoped cause requires it.
- Never read stale root `deployment.md`.

## Architecture and data

- Reuse existing models, services, jobs, factories, storage, auth, pagination, audit, reports, and UI patterns.
- No duplicate systems, plans, handoffs, flowcharts, databases, recipe/nutrient engines, services, components, provider adapters, or state-machine packages.
- Keep blast radius small. Do not rebuild a working subsystem for style.
- Normal seeders must be deterministic, repeatable, offline-capable, and idempotent where appropriate.
- Seed source/input records; let normal services/listeners/jobs generate derived records whenever the system normally does so.
- No runtime network/API dependency in normal seeding. External stock sources are one-time inputs; save approved local assets using existing patterns.
- No seed audit noise unless current conventions require it.
- Do not invent data, statuses, timestamps, notifications, reports, ledgers, snapshots, or attachments that the real workflow would not produce.
- Protect privacy: fictional demo identities only; scope data to the authorized role and owner.
- R2/S3 object presence, DB rows, backups, and recovery are separate claims; verify each separately.

## Behavior changes and verification

- Behavior change/bug fix → write or update focused tests before implementation (TDD), then test the root behavior and consumers.
- Check server authorization, validation, public UUIDs, API contracts, routes, pagination/empty states, generated outputs, and failure cleanup.
- Seeder change → run twice, check duplicates, chronology/status validity, connected graph, and generated-vs-seeded boundaries.
- Report/PDF change → generate the real artifact and inspect page count, clipping, overlap, identity, dates, and scope.
- Run affected tests first, then required full suites/build/lint/type checks. Do not call one green check “complete.”
- Docs-only change needs focused link/fence/cross-reference checks; do not create artificial tests.
- Before completion, self-review scope, authorization, privacy, stale dates, idempotence, data semantics, accessibility, and failure paths.

## Browser, deployment, and release

- Live-browser test = deployed URL, fictional accounts/data, visible user workflows; localhost/unit tests are not live acceptance.
- During live testing, inventory every observed error first; group by root cause; then batch-fix and rerun the full task-scoped sweep. Immediate-only exception: active security or data-loss risk.
- Record exact URL, revision, scenarios, and pass/fail evidence. Never claim untested live behavior.
- Separate local tests, CI, build, push, deploy start, health, and live acceptance in reports.
- Before production commands, label read-only vs state-changing; inspect current Compose, Dockerfiles, health, logs, CPU, memory, swap, disk, network, and image IDs.
- Do not claim deploy success from build success. Verify release/migration exit, running revision, containers, health, and public behavior.
- Backup readiness requires harmless upload inclusion, valid archive/retention, and restore verification in a temporary target. Snapshot/backup alone is not recovery proof.
- Shared mobile contract/release → update metadata, run tests/type checks, build/publish through existing workflow, verify version/versionCode, APK bytes/MIME/header/checksum, and QR points to the latest stable artifact.

## Documentation and communication

- Update existing docs/storyboards only when workflow, data semantics, demo sequence, or visible wording becomes false. Do not duplicate them.
- Use the user-designated canonical external storyboard location; never edit an untracked/local duplicate by guess.
- Keep role boundaries, visible actions, persistence, generation, and report paths accurate.
- Use applicable Superpowers and skills; Caveman mode is default. Backend work uses Laravel Boost MCP and the installed Laravel best-practices skill when available.
- No subagents/worktrees unless explicitly authorized; only an authorized fresh same-model read-only final review is allowed.
- Backend: focused Laravel tests, broader/full suite when required, Pint after PHP, and route/cache checks when routing changes.
- Frontend: affected tests, TypeScript, ESLint, and production build when applicable. Mobile/shared contracts: affected tests, TypeScript, and Android build/export when release behavior changes.
- Tool work gets short progress updates. Final answer: outcome first; distinguish changed, verified, pushed, deployed, and live-accepted.
- Never report unfinished work as finished.
- When approved delivery includes integration, commit only task files, push the requested target (this repo’s release target is `main`), and verify local/remote revision parity.
