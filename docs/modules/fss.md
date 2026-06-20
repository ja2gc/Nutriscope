# FSS Role — Workflow (source of truth)

FSS (Food Service Staff) runs the **operational** side of food service — the kitchen/procurement counterpart to RND's planning. FSS shares the food-service API with RND under `/api/fss/*` (middleware `auth:sanctum, role:FSS,RND`). Baseline: FSS executes against the plan RND creates; RND owns planning, FSS owns execution.

> **For agentic workers:** consult `backend/.agents/skills/laravel-best-practices/skills.md` before writing or editing backend code. Follow that file's own "How to Apply" routing (map the file type you're touching — controller, Form Request, etc. — to its listed sections; delegate reading the actual rule files under `rules/` to a sub-agent, per the skill's instruction).

> **Doc status:** this file is the **source of truth** for the FSS role (for both agents and the client/reader). It supersedes the inline "change of mind" notes that previously lived here — those decisions are now folded into the body below, and the deltas they introduced are tracked in the **Revision log** at the end. The matching execution plan (tasks + file references) is [`fss-sprint-plan.md`](../superpowers/plans/fss-sprint-plan.md).

> **⚠ Cross-role impact:** some decisions here (budget-per-head calculation, the population model) are **not FSS-only** — they change how the shared food-service module behaves and therefore affect **RND** too. Those points are flagged inline with **[cross-role]**.

---

## 1. Dashboard (proposed contents)
FSS does not yet have a dedicated dashboard. Proposed widgets, tuned to the operational role:
- **Today's service** — the active menu cycle's meals for today + readiness (prepped/served).
- **Meal-prep to log** — days not yet marked served; one-tap "mark served".
- **Accomplishment log** — today's per-staff duty checklist + diet-list counts (see §4).
- **Procurement to action** — POs awaiting a receipt / proof upload.

## 2. Menu cycle (view + execute)
- View the active/saved menu cycles (`/menu-cycles`, `compute`) — the weekly plan, per-day meals, ingredients, prep, and cost/head.
- FSS executes against the **activated** cycle (its cost is frozen at activation).
- **Read-only for FSS.** Menu cycles are RND's planning artifact; FSS does not create or edit them.

## 3. Meal-prep logging + actual population (consumption)
- `meal-prep-logs` (index), `menu-cycles/{id}/complete-day` (mark a service day served → deducts the planned ingredients from inventory at last-cost; blocks on shortfall unless `allow_shortfall`), `meal-prep-logs/{id}/reverse` (undo).
- Surfaced today via the **ServiceLogPanel** on the menu-cycle editor; belongs on the FSS dashboard as the primary daily action.

**Actual population (the served-vs-estimated reconciliation).** Procurement is sized against RND's **estimate** of the population to be served. The **actual** served population is only known on the day, when staff collect and total the **diet lists**: each FSS staff member is assigned one ward's diet list, which states that ward's patient count for the day. The day's `served_population` is the **sum of all wards' counts** reported that day. FSS records this — it is the same variable RND reconciles against (estimate vs actual), so a day can come out short or over. Capture happens through the **accomplishment log** (§4), whose per-ward rows sum into `meal_prep_logs.served_population`.

**[cross-role] Budget-per-head-per-day — how it is calculated.** Two distinct figures, never to be conflated:
- **Planned cap** = `budget_per_head_day × population` (the *estimated/budgeted* population RND set when buying groceries). This is the ceiling, frozen at plan time. Owned by the **Budget** covering the cycle's week (population now lives on Budget, not the menu cycle — see Revision log).
- **Actual per-head** = value of food actually served ÷ actual headcount fed. Per day: `meal_prep_log.total_value ÷ served_population`. Across a span (e.g. a PO tied to a menu plan): **total food cost ÷ total patients served** over that span. The estimate only sets the cap; the actual is always served-value ÷ served-heads, never the estimate.
- This calc is **RND-facing too** — RND's cost-per-head reporting uses the same span definition (total cost ÷ total served over the PO/menu-plan span). Implemented in [`BudgetActualService`](../../backend/app/Services/BudgetActualService.php) (`per_head_actual`, daily `per_head`); the procurement-cash variant lives in `ProcurementCostEfficiencyService` (see Revision log for its scoping).

## 4. Accomplishment report — per-staff daily duty + diet-list log
Replaces the earlier standalone "supplies cleaning log". The hospital's **Accomplishment Report** form (`docs/Nutriscope Forms/accomplishment report for fss.jpg`) is a **per-staff weekly duty sheet**: one sheet per staff member, a grid of fixed task rows × days, each cell a ✓ / a number / "off-duty". It captures, in one form, **meal prep, supplies cleaning, AND food apportioned/distributed to in-patients across wards**.

The seven task rows:
1. Helped in food preparation work
2. Stored food supplies properly
3. Collected diet list from different wards
4. Apportioned and distributed food to in-patients in different wards *(carries the ward headcount number)*
5. Collected, cleaned and returned used utensils and dining equipment
6. Assumed duties as assistant cook
7. Maintained cleanliness of kitchen, cabinets, refrigerators and freezers

**Data model (decided — "per-staff tasks, day-level headcount" compromise).** Tasks are recorded **per staff per day** (the ✓ / off-duty marks). Headcount is **not** double-entered: each staff's diet-list row (row 4) carries the count they apportioned/distributed that day, and the day's rows **sum into the single `meal_prep_logs.served_population`** for that service date (§3). So the report sheet shows each staff's tasks plus the day's shared served total, and the existing served-vs-estimate model stays the single source of the headcount. The report generator joins per-staff task flags with the day's summed headcount. Capture surface + API shape: see [`fss-sprint-plan.md`](../superpowers/plans/fss-sprint-plan.md) (Accomplishment Report tasks).

**Form details (from the template image):** one sheet per staff, ~15-day pay-period span (e.g. *May 01–15*), columns = calendar days, seven fixed task rows, cells = ✓ (task done) / a number (row 4 — heads distributed) / "off-duty". Signature blocks: **Prepared by** (the staff), **Noted by** (RND / Section Head), **Approved by** (Administrative Officer).

**Why this matters to the whole operation — the closed actual-cost loop.** The accomplishment report is not a side compliance form; it is the **data-entry vehicle for the actual served population**, which the entire budget-actual machinery depends on:
1. RND plans a menu cycle + **estimates** population → freezes the budget cap (`budget_per_head_day × estimated population`).
2. Procurement buys groceries sized to that estimate.
3. Daily, FSS collects diet lists per ward (row 3) and apportions/distributes food (row 4 = **actual** count).
4. Row-4 counts **sum → `meal_prep_logs.served_population`** (the actual headcount).
5. `complete-day` deducts inventory at last-cost; shortfall/variance vs the estimate **notifies RND** (already wired).
6. [`BudgetActualService`](../../backend/app/Services/BudgetActualService.php) computes **actual per-head = served value ÷ served_population**, and the planned-vs-actual variance becomes visible to RND.

So the same numbers that satisfy the hospital's compliance form are the numbers that close the estimate→actual cost loop. Build the capture (§4) and the rest of the food-service cost machinery feeds itself.

## 5. Procurement — receipts/proof only (no PO authoring)
- **FSS does NOT create or edit purchase orders or build procurement.** PO authoring and shopping-list construction are RND/planning actions. Any FSS-side PO/shopping-list-item *create* path is out of scope and must be removed or RND-gated (see Revision log: `ShoppingListController@storeItem`).
- FSS can **upload receipts / proof of purchase** against a **pre-existing** PO: `POST /purchase-orders/{id}/attachments` (type `receipt` | `proof`, image, optional caption), `DELETE /purchase-order-attachments/{id}`. Files on the public disk.
- **Key flow:** RND creates a PO without a receipt; FSS later uploads the proof of purchase against it. The Procurement page lists POs and supports the attachment upload. The upload UX mirrors RND's receipt-upload flow (same endpoint/field shape).

## 6. Inventory (only) — suppliers / budget / insights dropped for FSS
- **Inventory** — view/update stock, restock, no-stock flags; valued at stored last-cost. **In scope.**
- **Suppliers, Budget, Insights are NOT in FSS scope.** Supplier CRUD, budget planned-vs-actual views, and spend/cost-per-head analytics are RND-owned. Any FSS-exposed supplier/budget/insights surface is out of scope; remove if present (see Revision log).

## 7. Announcements (put this below dashboard in the navigation)
FSS sees announcements with visibility `FSS` or `All`, as a read-only **feed placed below** the dashboard content (not a manager — FSS cannot author or pin). Mirror RND's announcement feed presentation; reuse the shared announcement component rather than a bespoke one.

## 8. Reports — accomplishment report only
FSS's only report is the **Accomplishment Report** (§4). All other operational/clinical report types (Program Project Activity, Menu Calendar, Dietary Cash Book, Procurement Pack, Budget, Inventory, and every clinical type) are **not** FSS reports. Any FSS-facing report-browser access or FSS-only report backend beyond the accomplishment report is out of scope; remove if present. *(Note: the frontend Reports browser is RND-only today — `(rnd)/reports` + `/api/rnd/reports/*`; no FSS report page exists, so there is nothing to trim on the FSS frontend. The cleanup, if any, is backend-side shared generators.)*

## 9. Notifications (planned)
Same backend as RND ([`rnd.md`](rnd.md) §6–7). Useful FSS auto-events: no stock, PO received / awaiting receipt, service day not yet logged, menu activation, shortfall/variance (already wired — `ConsumptionService::completeDay` writes `meal_prep_shortfall` / `meal_prep_variance` notifications to the cycle's RND).

---

## Suggested improvements (for consideration)
- **Receiving-first PO view** for FSS: a queue of POs awaiting proof, separate from any planning PO list.
- **Shortfall alerts** when `complete-day` is blocked, routed to the dashboard.
- **Accomplishment log + meal-prep** as the two anchor daily tasks on the dashboard.
- **Read-only vs edit split:** the shared `/fss` routes give both roles full access; FSS writes should be limited to inventory, receipts, meal-prep logging, and the accomplishment log — everything else RND-gated.

---

## Revision log (change-of-mind deltas folded into the body above)

These are the decisions the client added as notes after the original draft. They are the reason Phase A and the codex/antigravity work (`baf8fbf`→HEAD) are partly out of alignment — both predate or ignore these. The execution-side revert/fix tasks live in [`fss-sprint-plan.md`](../superpowers/plans/fss-sprint-plan.md).

- **Procurement (§5):** FSS reduced to *receipts/proof on existing POs only* — no PO/shopping-list authoring. ⇒ revert codex's `ShoppingListController@storeItem` + `POST shopping-lists/{}/items` route (or RND-gate it).
- **Scope strip (§6):** suppliers, budget, insights removed from FSS. ⇒ `ProcurementCostEfficiencyService` must not be surfaced as an FSS insights endpoint; its cost÷served math is retained only insofar as it serves the **[cross-role]** budget-per-head calc (§3), re-homed to the budget/reporting path, not an FSS analytics page.
- **Cleaning log → Accomplishment report (§4):** the standalone cleaning log is replaced by the per-staff accomplishment sheet (template now provided).
- **Population model:** `population` / `budget_per_head_per_day` moved **off** `menu_cycles` onto **`budgets`** (codex migration `drop_population_and_budget_per_head_from_menu_cycles` + `add_menu_cycle_id_to_budgets`). This is **aligned** — keep. Actual headcount lives on `meal_prep_logs.served_population` (§3).
- **Budget calc (§3) [cross-role]:** per-head actual = served value ÷ served heads; span cost/head = total cost ÷ total served over the PO/menu-plan span. Affects RND reporting too.
- **Reports (§8):** accomplishment report only for FSS.
</content>
</invoke>
