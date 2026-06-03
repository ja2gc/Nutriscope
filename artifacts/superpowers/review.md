# Superpowers Review — Milestone: Complete Backend System Requirements

**Scope:** Models, Migrations, Seeders, Requests, Resources, Controllers, Services, Jobs, and Feature Tests for NCP core (Assessment, Diagnosis, Intervention, Monitoring), Food Service (Inventory, Suppliers, Purchase Orders, Shopping Lists, Menu Cycles, Budgets), OCR extraction pipeline, and PDF report orchestration.  
**Date:** 2026-06-03  
**Reviewer:** Antigravity AI Partner  

---

## Blockers

*None found.* All 117 PHPUnit feature and unit tests passed successfully. The database migrations run without issues, and endpoint authorization/roles are correctly structured.

---

## Majors

### M-1 · Shopping List Suggestion ignores Active Menu Cycle requirements

| Detail | |
|---|---|
| **Files** | [ShoppingListController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/FSS/ShoppingListController.php#L50-L89) |
| **Severity** | **Major** — Suggested shopping lists will under-procure ingredients required for the upcoming week's menu. |

**Problem:**  
The `generate()` method in `ShoppingListController.php` only checks for ingredients in the `inventory` table where `quantity_in_stock < minimum_stock_threshold`. It calculates suggestion quantity based *solely* on `minimum_stock_threshold - quantity_in_stock`. It does **not** retrieve the active menu cycle for the upcoming week nor calculate the total ingredient quantities needed for those meals, as explicitly required in the system requirements:
> 1. Get active menu_cycle for the upcoming week
> 2. Calculate total ingredient quantities needed for all meals
> 3. Compare with current inventory.quantity_in_stock
> 4. For each ingredient where stock < needed + minimum_stock_threshold: suggest purchase quantity = (needed + minimum_stock_threshold) - current_stock

**Proposed Fix:**  
Fetch the active menu cycle for the selected period, iterate through the days/meals to aggregate ingredient requirements, and add them to the shortfall calculation.

---

### M-2 · Purchase Order status transition to 'Received' does not update inventory

| Detail | |
|---|---|
| **Files** | [PurchaseOrderController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/FSS/PurchaseOrderController.php#L69-L73) |
| **Severity** | **Major** — Transitioning PO to 'Received' status does not restock the physical inventory. |

**Problem:**  
In `PurchaseOrderController.php::update`, the status is updated to `'received'` in the database via `$purchaseOrder->update($request->validated())`. However, there is no side-effect logic to find or update the corresponding `inventory` records for the PO items. The system requirements specify:
> When Received: quantity_in_stock updated for each item in the PO.

**Proposed Fix:**  
In the controller update method (or via a database transaction listener/observer on `PurchaseOrder`), check if the status is transitionally updated to `'received'`. If so, iterate over the PO's items and increase `quantity_in_stock` in the `inventory` table.

---

### M-3 · Menu Cycle activation does not update status or activation date

| Detail | |
|---|---|
| **Files** | [MenuCycleController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/FSS/MenuCycleController.php#L47-L51) |
| **Severity** | **Major** — Activated menu cycles remain in `'draft'` or other statuses and lack activation dates. |

**Problem:**  
In `MenuCycleController.php::activate`, the controller only updates `is_active => true`. It does not update the `status` field to `'active'` or set the `activation_date` to `now()`, which are fields defined in the database and required by the system requirements:
> Activate a menu cycle: sets status to active, sets activation_date.

**Proposed Fix:**  
Update the `activate()` method:
```php
$menuCycle->update([
    'is_active' => true,
    'status' => 'active',
    'activation_date' => now()->toDateString(),
]);
```

---

## Minors

### m-1 · Hardcoded fallback User ID in ProcessDocumentExtraction Job

| Detail | |
|---|---|
| **Files** | [ProcessDocumentExtraction.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Jobs/ProcessDocumentExtraction.php#L62) |
| **Severity** | **Minor** — Risk of database integrity constraint exception if user ID 1 does not exist. |

**Problem:**  
When saving the created `OcrDocument` record, the job falls back to a hardcoded `user_id = 1` if `reviewed_by` is not set. Since `user_id` has a foreign key constraint on the `users` table, this will crash if no user exists with ID 1.

**Proposed Fix:**  
Ensure a system user or default admin user is safely retrieved (e.g., `User::where('role', 'Admin')->first()?->id` or allow the column to be nullable if appropriate).

---

### m-2 · RecommendService inputs are request-driven rather than patient-driven

| Detail | |
|---|---|
| **Files** | [MealPlanController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/MealPlanController.php#L96-L104) |
| **Severity** | **Minor** — The RND has to pass raw conditions in the API request body instead of the endpoint automatically reading from the patient record. |

**Problem:**  
In `MealPlanController.php::recommend`, the endpoint `POST /api/rnd/ncp-records/{ncpRecord}/intervention/recommend` passes `$request->conditions` and `$request->stages` directly to `RecommendService::getRecommendations`. Ideally, the backend should extract these from the patient's existing `Diagnosis` list, `Assessment` profile, and biochemical values.

---

## Nits

### N-1 · Unused ParsedDocument DTO

| Detail | |
|---|---|
| **Files** | [ParsedDocument.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/DTO/ParsedDocument.php) |
| **Severity** | **Nit** — Dead code / unused file. |

**Problem:**  
The DTO `App\DTO\ParsedDocument` is defined but never imported or used in the extraction services or jobs.

---

### N-2 · AI Suggestion fallback model configuration

| Detail | |
|---|---|
| **Files** | [services.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/config/services.php#L40) |
| **Severity** | **Nit** — Model mismatch. |

**Problem:**  
The Anthropic config falls back to `claude-haiku-20240307` instead of the requested `claude-haiku-4-5` model. This is easily corrected in the `.env` file (`ANTHROPIC_MODEL`).

---

## Summary

The backend implementation covers almost all database schemas, controllers, and feature tests specified under the milestones. However, several critical operational side-effects (e.g., updating stock upon PO receipt, calculating shopping list requirements based on menu cycle, and menu cycle status updates) are missing or stubbed in a way that passes simple tests but fails operational requirements.

### Next Actions
1. **Fix M-1**: Implement menu-cycle aggregation inside `ShoppingListController::generate`.
2. **Fix M-2**: Implement inventory restocking trigger in `PurchaseOrderController::update` upon transition to `'received'`.
3. **Fix M-3**: Update status and activation date in `MenuCycleController::activate`.
4. **Fix m-1**: Make fallback `user_id` retrieval robust.
