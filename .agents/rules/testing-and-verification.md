# Testing and Verification

## Test-driven behavior changes

1. Reproduce or define the current failure.
2. Add a focused failing test.
3. Confirm it fails for the expected reason.
4. Implement the smallest compatible fix.
5. Run focused tests, then relevant broader suites.
6. Refactor only while green.

No artificial tests for docs/format-only edits. No unrelated changes to fake green. Reproduce transient env/network/file-lock/fixture failure before calling product defect.

## Verification matrix

- Authorization enforced server-side; hidden UI ≠ access control.
- Public contract uses UUID where provided; never leak/hard-code internal numeric ID.
- API shape/routing change → verify route names/params, resources, web proxies, deep links, pagination metadata, mobile consumers.
- Keep shared paginator visible for valid empty state (`Page 1 of 1 · 0 items`, disabled controls) where contract requires.
- Backend: focused Laravel → broader/full when requested/release-critical; Pint after PHP; useful syntax; auth/validation/UUID; route/cache when routing changes.
- Frontend: focused Vitest → broader/full as needed; TypeScript; ESLint; prod build.
- Mobile/shared contracts: affected tests; TypeScript; Android export/build when release behavior changes.
- Seeders: offline; seed twice; clean disposable seed; relations/status; manual-data survival; audit noise; private-file/orphan checks.
- Reports: render/inspect content, pages, clipping, overlap, margins, fonts, black/blank preview. HTTP success insufficient.
- Run `git diff --check` before staging or completion.

## Evidence levels

Local tests ≠ CI ≠ build ≠ push ≠ deploy ≠ deployed-browser acceptance. Name proven level.

- Push claim: verify local `HEAD` = `origin/main` = intended remote revision.
- Live acceptance: wait deploy; use deployed URL + fictional data; record URL/revision/scenarios/failures.
- Before any browser fix, finish full task-scoped sweep across affected roles/routes/reports/responsive states. Log every reproducible error, group shared root causes, then fix batch. Immediate patch only for active security/data-loss risk.
- Generated APK ≠ published release. Verify stable URL/version/bytes/MIME-header/checksum.
- Report pre-existing failures; never hide/fix out of scope.
