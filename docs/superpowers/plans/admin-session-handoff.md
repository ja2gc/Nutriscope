# Admin Session Handoff

> Self-contained handoff so work can resume on a **different device** (no local AI memory).
> Last updated: 2026-06-15.

## TL;DR — are we ready for Phase B (Admin)?

**Not yet.** Phase A (FSS/RND backend) must finish first (user constraint: *do not start Admin until Phase A is done*). Phase A is ~70% done; remaining = **§7 RND fixes** (see checklist). Once §7 is complete + full suite green, we jump to Phase B.

## Conventions (read first)

- Work on `main`. **No `Co-Authored-By`** trailer (author = jared).
- Verify gate every change: `cd backend && php artisan test` (sqlite `:memory:`, baseline **473 passing**) + `cd frontend && npx tsc --noEmit`.
- TDD: write failing test → implement → green → commit. One concern per commit.
- Dev login (browser/preview): `rnd@nutriscope.local` / `nutriscope2024!`.
- The Gemini/Opus review docs were **frequently wrong** — verify every claim against code before building. Already-retracted false findings: `complete-day missing`, `ai_usage_logs never written`, `procurement ignores inventory`. Don't re-implement these.

## Phase A status (`docs/superpowers/plans/implementation_plan.md`)

| § | Item | Status |
|---|---|---|
| 1 | CleaningLog CRUD | ✅ done + committed |
| 2 | FSS read-only guards + budgets→RND ownership | ✅ done + committed |
| 2.5 | Meal-prep shortfall + population variance + RND alerts | ✅ done + committed |
| 3 | FSS announcements feed | ✅ done + committed |
| 7 | RND clinical/algorithm fixes | 🔧 IN PROGRESS — see below |

### §7 sub-checklist (remaining Phase A work)
- [x] **Dynamic clinical_rules** — `RND/InterventionController::mapGoalTypeToConditions` now reads `config/clinical.php` (`goal_type_conditions`). Fixed a real bug (old hardcoded strings like `'Hypertension'`/`'Malnutrition'` never matched the lowercase `clinical_rules.condition` values, so most goals returned zero rules). **Open:** the `cardiac_diet → [hypertension, dyslipidemia]` mapping needs dietitian confirmation.
- [ ] **MealPlan AI fallback** — `MealPlanService.php` returns `['insufficient_recipes'=>true]` when <5 recipes match; controller never calls AI. Wire the Sonnet fallback per `docs/logic/meal-algorithm.md`. CONFIRMED missing.
- [ ] **AI diagnosis → background job (202)** — `RND/AiDiagnosisController` calls `AIService::suggestDiagnoses` synchronously. Moving to a queued job **changes the API contract** (202 + poll/listen) → coordinate with frontend before shipping. `AIService` already writes `AiUsageLog`; keep that write inside the job.
- [ ] **Monitoring AI-review caching** — verify it's actually missing in `MonitoringController`, then `Cache::remember()` per visit-pair.
- [ ] **N+1 eager-loads** — verify the RND list endpoints (e.g. `NcpRecordController` index / dashboard) don't eager-load `assessment`/`intervention`; add `with(...)` if confirmed.
- [ ] **prescription-targets.json sync** — verify `NutritionPrescriptionService` implements `free_sugar_max_pct_energy` (0.1) and validates `stage` against Asia-Pacific `bmi_range`. Clinical-correctness risk — test against the golden cases in `docs/logic/prescription-targets.json`.
- [x] ~~ProcurementService on-hand~~ — RETRACTED; live path already nets stock.
- [~] Report `dispatchSync` — intentional/documented; leave as-is.

## Phase B — Admin (start only after Phase A done)

Backend (`implementation_plan.md` §4–§6), each TDD:
- **§4 Audit-log** — `Admin\AuditLogController@index` returns `Activity::with('causer')->get()` raw. Add pagination + filters (causer_id, subject_type, event, start/end) + create `AuditLogResource`. **PHI is already redacted at write-time** by the `AuditsChanges` trait (`tapActivity`, `$auditRedactValues` on clinical models) — do NOT add a redundant controller redaction; instead add a regression test that no PHI reaches the API. Existing `AdminSystemTest::test_admin_can_list_audit_logs` only asserts `['data']`, stays green. (A draft `AdminAuditLogTest` was started then removed as premature — rewrite it under §4.)
- **§5 Password reset** — no `resetPassword` exists. Add `Admin\UserController@resetPassword` + `AdminResetPasswordRequest` + route `POST users/{user}/reset-password` with `throttle`. Class is `UserController` (routes alias it as `AdminUserController`).
- **§6 Admin dashboard** — `AdminDashboardController` with `Cache::remember()` aggregates. `ai_usage_logs` already populated (no `AiTokenObserver` needed).

Frontend Admin console: `docs/superpowers/plans/admin-sprint-plan.md` (Next.js `(admin)` route group). Note its self-review caveats (Next 16 breaking changes; read `frontend/AGENTS.md`).

## Parallel track — FSS mobile

`docs/mobile-integration.md`: the team's React Native app goes in a top-level `mobile/` folder, talks to the same backend via Sanctum Bearer token. FSS API contract is frozen (Phase A). Independent of Phase A/B backend work.

## Key files

- Backend routes (FSS read-only split, RND group): `backend/routes/api.php`
- Clinical goal mapping: `backend/config/clinical.php`; source docs `docs/logic/intervention-goals.md` + `intervention-goals-asia-pacific-research.md`
- Audit trait (write-time PHI redaction): `backend/app/Models/Concerns/AuditsChanges.php`
- Schema reference: `docs/database-schema.md` (corrected 2026-06-15 for budgets / meal_prep_logs / food_service_recipes)
- Plans: `docs/superpowers/plans/{implementation_plan,admin-sprint-plan,fss-sprint-plan,rnd-review,fss-admin-plan-review}.md`
