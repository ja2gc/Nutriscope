# FSS Role — Workflow (current state + intended scope)

FSS (Food Service Staff) runs the **operational** side of food service — the kitchen/procurement counterpart to RND's planning. FSS shares the food-service API with RND under `/api/fss/*` (middleware `auth:sanctum, role:FSS,RND`). The baseline: FSS is connected to RND's food-service side and executes against the plan RND creates.

> Scope note: describes how the role flows. Known gaps/risks live in [`docs/reviews/2026-06-14-system-review.md`](../reviews/2026-06-14-system-review.md).

---

## 1. Dashboard (proposed contents)
FSS does not yet have a dedicated dashboard. Proposed widgets, tuned to the operational role:
- **Today's service** — the active menu cycle's meals for today + readiness (which have been prepped/served).
- **Meal-prep to log** — days not yet marked served; one-tap "mark served".
- **Supplies cleaning log** — today's cleaning checklist (see §4, planned).
- **Inventory alerts** — low / out-of-stock items (red/amber/green).
- **Procurement to action** — POs awaiting receiving or missing a receipt/proof upload.
- **Budget snapshot** — spend-vs-allocated for the current period.
- **Announcements** — posts targeted to FSS (see §7).

## 2. Menu cycle (view + execute)
- View the active/saved menu cycles (`/menu-cycles`, `compute`) — the weekly plan, per-day meals, ingredients, prep, and cost/head.
- FSS executes against the **activated** cycle (its cost is frozen at activation).

## 3. Meal-prep logging (consumption)
- `meal-prep-logs` (index), `menu-cycles/{id}/complete-day` (mark a service day served → deducts the planned ingredients from inventory at last-cost; blocks on shortfall), `meal-prep-logs/{id}/reverse` (undo).
- Surfaced today via the **ServiceLogPanel** on the menu-cycle editor; belongs on the FSS dashboard as the primary daily action.

## 4. Supplies cleaning log (PLANNED — report template pending)
Intended: FSS records the **supplies/equipment cleaned that day** (a daily sanitation checklist). These entries accumulate and become a periodic **cleaning report**. The report layout/template is **not yet defined** (to be provided by the hospital) — build the data capture first; the report generator slots into the existing Reports browser once the template arrives.

## 5. Procurement (edit + receipts)
- FSS can **create and edit purchase orders** (`/purchase-orders` CRUD) and generate them from shopping lists.
- FSS can **upload receipts / proof of purchase** to a PO: `POST /purchase-orders/{id}/attachments` (type `receipt` | `proof`, image, optional caption), `DELETE /purchase-order-attachments/{id}`. Files stored under the public disk.
- **Key flow:** RND may create a PO without a receipt; FSS later uploads the proof of purchase against it. The Procurement page lists POs and supports the attachment upload.
- Shopping lists: generate suggested net-of-stock lists, edit line items, turn into POs.

## 6. Inventory, suppliers, budget, insights
- **Inventory** — view/update stock, restock, low-stock flags; valued at stored last-cost.
- **Suppliers** — CRUD.
- **Budget** — view planned-vs-actual, daily logs, summary (actuals can source from consumption or purchases).
- **Insights** — spend-by-supplier, cost-per-head, consumption charts (read-only analytics, distinct from the compliance PDFs).

## 7. Announcements
FSS should see announcements with visibility `FSS` or `All`. (Currently the announcements index is wired for RND/Admin only — see the review doc; intended flow is an FSS-visible feed.)

## 8. Reports
FSS reaches the same Reports browser for **operational** report types (Program Project Activity, Menu Calendar, Dietary Cash Book, Procurement Pack, Budget, Inventory). Clinical report types (Patient Menu Plan, Demographic Census, NCP Summary) are **RND-only** and blocked for FSS.

## 9. Calendar & Notifications (planned)
Same backend as RND (§6–7 of [`rnd.md`](rnd.md)). For FSS the useful auto-events/alerts are: stock expiry, low stock, PO received / awaiting receipt, service day not yet logged, menu activation.

---

## Suggested improvements (for consideration)
- **Receiving-first PO view** for FSS: a queue of "ordered" POs to receive + attach proof, separate from the planning PO list.
- **Shortfall alerts** when `complete-day` is blocked, routed to the dashboard (ties into Notifications).
- **Cleaning-log + meal-prep** as the two anchor daily tasks on the dashboard.
- **Read-only vs edit split:** decide whether RND should be read-only on operational FSS data (currently both roles have full access to the shared `/fss` routes).
- **Receipt reminder:** flag received POs that still lack a receipt/proof attachment.
