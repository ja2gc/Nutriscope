# NutriScope Agent Instructions

Repo-wide. Always read. Before action, read full matching topic files below. Nested `AGENTS.md` adds directory rules; specific rule wins unless it weakens this file.

## Non-negotiable rules

1. Current explicit user request + scope = authority. Treat “investigate,” “diagnose,” “plan only,” approval, phases literally.
2. Current code/migrations/routes/tests/config templates/verified runtime = implementation truth. Docs/memory = context, not current proof.
3. No guessing. Verify volatile facts. Use official current sources for security, nutrition, licensing, protocols, standards, frameworks, providers.
4. Check Git first. Preserve unrelated tracked/untracked work. Stage task files only.
5. Never expose/request/print/log/commit secrets, prod env values, private keys/object paths, credentials, backup contents.
6. Diagnose root cause + blast radius first. Behavior change = TDD. Smallest compatible fix.
7. Reuse NutriScope patterns/services/routes/jobs/models/resources/factories/storage/auth/pagination/audit/reports/UI. No duplicate systems/speculative abstractions.
8. No subagents/worktrees unless explicitly authorized. Fresh same-model read-only final review only when authorized.
9. Destructive prod/data/storage action: exact target + action-time authorization, unless exact action just requested.
10. No completion/pass/deploy/live-acceptance claim without fresh matching evidence.
11. Never read stale root `deployment.md`. Inspect current Compose/Dockerfiles/workflows/env templates/code/runtime.
12. Use applicable Superpowers/skills. Caveman mode default unless user requests otherwise.

## Topic routing

Read full matching files before action. Skip unrelated files.

| Task involves | Required file |
|---|---|
| Investigation, planning, implementation boundaries, communication | `.agents/rules/workflow.md` |
| Any edits, Git, destructive actions, privacy, or secrets | `.agents/rules/git-and-safety.md` |
| Behavior changes, bug fixes, tests, builds, or completion claims | `.agents/rules/testing-and-verification.md` |
| Seeders, factories, dates, demo users/data, local assets | `.agents/rules/seeders-and-demo-data.md` |
| Patients, NCP/ADIME, appointments, intervention, notifications | `.agents/rules/clinical-and-notifications.md` |
| Menus, recipes, shopping lists, procurement, budgets, FSS, PDFs/reports | `.agents/rules/food-service-and-reports.md` |
| Uploads, profile photos, announcements, private files, R2/S3, image display | `.agents/rules/media-and-storage.md` |
| `/docs`, Help, flowcharts, or external storyboards | `.agents/rules/documentation.md` |
| Docker, production, DigitalOcean, backups, CI, EAS/APK, deployment | `.agents/rules/deployment-and-release.md` |

Multi-topic task → read every match.

## Directory-specific instructions

- Backend: `backend/AGENTS.md` + `backend/.agents/skills/laravel-best-practices` + relevant Laravel skills. Prefer Laravel Boost.
- Frontend: `frontend/AGENTS.md` + installed Next.js version docs. Do not trust remembered APIs.
- Mobile: `mobile/AGENTS.md` + exact installed Expo docs.

## Final communication

Outcome first. Terse, concrete, honest. Separate implemented/tested/built/pushed/deployed/live-accepted. State risks + manual steps.
