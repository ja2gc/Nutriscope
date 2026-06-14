# System Review — flaws & future-revision notes (2026-06-14)

Observations gathered while writing the role workflow docs. **These are notes for future revision, not fixed in this pass.** The food-service → reports path was just reviewed/revised (Spec 1–6 + reports overhaul); the **NCP side has not been reviewed in a long time** and carries the most risk. Severity is a rough triage. File references are approximate (verify before acting).

---

## A. NCP (clinical) — highest priority

### A1. Monitoring is not gated to follow-up visits — HIGH (logical)
The workflow intends monitoring for the **2nd visit onward**, and `InterventionController` even has a comment to that effect, but **no code enforces it**: `MonitoringController::store` has no guard, so a monitoring row can be created on the first encounter. Related: `NcpRecord.type` (`new|followup|reassessment`) is set to `new` at `startNcpCycle` and **never updated**, so the type-based gate is effectively dead. *Fix idea:* require ≥1 completed A/D/I (or `status='active'`) before allowing monitoring, and/or drive a real `type`/visit-count.

### A2. NCP steps can be done out of order / skipped — MEDIUM (logical)
No prerequisite checks: Diagnosis doesn't require an Assessment, Intervention doesn't require a Diagnosis, Monitoring doesn't require an Intervention. Only **deletion** is guarded (can't delete an NCP once A+D+I exist). `MealPlanController::store` (manual) doesn't require an Intervention while `generate` does — inconsistent. *Fix idea:* enforce stage prerequisites on create.

### A3. No per-patient scoping on NCP data — HIGH (security/authorization)
Records are resolved by route-model binding with **only `role:RND`** as the gate. Any RND user can read/edit **any** patient's NCP, diagnoses, intervention, or meal plans by guessing/iterating IDs. The `Store*Request::authorize()` methods all `return true`. *Fix idea:* scope to the patient's assigned RND (or an explicit care-team), at least on clinical records.

### A4. AI diagnoses accepted without validation — MEDIUM (logical/integrity)
`AiDiagnosisController::aiApprove` stores AI-suggested PES directly (`ai_generated=true`) with no clinical-review guard, confidences not persisted, and no audit link back to the suggestion call. *Fix idea:* require explicit field confirmation + keep the confidence/reasoning provenance.

### A5. Monitoring AI rate-limit is bypassable — LOW (cost/security)
The limiter key includes the NCP id (`ai-review:{user}:{ncp}`), so a user can exceed the intended cap by switching NCPs. *Fix idea:* per-user (and/or per-hour) global limit.

### A6. `upload-labs` may clobber the Assessment — LOW (data integrity)
`firstOrCreate(['ncp_record_id' => …])` can create a second/blank Assessment path if ordering differs from the screening upload. *Fix idea:* reuse the existing Assessment or error clearly.

### A7. Hardcoded lab reference ranges — LOW (clinical correctness)
`MonitoringSummaryService::LAB_RANGES` are fixed (not patient-specific: pediatric, CKD stage, etc.), so delta flags can be clinically wrong at the edges. *Fix idea:* parameterize by patient context.

### A8. OCR file paths stored absolute with relative fallback — LOW (portability)
Screening/OCR file paths are stored as absolute paths with a `storage_path('app/'…)` fallback; brittle if the app root moves. *Fix idea:* store disk-relative paths consistently.

### A9. Meal-plan item validation — LOW (integrity)
`MealPlanItemController` doesn't validate `day ∈ Mon–Sun` / `meal_type ∈ {breakfast,am_snack,lunch,pm_snack,dinner}`. *Fix idea:* validate against the enums.

---

## B. FSS / Admin / cross-cutting

### B1. PurchaseOrder user column — VERIFY (potential schema/code mismatch)
An agent flagged the original PO migration as defining `rnd_user_id` while the controller/factory use `fss_user_id`. PO creation currently works (tests + browser), so a later migration likely renamed it — **verify** the live `purchase_orders` schema vs `PurchaseOrderController` before trusting either way. If a stray `rnd_user_id` column lingers, reconcile.

### B2. FSS cannot see announcements — MEDIUM (logical)
`Announcement.visibility` includes `FSS`, but the announcements index is only wired into the RND/Admin groups; there's no FSS-reachable announcements route/filter. FSS-targeted posts are currently unreachable by FSS. *Fix idea:* add an FSS-visible announcements endpoint filtering `visibility ∈ {FSS, All}`.

### B3. Notifications are never created — MEDIUM (incomplete feature)
`NotificationController` only reads/marks-read; nothing writes notifications, so the page is always empty. *Fix idea:* a notification service emitting on events (PO received, low stock, budget exceeded, follow-up due).

### B4. Calendar & Notification UIs are scaffolds — MEDIUM (incomplete feature)
Backend models/endpoints exist; frontend pages are stubs. Auto-event generation (follow-ups, expiry, activation, budget deadlines) is not implemented. *Fix idea:* implement per the planned flow in the role docs.

### B5. Admin audit-log endpoint is unpaginated/unfiltered — MEDIUM (performance/privacy)
`AdminAuditLogController::index` returns the entire activity log with no pagination or filtering — slow at scale and broad PHI exposure surface. *Fix idea:* paginate + filter (date, actor, model).

### B6. No Admin frontend — MEDIUM (incomplete feature)
`/admin/*` endpoints exist but there is no `(admin)` UI for RBAC, audit logs, or settings. *Fix idea:* build the admin console (users/RBAC + audit browser first).

### B7. Weak role separation on `/fss` — LOW (design)
All food-service routes are `role:FSS,RND`; RND has full operational access (receiving, consumption logging). Decide intended boundary (RND read-only on operations?) and enforce.

### B8. Suppliers / menu cycles / budgets not audited — LOW (audit coverage)
Spec 5 audited inventory/PO/patients/etc., but suppliers, shopping lists, menu cycles, and budgets aren't all surfaced in history panels. *Fix idea:* extend audit/history coverage where useful.

---

## C. Notes for the workflow docs (kept out of them on purpose)
- The reports model is now **browse-don't-generate** with **auto-frozen values**; the older "ADIME Individual/Aggregate, NCP Census" report naming in prior docs has been superseded by the current type set (PPA, Menu Calendar, Cash Book, Procurement Pack, Budget, Inventory + clinical Demographic Census, Patient Menu Plan, NCP Summary).
- Prior docs referenced Sonnet for NCP AI; the code currently uses Haiku (`claude-haiku-4-5-20251001`) for both diagnosis suggestion and monitoring narration.
