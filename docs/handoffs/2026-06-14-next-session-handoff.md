# Handoff — Reports/Budget/FSS concerns + deferred review items (2026-06-14)

Branch `main` (pushed to origin). Full PHP suite **442/442**, frontend `tsc` clean. This doc is a **plan / notes only — nothing here is implemented yet.** It captures the open concerns, what we can realistically do about each, and the files involved, so the work can continue in a fresh chat.

> **2026-06-15 progress (done + verified, 452/452 PHP, tsc clean):**
> - **A1** report data-source audit → [`docs/reviews/2026-06-15-report-data-source-audit.md`](../reviews/2026-06-15-report-data-source-audit.md). Only gap: Dietary Cash Book replenishment side (ties to A5).
> - **A6** variance sign/label clarified (budget page KPI + `budget.blade.php`): absolute amount + explicit "over/under" labels; negative "Remaining" → "Over Allocation".
> - **A7** annual budget surfaced in the Dietary Cash Book header (`DietaryCashBookGenerator::annualBudget`).
> - **A8** per-day population captured on `meal_prep_logs.population` (migration + `ConsumptionService`); `BudgetActualService` now returns `avg_population` + `per_head_actual`, surfaced on the budget page; RND dashboard **Budget Per Person** KPI now wired to real budgets.
> - **NCP A2** step-order enforced: Diagnosis (manual + AI-approve) requires an Assessment; Intervention requires a Diagnosis (Monitoring→Intervention already done).
> - **NCP A7** lab ranges now sex-aware (`MonitoringSummaryService::rangesFor`).
> - **NCP A8** screening/OCR file paths now stored disk-relative.
> - **A3** per-patient scoping: **accepted as-is (single-tenant)** — see the system-review doc.
> - **Admin**: sprint plan written → [`docs/superpowers/plans/2026-06-15-admin-console-sprint.md`](../superpowers/plans/2026-06-15-admin-console-sprint.md) (build deferred to a later session).
>
> **2026-06-15 progress (batch 2 — remaining handoffs, 455/455 PHP, tsc clean):**
> - **A2** done: daily budget **forecast** widget (burn-rate → projected period spend vs allocation) on the budget page; **server-side SVG** planned-vs-actual chart in the budget PDF (`budget.blade.php`; DomPDF-safe, no JS).
> - **A3** done: **Insights** folded into a Budget tab (`components/foodservice/InsightsPanel.tsx`), **Suppliers** folded into a Procurement tab (`SuppliersPanel.tsx`); standalone routes + Sidebar links removed.
> - **A5** done: Dietary Cash Book replenishment now **derived from Budget allocations** overlapping the period (reproducible; closes the last A1 gap). Explicit `replenishment` param still overrides for back-compat.
> - **Still open:** FSS build-out (not this dev's part); Admin console build (per the sprint). The clinical/budget/reports handoff items are now all addressed.
>
> Conventions: work on `main`; **NO `Co-Authored-By`** (author = jared). Verify with `cd backend && php artisan test` (sqlite, 442 baseline; `phpunit.xml` sets `memory_limit=512M` for DomPDF) + `cd frontend && npx tsc --noEmit` (ignore the pre-existing `.next/dev/types/validator.ts` artifact). MySQL/Docker on :3306 for browser checks; app servers run on the host (`php artisan serve :8000` + `npm run dev` → :3000), not in Docker. Browser-test via the Claude extension (real keystroke login works; `preview_fill` doesn't). Dev login `rnd@nutriscope.local` / `nutriscope2024!`.

---

## A. Open concerns from this session (verbatim + analysis)

### A1. "Can all report data be generated from actual inputs collected during food-service operations, or are some values currently hardcoded/seeded and not reproducible during normal use?"
**Do a data-source audit of every generator** in `backend/app/Services/Reports/Generators/`. Classify each field as: (a) live record, (b) config (branding/signatories — editable, OK), or (c) **report-time param not backed by any stored record** (the problem case).
- Known live: Procurement Pack, Dietary Cash Book *disbursements*, Inventory, Budget *actuals*, PPA/Menu Calendar (now snapshot-frozen), NCP Summary, Census, Patient Menu Plan — all query real models.
- Known **param-only (not reproducible)**: Dietary Cash Book's `beginning_balance`, `replenishment`, `replenishment_ref`, `accountable_officer` are passed as report parameters (see `DietaryCashBookGenerator::data()`), with no underlying ledger record. → ties to A5/A7 below.
- Deliverable: a short table per report (field → source). Files: `Generators/*`, `resources/views/reports/*.blade.php`.

### A2. "Reports should include graphs, and display the daily budget forecast on the Budget page."
- **Graphs in PDF reports:** DomPDF can't run JS charts. Options: (i) render charts server-side to PNG/SVG (e.g. a small chart-image service or an SVG builder) and embed via `<img>` in the blade (same pattern as the new procurement photo appendix); (ii) keep analytical graphs in the web **Insights** view only. Recommend (i) for the reports that warrant a trend (Budget, Consumption). Decide scope per report.
- **Daily budget forecast on the Budget page:** add a forecast chart/widget on `frontend/app/(rnd)/food-service/budget/page.tsx` projecting spend-to-date vs allocated over the period (burn-rate → projected end). Source from `BudgetActualService::dailySeries`. Files: `backend/app/Services/BudgetActualService.php`, `BudgetController::summary`, the budget page.

### A3. "Merge Insights and Suppliers pages into existing pages as tabs (reduce page bloat)."
- **Suppliers → tab on Procurement** (suppliers are used by POs): `frontend/app/(rnd)/food-service/procurement/page.tsx` gains a "Suppliers" tab; remove the standalone `suppliers/page.tsx` route + Sidebar entry.
- **Insights → tab on Budget** (or Dashboard): fold `food-service/insights/page.tsx` charts into a tab on the Budget page; remove the standalone route + Sidebar entry.
- Files: `frontend/components/layout/Sidebar.tsx` (remove 2 nav links), the four food-service pages. Keep the backend endpoints unchanged.

### A4. "Budget reports should reflect real operational data, not hardcoded/seeded — BUT if the seeded values are saved across food service (not just the reports), it's OK."
- `BudgetActualService::dailySeries` already sources **actual** from consumption (`MealPrepLog`) or received POs, and **planned** from the `Budget` record (user-creatable via Budget CRUD). So budget figures ARE reproducible via normal use (create a Budget, receive POs / log meals). Demo seed values live in `Budget`/`MealPrepLog` rows (saved across food service), which satisfies the caveat.
- Deliverable: confirm (as part of A1's audit) that no budget figure is report-param-only. Files: `BudgetActualService`, `Models/Budget`, `BudgetDailyLog`.

### A5. "The cashbook values — can we really do that inside the food-service part, or would that need its own separate workflow?"
- The Dietary Cash Book is a **cash disbursement ledger**: Date · Ref/OR · Payee · Nature · **Cash Advance/Replenishment** · Disbursements · running Balance. Disbursements = received POs (real). But the **cash-advance/replenishment + beginning balance** side has **no workflow** — currently report params only.
- **Decision needed:** to make the cashbook fully real, add a small **cash ledger workflow** (record cash advances/replenishments to the accountable officer, dated, with OR ref) — its own model/migration + UI — OR treat `Budget` allocations as the replenishment source. Today `BudgetDailyLog` holds only `log_date`/`spent`/`notes` (manual spend), not replenishments.
- Files: `DietaryCashBookGenerator`, possibly a new `cash_ledger` model/migration + a Food-Service "Cash Book" page, or extend `BudgetDailyLog`.

### A6. "I think there's something wrong with variance since I'm seeing a negative value."
- Variance = planned − actual (or `allocated − cash_flow`). A **negative value legitimately means overspend**, but it may be a sign/label bug or genuinely miscomputed. **Audit:** `BudgetActualService` (variance/`remaining` calc), `resources/views/reports/budget.blade.php`, `BudgetController::summary`, and the budget page display. Clarify the sign + label (e.g. "Over budget by ₱X" in red vs "Remaining ₱Y"). Confirm whether the negative is correct-but-confusing or an actual error (check that planned and actual cover the same period + same axis — recall the cash-vs-consumption axis note in Spec 6 §5-D).

### A7. "We should be able to see the budget for the year (shown in the cashbook)."
- Surface the **annual budget** in the cashbook (and/or budget page). `Budget` has `scope` + period; need to resolve the year's allocation and show it as context in `DietaryCashBookGenerator` / `dietary-cash-book.blade.php` header. May require a yearly-scoped Budget record or aggregation.

### A8. "We should see the population on that day (population changes daily) and how that reflects budget-per-head-per-day."
- Today population is a single value on `MenuCycle.population` / `Budget.population`; there's **no per-day headcount**. To show "population on that day" + correct budget-per-head-per-day, add a **daily population/headcount capture** (per service day) — likely on `MealPrepLog` (add a `population`/`headcount` column) or a small daily-census table — and compute budget-per-head-per-day from the actual day's headcount.
- Files: `MealPrepLog` model + migration, `ConsumptionService`/`MealPrepLogController` (capture headcount on complete-day), `MenuCycleCostService` (per-head uses day headcount), budget page + budget report.

---

## B. Deferred review flaws (from `docs/reviews/2026-06-14-system-review.md`)

**RND (real, not yet done):**
- **A2** strict NCP step-order (assessment→diagnosis→intervention) — bigger change, updates ~6 NCP feature tests. *(monitoring follow-up gate already done.)*
- **A3** no per-patient scoping — any RND can open any patient by ID. Decide: single-tenant trusted-RND (accept) vs scope to assigned RND/care-team.
- **A7** hardcoded lab reference ranges in `MonitoringSummaryService`. **A8** OCR file paths stored absolute.

**FSS / Admin / cross-cutting:**
- **B1** verify `purchase_orders` user column (`rnd_user_id` migration vs `fss_user_id` code — POs work, so likely already reconciled; confirm).
- **B2** FSS can't see announcements (visibility `FSS` unreachable). **B3** notifications never created. **B4** calendar/notification UIs are scaffolds. **B5** admin audit-log endpoint unpaginated. **B6** no Admin frontend. **B7** weak `/fss` role separation (RND has full operational access). **B8** audit/history coverage gaps (suppliers, menu cycles, budgets).

---

## C. FSS / Admin / Calendar / Notification build-out (from workflow docs)
Per `docs/modules/{fss,admin}.md` (revised this session):
- **FSS:** dedicated dashboard (today's service, meal-prep to log, supplies-cleaning log, inventory alerts, POs needing receipts, budget snapshot, announcements); **supplies-cleaning log** capture → a periodic cleaning report (**template pending from user**); receiving-first PO queue. *(Multi-file receipt upload + PO edit already done.)*
- **Admin:** no frontend yet — build user/RBAC manager + audit-log browser first, then settings + token-usage.
- **Calendar/Notifications:** implement the planned auto-events + a notification-emitting service (currently backend models exist, UIs are stubs, nothing writes notifications).

---

## D. Suggested sequencing for the next session
1. **A1 data-source audit** (cheap, informs everything) → produces the field→source table.
2. **A6 variance** (likely quick: sign/label or real bug) + **A7 yearly budget** + **A8 daily population** (these three are the budget cluster).
3. **A2 graphs + daily forecast** (Budget page first; report graphs second).
4. **A3 merge Insights/Suppliers into tabs** (UI cleanup).
5. **A5 cashbook cash-ledger** (needs a workflow decision first — confirm with user).
6. Then the deferred review flaws (B-list) + FSS/Admin/Calendar build-out as separate efforts.

## E. Key files index
- Reports: `backend/app/Services/Reports/{ReportService,ReportBrowser,Contracts/*,Instances/*,Generators/*}.php`, `backend/resources/views/reports/*.blade.php`, `backend/app/Http/Controllers/ReportController.php`, `backend/routes/api.php`.
- Budget: `backend/app/Services/{BudgetActualService,BudgetService,MenuCycleCostService}.php`, `backend/app/Http/Controllers/FSS/BudgetController.php`, `backend/app/Models/{Budget,BudgetDailyLog,MealPrepLog,MenuCycle}.php`, `backend/resources/views/reports/budget.blade.php`, `frontend/app/(rnd)/food-service/budget/page.tsx`.
- Insights/Suppliers: `backend/app/Http/Controllers/FSS/{InsightsController,SupplierController}.php`, `frontend/app/(rnd)/food-service/{insights,suppliers,procurement}/page.tsx`, `frontend/components/layout/Sidebar.tsx`.
- NCP: `backend/app/Http/Controllers/RND/*`, `backend/app/Models/{NcpRecord,Assessment,Diagnosis,Intervention,Monitoring,BiochemicalData}.php`, `backend/app/Services/{MonitoringSummaryService,NutritionPrescriptionService,MealPlanService,RecommendService,AIService}.php`, `frontend/app/(rnd)/ncp/**`.
- Reviews/specs/docs: `docs/reviews/2026-06-14-system-review.md`, `docs/modules/{rnd,fss,admin}.md`, `docs/superpowers/specs/2026-06-*`.
