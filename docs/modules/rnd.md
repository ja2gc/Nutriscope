# RND Role - Current Workflow Status

RND is the food-service planning owner and the clinical nutrition owner. This file reflects the current implementation after the `docs/superpowers/plan/` food-service redesign work.

## Current Scope

RND owns:

- Patient/NCP clinical workflows.
- Food catalog/inventory reference, food-service recipes, and menu-cycle planning.
- Budget setup and food-service settings.
- Shopping-list generation and PO conversion.
- Procurement open-execution follow-up with FSS.
- Reports and archived submitted outputs.

FSS owns daily execution data: receipts/proof uploads, OR numbers, served population, and accomplishment/diet-list entries.

## Dashboard

RND dashboard now focuses on live work items instead of old cost-per-head KPI cards.

- Pending POs are shown from open-execution purchase orders.
- Budget-per-head/day KPI cards were removed per plan.
- Food-service status is read from real backend data, not hardcoded display values.
- Announcement feed remains visible.

## Food-Service Settings

Budget per head per day belongs in Food Service Settings for RND/Admin use.

Current status:

- `food_service_settings.per_head_day_limit` exists and is used by menu-cycle cost/budget display.
- RND budget page copy says the per-head/day limit is configured in Settings.

Known gap:

- Legacy `budgets.per_head_day_limit` is still accepted by backend budget setup. UI no longer uses it, but API/model surface still exists.

## Inventory / Reference Catalog

Inventory remains in RND scope as a food-service reference catalog. It is not the old stock-management page.

Plan intent:

- Inventory is the backend reference list of items available for procurement.
- It should not be a navigable page for FSS.
- It should remain available to RND because recipes, menu cycles, shopping lists, supplies lists, procurement, PO values, and reports depend on it.
- It must not expose stocking/restock workflow as a user-facing FSS operation.

RND page contents:

- Ingredients: name, category, vendor, inventory/base unit, purchase unit, purchase cost, unit cost.
- Supplies: name, category, vendor, cost per unit only, no quantity field.
- Ready-to-eat/single items: name, category, vendor, base unit, purchase cost, and unit cost so they can be placed directly in a menu-cycle cell.
- Vendor state: auto-suggested from latest procurement and auto-updated unless manually locked.
- Vendor controls: lock/unlock suggested vendor so RND can preserve a manually selected supplier.
- Unit/cost visibility: show how purchase price converts into cost per inventory/base unit.
- Actions: create, edit, delete where safe; block delete when item is already used by recipes or procurement history.

Explicitly not on this page:

- Quantity in stock.
- Restock button.
- Stock status/no-stock workflow.
- FSS stocking controls.

Current status:

- The backend catalog table `fs_items` is intact and still drives calculations.
- Recipe creation/editing still reads catalog rows from `/api/fss/inventory/rows`.
- Claude is currently restoring a cleaner catalog API under `/api/fss/fs-items/catalog` with RND-only create/edit/delete routes.

Known gap:

- The RND food-service catalog/inventory surface is not cleanly wired yet. The sidebar points to `/food-service/foods`, while current implemented pages were under `/food-service/recipes`. RND needs a usable `/food-service/foods` inventory/catalog page matching the fields above.

## Menu Cycles

Menu cycles are weekly planning artifacts, normally Monday-Sunday.

Current behavior:

- RND fills menu-cycle cells with recipes or food-service items.
- Each planned cell can carry estimated population.
- FSS can view active/saved cycles read-only in mobile.
- Mobile menu view can open a food/recipe profile for a cell and show scaled ingredients, quantities, cost, cost/head, and prep notes.
- When a food shopping list is converted to PO, menu-cycle day cells receive permanent PO snapshots.

## Shopping List Generation

The planned procurement flow is now date-span based.

Current behavior:

- RND selects a start and end date.
- Backend resolves each date to its covering menu cycle.
- Suggested list generation is all-or-nothing: missing menu days or missing estimated population block generation and return exact missing dates.
- Estimated population is saved at shopping-list level and cascades into covered menu-cycle day cells.
- Ingredient quantities and costs recalculate into the editable shopping-list item table.
- Manual quantity/cost edits update line totals.
- UI shows the calculated budget-per-head/day panel from backend cost-efficiency data.

Known UI drift:

- The shopping-list header still has a secondary `per day` chip computed as total divided by span days. The correct budget-per-head/day value is shown in the dedicated panel.

## Procurement

Current planned food flow:

1. RND generates or edits a draft shopping list.
2. RND converts it to one food PO.
3. PO groups items by vendor group under that one PO.
4. PO enters `open_execution`.
5. RND/FSS can input OR numbers and upload receipts/proof per vendor group.
6. Receipt upload marks the vendor group received and can trigger stock-in.
7. Food PO completes only after every vendor group has receipt upload and every date in the span has served population.
8. Completion calculates actual budget per head/day from final PO total divided by actual served population.

Current status:

- One PO with vendor groups is implemented.
- Structural PO data freezes at conversion.
- Menu-cycle day PO snapshots are written.
- Pending PO cards exist on web and mobile dashboards.
- Supplies and food use independent procurement tracks.

Known gap:

- Backend/web still allow a vendor group to be manually marked `received` without receipt upload. Mobile has been corrected to rely on receipt upload.

## Budget

Current behavior:

- Fiscal-year budget setup uses fiscal year and allocated amount.
- Shared budget ledger records system deductions from completed POs and manual adjustments.
- Food and supplies share the ledger only for deductions; procurement tracks remain separate.
- Budget page no longer asks for per-head/day limit.

Known gap:

- Backend still keeps legacy `budgets.per_head_day_limit` API/model field. Remove or ignore after Settings migration is locked.

## Reports

Current active food-service report direction:

- RND reports are browse/render/archive, not blind "generate all".
- Active food-service report types are narrowed to current useful reports.
- FSS accomplishment reports archived by staff are visible to RND.
- Rendered archived reports use frozen snapshots where implemented.

Accomplishment report:

- FSS diet-list entries auto-file weekly accomplishment reports.
- Week boundaries are Monday through Sunday.
- A staff report archives only after that FSS user has one entry for every day in the week.
- Snapshot is frozen and remains stable even if diet-list rows later change.

Known gap:

- Program Project Activity report browse/render still uses `menu_cycle_id` and reloads menu-cycle data. The PO conversion creates a `ProgramProjectActivity` snapshot, but the report browser/generator is not yet fully snapshot/PO sourced.

## 8. Settings
Basic settings stuff
[2026-06-19] Check existing scaffold before building. Build frontend only against what the backend already supports. Do not add settings with no backend support.

## Profile
basic profile stuff
[2026-06-19] At minimum: User name (which should be the same variable for reports that are the ones that prepared it), email, password change. Check if profile photo upload is supported in the backend before adding it to the frontend.