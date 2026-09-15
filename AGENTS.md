# NutriScope Agent Instructions

This file is only the router. Read `.agents/rules/workflow.md` first; it holds
all universal guardrails, do-nots, working style, phases, skill use, and
completion rules. Then read every matching topic file below. Nested
`AGENTS.md` adds directory rules; specific rule wins unless it weakens
`workflow.md`.

## Topic routing

Read full matching files. Multi-topic task → read every match.

| Task involves | Required file |
|---|---|
| Seeders, factories, dates, demo users/data, local assets | `.agents/rules/seeders-and-demo-data.md` |
| Patients, NCP/ADIME, appointments, intervention, notifications | `.agents/rules/clinical-and-notifications.md` |
| Menus, recipes, shopping lists, procurement, budgets, FSS, PDFs/reports | `.agents/rules/food-service-and-reports.md` |
| Uploads, profile photos, announcements, private files, R2/S3, image display | `.agents/rules/media-and-storage.md` |
| `/docs`, Help, flowcharts, or external storyboards | `.agents/rules/documentation.md` |
| Docker, production, DigitalOcean, backups, CI, EAS/APK, deployment | `.agents/rules/deployment-and-release.md` |

## Directory rules

- Backend: `backend/AGENTS.md` + `backend/.agents/skills/laravel-best-practices` + relevant Laravel skills. Prefer Laravel Boost.
- Frontend: `frontend/AGENTS.md` + installed Next.js docs. Do not trust remembered APIs.
- Mobile: `mobile/AGENTS.md` + exact installed Expo docs.
