# RND Final Sprint Plan — Execution

> **Source of truth for scope:** [`docs/modules/rnd.md`](../../../modules/rnd.md). This file is the **execution plan** (exact tasks + file references) — an agent should read `rnd.md` for *what/why* (esp. the five **[2026-06-19]** scope revisions), then this file for *what to do and where*.
> **For backend tasks:** consult `backend/.agents/skills/laravel-best-practices/skills.md` first (follow its own "How to Apply" routing; delegate reading `rules/` to a sub-agent).
> **Conventions:** work on `main`; **NO `Co-Authored-By`** (author = jared). Verify backend `cd backend && php artisan test`; frontend `cd frontend && npx tsc --noEmit`.
> **Target platform:** RND app is Next.js under `frontend/app/(rnd)/`. Backend is Laravel 11 under `backend/`.

> **⚠ Pre-defense reset:** the 6-19 notes **overturn already-built code** (OCR pipeline). Several tasks are *deletions*, not additions. Don't preserve the OCR work "just in case" — it is explicitly out of scope.

---

## Status / Progress log

> **For session handoff (prompt-cache / context-window switch):** update this block after each subtask. Check off `- [x]` in the task body AND commit. A fresh session reads this block first, then resumes at the first unchecked task.

- **Status: ALL TASKS COMPLETE** (T0–T8). Only the T5 host-runner deploy step is left for the user (Windows Task Scheduler / `schedule:work`).
- **Verification:** backend full suite 496/496 + touched-tests 27/27 green; frontend `tsc --noEmit` exit 0. Browser preview not run (needs running Laravel + logged-in RND session; not wired in the preview sandbox).
- **Backend DONE:** T0, T1, T2, T4, T7-be, T5 (compose cleaned, schedule registered).
- **Frontend DONE:** T6 (notifications page + live bell badge), T7-fe (profile page + TopBar link), T8 (settings: density + reduced-motion + notif mgmt; dark mode deliberately skipped — Tailwind v4 light-only, app-wide rewrite), T3 (assessment page fully rewritten — OCR stripped, tabs D/E = attachments), T3b (patient-profile per-cycle Attachments tab).
- **Follow-up source (decided):** notification trigger B keys on `intervention.next_followup_date` (canonical patient "next follow-up" per `PatientResource:37`), not `monitoring.next_monitoring_date`.
- **Execution order:** T0 → T1 → T2 → T4 → T7 → T5 → T6 → T3 → T8 (rationale below)
- **Decisions made mid-exec:**
  - **Tests run on host** with `DB_HOST=127.0.0.1` override (phpunit.xml hardcodes `mysql` docker-net host; only mysql/redis/omr/paddleocr run as containers, backend runs on host PHP 8.4). Run from `backend/`.
  - **Attachments unified on `screening_documents`** table/model (reuse, not new model). `type` now nullable string (`screening|labs|referral`), added `original_name`, dropped all extraction cols. Per-cycle scope via `assessment_id → ncp_record`. Endpoints: `POST/GET /ncp-records/{id}/attachments`, `DELETE /screening-documents/{id}`, `GET /screening-documents/{id}/file`.
  - **Scope add (user edit to rnd.md §3.1):** patient-profile **Attachments tab** per NCP cycle → Task **3b**.
  - Deleted extra (beyond plan list): `app/Events/DocumentExtractionCompleted.php`, `database/seeders/ExtractionTemplateSeeder.php`.
- **Blockers:** T5 needs user input — where the backend runtime/container is defined (not in `docker-compose.yml`; backend runs on host, not containerized).

### Execution order rationale
1. **T0** — baseline green + calendar verify (no-op likely).
2. **T1** — delete OCR pipeline (warm context; cross-file grep sweep). Unblocks T2/T3.
3. **T2** — attachments endpoint + report section (depends on T1's repurposed upload).
4. **T4** — NotificationService + 2 triggers (backend, independent of frontend).
5. **T7** — Profile self-service endpoints + page (backend+frontend, self-contained).
6. **T5** — scheduler in Docker + remove dead OCR services (needs T4's command to exist; **user-blocked** on backend-container location).
7. **T6** — Notifications frontend (depends on T4 writing notifications).
8. **T3** — Assessment page refactor (depends on T2 attachment endpoints).
9. **T8** — Settings frontend (depends on T6 notif endpoints in use; lowest risk, last).

> Frontend tasks (T3, T6, T8) are tightly specced → safe to hand to separate Sonnet sessions; backend/judgment tasks (T1, T4, T7) benefit from warm Opus context.

---

## Scope map — every 6-19 revision → task

| `rnd.md` revision | Task |
|---|---|
| §3.1 OCR/OMR pipeline removed; upload repurposed as attachments | **T1, T2, T3** |
| §6 Calendar frontend hidden (backend kept) | **T0** (verify only) |
| §7 Notifications — 2 triggers + scheduler | **T4, T5, T6** |
| §8 Settings — backend-supported + local prefs | **T8** |
| §9 Profile — name/email/password, no photo | **T7** |

---

## Task 0 — Guardrails & verification baseline

- [ ] Branch off `main` (or work on `main` per convention). Confirm clean tree.
- [ ] **§6 Calendar check (no build):** confirm Calendar is already absent from the RND sidebar — search [`frontend/components/layout/Sidebar.tsx`](../../../../frontend/components/layout/Sidebar.tsx) for `calendar` → expect **0 matches**. Leave backend (`calendar_events` table, `RND/CalendarEventController`, routes `api.php:141-143`) **intact**. If a nav entry is found, remove it; otherwise this task is a no-op confirmation.
- [ ] Record baseline: `cd backend && php artisan test`, `cd frontend && npx tsc --noEmit` both green before starting.

---

## Task 1 — Remove OCR/extraction pipeline (backend) `rnd.md §3.1` ✅ DONE

Delete the extraction pipeline entirely. File upload stays (T2).

### 1.1 — Delete whole files
- [ ] `backend/app/Jobs/ProcessDocumentExtraction.php`
- [ ] `backend/app/Services/OCRService.php`
- [ ] `backend/app/Services/ExtractionService.php`
- [ ] `backend/app/Models/OcrDocument.php`
- [ ] `backend/app/Models/ExtractionLog.php`
- [ ] `backend/app/Models/ExtractionTemplate.php`
- [ ] Migrations: `backend/database/migrations/2026_06_02_210745_create_ocr_documents_table.php`, `..._210747_create_extraction_templates_table.php`, `..._210748_create_extraction_logs_table.php`
- [ ] OCR/extraction tests, e.g. `backend/tests/Feature/OcrExtractionServiceTest.php` (grep `tests/` for `Ocr|Extraction|Screening` and remove extraction-specific assertions).
- [ ] **Grep sweep** for dangling references after deletes: `OcrDocument`, `ExtractionLog`, `ExtractionTemplate`, `ProcessDocumentExtraction`, `OCRService`, `ExtractionService`, `DocumentExtractionCompleted`. Remove `use` imports, event listeners, and any `config/` or service-provider bindings. Tree must compile.

### 1.2 — Strip extraction columns from `screening_documents`
- [ ] Edit migration `backend/database/migrations/2026_06_02_210746_*screening_documents*.php`: drop columns `extracted_data`, `mapped_fields`, `confidence_score`, `reviewed_by`, `reviewed_at`. Keep the file/patient/assessment linkage columns.
- [ ] `backend/app/Models/ScreeningDocument.php`: remove those from `$fillable`/`$casts` and remove the `reviewer()` relation. Keep `patient()` + `assessment()`.

### 1.3 — Rewrite upload controllers to plain storage
- [ ] `backend/app/Http/Controllers/RND/AssessmentController.php`:
  - `uploadScreening()` / `uploadLabs()` → **store file only**, link to NCP record, **no** `ProcessDocumentExtraction::dispatch`. Consolidate disk path to a single `documents/ncp` (was `documents/screening` + `documents/labs`).
  - `showScreeningDocument()` → return the document record **without** extraction fields.
  - **Delete** `showOcrDocuments()` and `showOcrDocumentFile()`.
- [ ] `backend/app/Http/Controllers/RND/ScreeningDocumentController.php`: **delete** `approve()`. Keep `show()`, `file()`, `normalizeDecimal()`.

### 1.4 — Trim routes
- [ ] `backend/routes/api.php` (~lines 84-91): remove `upload-labs` extraction wiring, `ocr-documents` (list + file), and `screening-documents/{id}/approve`. Keep upload + file-serve routes repurposed for plain attachments.

### 1.5 — Migrate fresh (pre-defense, approved)
- [ ] `cd backend && php artisan migrate:fresh --seed`. Confirm no OCR tables remain (`ocr_documents`, `extraction_templates`, `extraction_logs` gone).

---

## Task 2 — Repurpose upload as NCP attachments (backend + report) `rnd.md §3.1` ✅ DONE

- [ ] **Listing endpoint (per NCP cycle):** expose the NCP record's attachments (the retained `ScreeningDocument`/document rows) via a clean `GET /ncp-records/{ncpRecord}/attachments` **scoped to that `ncp_record_id`** — so attachments never mix across a patient's cycles (`rnd.md §3.1` new note). Reuse `ScreeningDocumentController::file()` for download. Add a `DELETE` for an attachment (RND-scoped).
- [ ] **Printed NCP report:** append a **"Supporting Documents"** section to `backend/resources/views/reports/ncp-summary.blade.php` — after the Monitoring & Evaluation table (~line 155), **before** signatories (~line 157). Loop the NCP record's attachments and list filename + upload date + type. (Image attachments may be embedded; non-images listed by name.)
- [ ] Test: an NCP record with 2 attachments renders both in the report and exposes them on the listing endpoint; a second cycle's attachments do **not** leak into the first (per-cycle scope).

---

## Task 3 — Assessment page refactor (frontend) `rnd.md §3.1` ✅ DONE

File: `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`.

- [ ] **Remove extraction UI/state/handlers:** polling state/refs (`pollingScreening`, `pollingLabs`, `*Ref`), `startScreeningPolling()`, `startLabsPolling()`, polling cleanup effect, confidence helpers (`confidenceBorder`), the **OCR Snapshot** panel, confidence badges, extraction status indicators, processing overlays, and the screening-approval branch inside `handleSave()`.
- [ ] **Simplify upload handlers** (`handleScreeningUpload`/`handleLabsUpload`): upload → immediate success, no polling. Keep clear/remove handlers (simplified).
- [ ] **Move attachments to the bottom:** referral section is currently mid-page (~lines 1316-1513). Render a single plain **Attachments** block (drag-drop + file list + download/delete) at the **bottom of the page, below the referral section**.
- [ ] `frontend/services/assessmentService.ts`: remove `OcrDocumentRecord`, `ScreeningDocumentApprovalPayload`, extraction fields on `ScreeningDocumentRecord`, and the `uploadLabsDocument`/`fetchOcrDocuments`/`approveScreeningDocument` calls. Keep/repoint upload + fetch to the plain-attachment endpoints (T2).
- [ ] **UI/UX + Tailwind pass** (ui-ux-pro-max + tailwind skills) on the new attachment block: clear empty state, file-type icons, consistent with the assessment page's existing card styling.
- [ ] Verify `npx tsc --noEmit` clean; no references to removed symbols remain.

### 3b — Patient-profile Attachments tab (per NCP cycle) `rnd.md §3.1 new note`
File: `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx` (existing tabs `overview` | `adime-records`, `TabKey` at line 19).
- [ ] Add a third tab **`attachments`** to `TabKey` + the tab nav (line ~381) + a tab panel.
- [ ] Panel lists the patient's NCP cycles (already in `records`), each cycle showing **its own** attachments fetched from `GET /ncp-records/{id}/attachments` (T2) — grouped/sectioned per `Cycle ID` so there's **no mix-up** across cycles. Download/remove per file.
- [ ] Reuse cycle-card styling already in the ADIME-records tab; empty state per cycle ("No documents attached").
- [ ] **⚠ Next.js note:** this repo's Next.js has breaking changes — per `frontend/AGENTS.md`, read `node_modules/next/dist/docs/` before writing/changing any frontend code (applies to all of T3, T3b, T6, T7, T8).

---

## Task 4 — Notifications service + two triggers (backend) `rnd.md §7` ✅ DONE

No schema changes — `notifications` table/model/controller/routes already exist (`api.php:145-148`).

- [ ] **`NotificationService`** (`backend/app/Services/NotificationService.php`): `notify(iterable $users, string $title, string $message, string $type, ?string $sourceModule, ?int $sourceId)` — bulk-insert one `Notification` row per user.
- [ ] **Trigger A — announcement posted:** in `RND/AnnouncementController::store()` (~lines 39-53), after the announcement is created, fan out by `announcements.visibility`:
  - `All` → all active users; `FSS` → FSS users; `Admin` → admins. (Enum has no `RND` value — fine.)
  - `type='announcement'`, `source_module='announcements'`, `source_id=$announcement->id`. Mirror the same hook in `Admin/AnnouncementController` if it doesn't inherit it.
- [ ] **Trigger B — upcoming follow-up:** new console command `notifications:follow-up-reminders` (`backend/app/Console/Commands/`): find monitorings whose **most recent** `next_monitoring_date` per NCP record = **tomorrow**; notify the owning RND. `type='follow_up'`, idempotent (don't double-send for the same date). Guard against duplicate rows on re-run.
- [ ] **Register schedule:** in `backend/bootstrap/app.php` add `->withSchedule(function (Schedule $schedule) { $schedule->command('notifications:follow-up-reminders')->dailyAt('07:00'); })`.
- [ ] Tests: announcement with each visibility fans out to the right cohort; follow-up command notifies only the day-before owner and is idempotent.

---

## Task 5 — Scheduler in Docker + remove dead OCR services `rnd.md §7` ✅ DONE (host-runner = deploy note)

> **DEPLOY NOTE:** backend is **not containerized** (only mysql/redis run as containers; backend runs on host PHP). The Laravel schedule is registered + verified (`schedule:list` shows `notifications:follow-up-reminders` daily 07:00), but a host-level runner must trigger it: either a Windows Task Scheduler entry running `php artisan schedule:run` every minute, or a persistent `php artisan schedule:work`. paddleocr/omr services + build dirs removed.

- [ ] **Locate the backend runtime.** `docker-compose.yml` defines only `mysql`, `redis`, `paddleocr`, `omr` — **no backend service**. Find where the Laravel app actually runs (separate compose file, Dockerfile, or host). Attach the scheduler there.
- [ ] **Add scheduler runner:** simplest reliable option — a process running `php artisan schedule:work` (or a cron entry calling `php artisan schedule:run` every minute) alongside the backend container. If `docker-entrypoint.sh` is the backend entry, add a supervised `schedule:work` process (don't block `apache2-foreground`).
- [ ] **Remove dead OCR infra:** delete `paddleocr` + `omr` services from `docker-compose.yml`; remove their port/network/healthcheck blocks. Delete the `paddleocr/` and `omr/` build-context dirs if nothing else references them (grep first).
- [ ] Verify: `php artisan schedule:list` shows `notifications:follow-up-reminders`; `docker compose config` is valid without paddleocr/omr.

---

## Task 6 — Notifications frontend `rnd.md §7` ✅ DONE

- [ ] **`frontend/app/(rnd)/notifications/page.tsx`:** replace scaffold with a real list against existing endpoints — `GET /api/rnd/notifications`, `PATCH .../{id}/read`, `PATCH .../read-all`. Show title/message/type/time, unread styling, mark-read + mark-all-read actions, empty state.
- [ ] **Bell badge:** wire the static badge in `frontend/components/layout/TopBar.tsx` (~lines 70-79) to a live unread count (poll or fetch on mount + after read). Hide badge when 0.
- [ ] **Service:** add `notificationService.ts` (list/read/readAll) if absent.
- [ ] **UI/UX + Tailwind pass.** Verify `npx tsc --noEmit`.

---

## Task 7 — Profile: self-service (backend + frontend) `rnd.md §9` ✅ DONE

Backend currently has **no** self-service profile/password endpoint (admin-only `Admin/UserController`). Add minimal self endpoints. **No photo** (no avatar column).

- [ ] **Backend endpoints** on the `auth:sanctum` group (`backend/routes/api.php` near the `/auth` block):
  - `PATCH /api/auth/profile` → update `name`, `email` (unique-ignoring-self) for `Auth::user()`. Form Request `UpdateProfileRequest`.
  - `POST /api/auth/password` → `current_password` (verify via `Hash::check`), `password` (confirmed, min 8). Form Request `UpdatePasswordRequest`.
  - Controller `Auth/ProfileController` (or extend `AuthController`).
- [ ] **`name` stays the report "prepared by" variable** — it already flows through `Auth::user()->name` in `ReportController.php:171`; no extra wiring, just confirm a name edit reflects on a freshly generated report.
- [ ] **Frontend `frontend/app/(rnd)/profile/page.tsx`** (new): edit form for name/email + change-password form. On success call `refreshUser()` (`frontend/contexts/AuthContext.tsx`) so the TopBar/name update live. Add a profile link in the TopBar/user menu.
- [ ] **Service:** `userService.ts` (or extend `authService.ts`) with `updateProfile`, `changePassword`.
- [ ] Tests (backend): profile update changes name/email; wrong `current_password` rejected; new password logs in.
- [ ] UI/UX + Tailwind pass; `npx tsc --noEmit`.

---

## Task 8 — Settings frontend `rnd.md §8` ✅ DONE (dark mode skipped — see Status log)

Backend has no settings table — build only what's backed, plus local-only UX prefs.

- [ ] **`frontend/app/(rnd)/settings/page.tsx`** (replace scaffold):
  - **Notification management** (backend-backed): reuse the notifications endpoints — mark-all-read, link to the notifications page; show unread count.
  - **Local-only preferences** (no backend; persist in `localStorage`): **dark mode / theme toggle**, **list density** (comfortable/compact). Apply via a small theme context/provider + Tailwind `dark:` variants (tailwind skill). Persist + rehydrate on load.
  - Link to **Profile** (T7) for account edits.
- [ ] Ensure dark-mode is honored app-wide (root class toggle) if a theme provider is introduced.
- [ ] UI/UX + Tailwind pass; `npx tsc --noEmit`.

---

## Verification (end-to-end)

- [ ] **Backend:** `cd backend && php artisan test` green; `php artisan migrate:fresh --seed` clean; `php artisan schedule:list` shows the reminder.
- [ ] **Frontend:** `cd frontend && npx tsc --noEmit` clean.
- [ ] **Manual flows:**
  - Upload a document on the assessment page → appears at the bottom (below referral) → shows in the printed NCP report's Supporting Documents.
  - Post an announcement (each visibility) → correct user cohort gets a notification; bell badge updates; notifications page lists it.
  - `php artisan notifications:follow-up-reminders` (with a monitoring whose `next_monitoring_date` = tomorrow) → owning RND notified; re-run sends no duplicate.
  - Profile: change name → reflected in TopBar and on a newly generated report's "prepared by".
  - Settings: toggle dark mode → persists across reload.
  - No dangling OCR references (grep `Ocr|Extraction|paddleocr|omr` → only this doc / historical notes).
