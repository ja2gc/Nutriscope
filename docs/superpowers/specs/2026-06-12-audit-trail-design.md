# Spec 5 — App-Wide Audit Trail (change history + per-record UI)

- **Date:** 2026-06-12
- **Status:** ✅ BACKEND IMPLEMENTED 2026-06-14 (Part 1). Frontend History panel = Part 2 (pending). Plan: `docs/superpowers/plans/2026-06-14-audit-trail-backend.md`
- **Scope:** whole app (clinical + food-service), not just FS
- **Roadmap:** Spec 5 of 5 (see [Spec 1](2026-06-12-fs-costing-immutability-design.md))

---

## 1. Background — what exists vs. what's claimed

- ✅ `spatie/laravel-activitylog ^4.12` installed; `activity_log` table migrated (`event`, `batch_uuid`).
- ✅ `AuditMiddleware` is wired onto the RND route group ([api.php:46](../../../backend/routes/api.php#L46)) — it logs **access**: causer + url + method + ip, `"Accessed {path}"`, for **every** request (incl. GET reads).
- ✅ Admin can list logs: `GET /api/admin/audit-logs`.
- ❌ **No model uses the `LogsActivity` trait** → there is **no change-level audit**: no created-by/edited-by, no field-level old→new diffs tied to a subject record.
- ⚠️ **Doc inaccuracy:** [docs/security/security.md:13](../../../docs/security/security.md#L13) claims *"Audit logging on all sensitive models via spatie/laravel-activitylog."* That is **false today** — it's route-level access logging only. This spec makes the claim true and corrects the wording.

**The gap = subject-centric change history.** "Who created this report, who dealt with this patient, who changed this PO" is what the user wants, surfaced per-record.

## 2. Goals / Non-goals

**Goals**
1. Add `LogsActivity` to sensitive models: created/updated/deleted events, **field-level diffs**, **causer** auto-captured from auth.
2. Per-record **history panel** in the UI (timeline: who, when, what changed).
3. Keep (and tighten) the access logging; keep the admin global view.
4. Correct `security.md`.

**Non-goals**
- A full SIEM / tamper-proof WORM store (activity_log is app-level, not forensic).
- Auditing every trivial table (lookup/config tables excluded).

## 3. Design

### 3.1 Models to instrument
Clinical: `Patient`, `NcpRecord`, `Assessment`, `Diagnosis`, `Intervention`, `Monitoring`, `MealPlan`.
Food-service: `PurchaseOrder`, `ShoppingList`, `FsItem`, `Inventory`, `FoodServiceRecipe`, `MenuCycle`, `Budget`, `MealPrepLog`.
Reports: custom `downloaded` / `archived` events (Spec 4), plus `Report` archive create.

Each via the trait with `logOnlyDirty()`, an explicit `logFillable`/attribute allow-list (don't log internal/computed columns), and `dontSubmitEmptyLogs()`.

### 3.2 Causer
`User` uses `CausesActivity`; the auth user is auto-attached. **System/AI/job actions** (no user — e.g. AIService, queued report jobs) attribute to a sentinel "system" causer so the trail has no null gaps.

### 3.3 Read API + UI
- `GET /{resource}/{id}/activity` → that subject's change history (authorized by role).
- UI: a collapsible **History** panel/timeline on patient, NCP, PO, recipe, etc. — "Jane edited Assessment · weight 60→62kg · 2026-06-12 14:03".

### 3.4 Tighten access logging
Current middleware logs **all** requests including reads → high-volume noise. Restrict access logging to **mutations** (POST/PUT/PATCH/DELETE) by default, or keep reads only for explicitly sensitive endpoints (e.g. viewing a patient chart). Decision §8.

## 4. Data model
No new tables (activity_log exists). Add a config/const list of audited models + per-model attribute allow-lists. Possibly an index on `(subject_type, subject_id, created_at)` for fast per-record history (verify the package's default indexes cover it).

## 5. Error handling
- Audit writes must **never** break the business action — log failures are swallowed/queued, not fatal.
- High-volume safety: consider queuing activity writes; ensure the causer/properties survive the queue.

## 6. Testing
- Editing an audited model writes one activity with correct causer + dirty diff.
- Reverting / no-op save writes nothing (`logOnlyDirty` + `dontSubmitEmptyLogs`).
- System action attributes to the sentinel causer, not null.
- Per-record endpoint returns only that subject's history and is access-controlled.

## 7. Flaws / risks I want to flag
1. **PHI in diffs (biggest one):** logging old→new for clinical fields stores patient health data in `activity_log` — a privacy/compliance exposure, and it widens where PHI lives. Options: log that a field changed **without values** for sensitive clinical attributes, or encrypt/restrict the audit store. **Must decide before instrumenting clinical models.**
2. **Volume & retention:** field-level logging on hot tables grows fast; without a retention/pruning policy `activity_log` balloons. Need a documented retention window + prune job.
3. **Access-log noise:** logging every GET makes the trail hard to read and inflates volume; restricting to mutations is almost certainly right.
4. **Causer gaps:** background jobs/AI without the sentinel will write null-causer rows that look like "nobody did it."
5. **Not tamper-proof:** anyone with DB/admin write access can alter `activity_log`. Fine for an internal trail; don't oversell it as forensic in `security.md`.

## 8. Decisions

**Locked:**
- **Decision A — PHI handling: FIELDS-ONLY FOR CLINICAL.** Clinical/patient models (`Patient`, `NcpRecord`, `Assessment`, `Diagnosis`, `Intervention`, `Monitoring`, `MealPlan`) log **which fields changed + who + when, but NOT the before/after values**. Operational/food-service models log **full values**. This keeps PHI out of `activity_log` while preserving "who did what." Implementation: a per-model "redacted attributes" set; for redacted fields, record the attribute name in the change set with values stripped.
- **Decision B — access-log scope: MUTATIONS ONLY.** Log `POST/PUT/PATCH/DELETE` by default; drop routine GET noise. (Sensitive-read logging can be added later per-endpoint if a HIPAA-style access trail is required.)

**Still open (minor, non-blocking):**
- **Decision C — retention:** keep-window + prune vs archive for `activity_log` (propose: documented retention window with a scheduled prune job).
- **Decision D — surfacing breadth:** start the per-record history UI on the highest-value models (`Patient`, `PurchaseOrder`, `Inventory`) and expand, vs all at once.
