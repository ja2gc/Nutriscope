# NutriScope Rule Map

This file only explains what to read and when. It contains no independent
guardrails. Read `.agents/rules/workflow.md` for all universal rules first.

## Rule files

| File | Read when the task involves |
|---|---|
| `rules/workflow.md` | Always: scope, phases, guardrails, do-nots, working style, skills, verification, release, and communication |
| `rules/seeders-and-demo-data.md` | Seeders, factories, demo users/data, dates, generated-vs-seeded records, or local demo assets |
| `rules/clinical-and-notifications.md` | Patients, NCP/ADIME, appointments, interventions, meal plans, or notifications |
| `rules/food-service-and-reports.md` | Menus, recipes, shopping lists, procurement, budgets, FSS, PDFs, or reports |
| `rules/media-and-storage.md` | Uploads, profile photos, announcements, private files, R2/S3, or image display |
| `rules/documentation.md` | `/docs`, Help, flowcharts, workflow prose, or repository-root `Storyboarding/` |
| `rules/deployment-and-release.md` | Docker, production, DigitalOcean, backups, CI, EAS/APK, or deployment |

Multi-topic task → read every matching topic file. Do not load unrelated topic
files. Nested directory instructions add context for that directory.

## Directory and skill pointers

- Backend task → `../backend/AGENTS.md` and `../backend/.agents/skills/laravel-best-practices/`; use matching Laravel Boost MCP/tool guidance.
- Frontend task → `../frontend/AGENTS.md` and installed Next.js documentation.
- Mobile task → `../mobile/AGENTS.md` and exact installed Expo documentation.
- Special artifact task → use the matching installed skill and its `SKILL.md`.
