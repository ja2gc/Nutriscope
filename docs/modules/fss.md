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

Current bottom tabs:

- Dashboard.
- Prep & Accomplishment.
- Procurement.

Menu Cycle is reachable from Prep & Accomplishment, not a bottom tab. Notifications, announcements, profile, and settings are reached from the header/settings flow.

Removed/out of current mobile scope:

- Inventory tab.
- Suppliers tab.
- Budget tab.
- Insights tab.
- FSS-side shopping-list authoring.

## Dashboard

FSS dashboard loads live data from `GET /api/fss/dashboard/summary`.

Current widgets:

- Meals to log today.
- Pending POs.
- Today's service.
- Announcements feed.

The old inventory/no-stock and budget/cost KPI concepts are not part of the current demo scope.

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

Current status:

- Mobile no longer exposes a manual `Mark received` shortcut.
- Backend/web still have a manual received-status path; this is a remaining workflow gap outside mobile.

## Reports

FSS report scope is accomplishment report only.

Current behavior:

- Mobile `My accomplishment reports` loads `/api/fss/reports`.
- Client filters to `accomplishment_report`.
- Detail screen renders the frozen accomplishment snapshot natively.
- Backend blocks FSS access to non-accomplishment reports.

## Notifications, Profile, Settings

Current behavior:

- Header bell loads `/api/notifications`.
- Settings supports display density, reduce motion, mark-all-read, profile link, and logout.
- Profile uses shared auth endpoints.

## Demo Readiness Notes

Mobile is suitable for APK demo after the remaining non-mobile flagged gaps are addressed and verification passes.

Need before demo:

- Backend API reachable from the device/emulator through `EXPO_PUBLIC_API_URL`.
- Seeded users and food-service demo data.
- At least one active/current menu cycle.
- At least one open-execution PO with vendor groups for receipt/proof demo.
- FSS account with role `FSS`.
