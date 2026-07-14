# FSS Role - Current Workflow Status

FSS is the food-service execution role. This file reflects the current implementation after the `docs/superpowers/plan/` redesign work.

## Current Scope

FSS owns:

- Daily meal-prep/service logging.
- Diet-list/accomplishment entries.
- Served population input.
- PO vendor-group OR numbers.
- Receipt/proof uploads.
- Viewing active menu cycles and food profiles.
- Viewing own archived accomplishment reports.

FSS does not own menu planning, shopping-list generation, PO authoring, supplier management, budget setup, or analytics.

## Mobile App Shape

Four bottom tabs (in order):

1. **Dashboard** — KPIs, active menu week card, pending PO details, today's service, announcements.
2. **Menu** — full menu cycle list, recipe/item profiles, served population backfill per day.
3. **Prep & Accomp.** — mark today served, shortfall confirmation, diet-list/accomplishment form, links to Menu and reports.
4. **Procurement** — PO list/detail, OR number, receipt/proof upload.

Menu Cycle is reachable from the Menu bottom tab AND as a convenience link from Prep. Notifications, announcements, profile, and settings are reached from the header.

Removed/out of current mobile scope:

- Inventory tab.
- Suppliers tab.
- Budget tab.
- Insights tab.
- FSS-side shopping-list authoring.

## Dashboard

FSS dashboard loads live data from `GET /api/fss/dashboard/summary`.

Current widgets:

- **Meals to log today** — count of active-cycle service slots not yet completed for today.
- **Pending POs** — count tap-navigates to Procurement.
- **Active Menu Week card** — active cycle name, start date, service-day count per week; tap navigates to the Menu tab.
- **Pending PO detail list** — each open-execution PO with `po_number`, `procurement_track`, and per-PO `waiting_on` labels (`Needs receipts`, `Needs served population`). Tap navigates to Procurement.
- **Today's service** — meal slots for today with prep/shortfall status.
- **Announcements feed** — recent announcements.

Absent by design: no inventory card, no budget card, no per-head KPI, no graph widgets.

## Menu Cycle View

FSS can open menu cycles read-only from the Prep & Accomplishment screen.

Current behavior:

- Active cycle is listed first.
- Weekly menu cells are grouped by weekday.
- Food or recipe cells can be opened.
- Profile modal shows scaled servings, total cost, cost/head, ingredients, quantities, costs, and prep notes.
- FSS can backfill actual served population per day.

This supports kitchen visibility without letting FSS alter RND planning data.

## Prep And Meal Service

FSS can:

- View today's planned service.
- Mark today's service as served.
- Handle shortfall confirmation when inventory is insufficient.
- Submit actual served population via diet-list/accomplishment entries.

Diet-list counts are the source of actual served population when present. Those sums flow into `meal_prep_logs.served_population` and refresh PO lifecycle/completion calculations.

## Accomplishment / Diet List

FSS enters daily accomplishment data from mobile:

- Ward.
- Population/headcount.
- Seven task flags.
- Off-duty marker.
- Active menu-cycle linkage when available.

Seven task rows:

1. Helped in food preparation work.
2. Stored food supplies properly.
3. Collected diet list from different wards.
4. Apportioned and distributed food to in-patients in different wards.
5. Collected, cleaned and returned used utensils and dining equipment.
6. Assumed duties as assistant cook.
7. Maintained cleanliness of kitchen, cabinets, refrigerators and freezers.

Weekly archive behavior:

- The week is always Monday-Sunday.
- A report archives only after the FSS user has one entry for every day in that week.
- Off-duty entries count as that day's entry and render as `X`.
- Archived snapshot freezes the grid and does not change after later diet-list edits.
- FSS sees only their own archived accomplishment reports.
- RND can see FSS-filed accomplishment reports.

## Procurement Execution

FSS mobile procurement flow:

- Lists existing POs only.
- Opens vendor groups under one PO.
- Shows vendor line details read-only.
- Allows OR number save.
- Allows receipt and proof image upload.
- Receipt upload is the workflow event that marks a vendor group received server-side.
- Completed/archived POs are locked from mobile edits.

Backend role enforcement (enforced server-side, not just UI):

- FSS may only update `or_number` on a vendor group.
- FSS is blocked (403) from patching `status`, `items`, prices, quantities, or supplier data.
- RND retains price-correction rights during open execution with full audit trail.

Food PO completion requires receipts from all vendor groups **and** served population for all covered service dates. Supplies PO completion requires receipts only.

## Reports

FSS report scope is accomplishment report only.

Current behavior:

- Mobile `My accomplishment reports` loads `/api/fss/reports`.
- Client filters to `accomplishment_report`.
- Detail screen renders the frozen accomplishment snapshot natively.
- Backend blocks FSS access to non-accomplishment reports.

## Data Read Scoping

Diet-list/accomplishment reads (`GET /api/fss/diet-list-counts`) are scoped to the current FSS user's own rows. RND and Admin see broader views through dedicated report/admin endpoints.

## Audit trails

FSS can view the authorized shared purchase-order trail at the existing PO activity path. It includes receipt/proof, vendor-group, receiving, lifecycle, meal-service, and related budget events while returning only the structured audit DTO—never raw properties or file contents. User, system, and deleted-record snapshots remain understandable, and older pages load through cursor pagination. Inventory has no activity route or stock-history surface. Audit taxonomy, retention, privacy, and incident handling are documented in [`docs/architecture/audit-logging.md`](../architecture/audit-logging.md).

## Notifications, Profile, Settings

Current behavior:

- Header bell loads `/api/notifications`.
- Settings supports display density, reduce motion, mark-all-read, profile link, and logout.
- Profile shows: Role (read-only), Status (read-only), Name (editable), Email (editable), Contact Number (editable).
- Profile uses shared auth endpoints (`GET /api/auth/me`, `PATCH /api/auth/profile`).

## Demo Readiness Notes

Mobile is suitable for APK demo after the remaining non-mobile flagged gaps are addressed and verification passes.

Need before demo:

- Backend API reachable from the device/emulator through `EXPO_PUBLIC_API_URL`.
- Seeded users and food-service demo data.
- At least one active/current menu cycle.
- At least one open-execution PO with vendor groups for receipt/proof demo.
- FSS account with role `FSS`.
