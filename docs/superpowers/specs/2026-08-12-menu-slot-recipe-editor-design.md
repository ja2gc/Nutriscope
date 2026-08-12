# Menu Slot Recipe Editor Design

## Goal

Let RND open any populated Menu Cycle slot and edit its recipe details without changing the master Food Service recipe. Let FSS open the same slot page read-only. Scale ingredient quantities from the slot recipe when a shopping list and purchase order use a different population.

## Scope

- Replace the Menu Cycle food-profile modal and misleading master-recipe Edit link with a dedicated slot page.
- RND may edit slot name, reference servings, ingredient quantities/units, preparation notes, and planned servings.
- FSS may view the same data and computed quantities but cannot edit.
- All ingredient quantities scale proportionally. No fixed-quantity mode.
- Master recipes remain unchanged.
- Existing purchase-order snapshot remains the final immutable record.
- No recipe-version system, autosave framework, new ingredient table, or duplicate recipe CRUD.

## Chosen Design

### Storage

Add one nullable JSON `recipe_override` column to `menu_cycle_days` and cast it to an array. It stores only a customized slot recipe:

```json
{
  "name": "Chicken Adobo",
  "reference_servings": 20,
  "prep_notes": "Simmer until tender.",
  "ingredients": [
    { "fs_item_id": 12, "name": "Chicken", "quantity": 2, "unit": "kg" }
  ]
}
```

An untouched slot keeps `recipe_override = null` and reads the current master recipe. First RND save copies the edited values into the override. Customized slots stop following later master-recipe edits. `Reset to master recipe` sets the override back to `null` after confirmation.

JSON is intentional: data belongs to one slot, is replaced as one validated document, and mirrors the existing `po_snapshot` approach. A second normalized recipe schema would duplicate current recipe CRUD and add joins without user value.

### Scaling

Keep two explicit serving values:

- **Recipe makes**: slot reference servings describing the entered ingredient quantities.
- **Planned servings**: requested population for the slot preview. During procurement, the shopping-list/PO population is the final target.

Use one formula everywhere:

`scaled quantity = baseline ingredient quantity × planned servings ÷ recipe makes`

Example: 2 kg for 20 servings becomes 10 kg for 100 servings. If RND changes the slot baseline to 3 kg for 20 servings, it becomes 15 kg for 100 servings.

All ingredients scale proportionally. Ingredient edits change the slot baseline, not an already-scaled output. Input changes update the preview locally and immediately; they do not issue an API request per keystroke.

`MenuCycleCostService` remains the single costing/scaling authority. It reads `recipe_override` when present, otherwise the related master recipe. Shopping-list generation, cost summaries, and PO snapshot creation therefore use identical input and math.

The existing shopping-list population cascade remains authoritative during procurement: it substitutes the entered PO population as target servings, then scales the selected slot recipe. It does not rewrite the slot baseline.

When a PO is created, the existing snapshot flow stores the computed slot recipe, target population, ingredient quantities, and costs. Once `po_snapshot_locked` is true, both RND and FSS see the slot as read-only. Later slot or master changes cannot alter that PO.

### Routes and Authorization

Use stable composite slot identity because Menu Cycle updates currently recreate day rows:

- RND web: `/food-service/menu-cycle/{cycle}/slots/{day}/{meal}`
- FSS PWA: `/fss/menu/{cycle}/slots/{day}/{meal}`
- API GET: `/api/fss/menu-cycles/{cycle}/slots/{day}/{meal}` for FSS and RND
- API PATCH: same backend resource inside the existing RND-only middleware
- API DELETE override/reset: same RND-only boundary

Backend resolves the slot inside the requested cycle, validates day/meal values, enforces role and active-user middleware, rejects edits to locked snapshots, validates every nested ingredient field, and ignores client-supplied prices. Current `FsItem` prices remain authoritative for cost calculations.

If a slot or cycle has unsaved changes, clicking it saves the Menu Cycle first, then navigates using the returned cycle ID. Save failure leaves the user in the editor with the existing error message.

Current Menu Cycle saves replace day rows. `syncDays` therefore carries the existing override forward only when the same day/meal still references the same master recipe or catalog item. Replacing or clearing a slot discards its old override. This preserves custom recipes without trusting a large client-supplied JSON document during ordinary grid saves.

### UI

One shared `MenuSlotRecipePage` component serves both thin route wrappers.

- Top: visible Back button, day/meal context, slot name, `Master recipe`, `Customized for this slot`, or `Locked to PO` status.
- Summary: Recipe makes, Planned servings, total cost, and cost per head.
- Ingredients: mobile cards and desktop rows using existing catalog picker, quantity, unit, and calculated scaled quantity/cost.
- Notes: existing textarea styling.
- RND actions: `Save slot changes` as sole primary action; `Reset to master recipe` secondary/destructive and confirmed.
- FSS/locked state: semantic read-only values, no disabled-looking editable controls.

Back uses client navigation and returns to the exact Menu Cycle URL. The Menu Cycle page restores selected cycle and browser scroll; no full reload. Route-level loading uses a stable skeleton. Saving keeps content visible, disables only actions, and shows inline success/error feedback.

Mobile uses a single column, 16px inputs, at least 44px touch targets, no horizontal scrolling, and bottom padding above the FSS navigation. Tablet/desktop use the same page with a constrained content width and two-column summary where space allows. Existing NutriScope typography, emerald/warm tokens, Button, Date/input conventions, and Lucide icons are reused.

### Menu Cycle Changes

- Entire populated slot card becomes the view/edit link; no tiny text-only target.
- Remove profile modal and master-recipe Edit link.
- Preserve current add/remove slot behavior.
- RND label communicates customization; FSS sees the same indicator.
- FSS Menu route remains read-only and uses the dedicated page instead of squeezing editing controls into a popup.

## Alternatives Rejected

1. **Edit master recipe from Menu Cycle:** changes unrelated cycles and contradicts slot ownership.
2. **Create full normalized slot-recipe and slot-ingredient tables:** clean relational model but duplicates recipe CRUD, migrations, resources, and joins for data owned by one slot.
3. **Store only final scaled quantities:** loses the reference recipe and makes later population changes ambiguous.
4. **Bottom sheet/modal:** cramped for ingredient editing, weak deep linking, and caused the current loading/navigation discomfort.

## Error and Edge Handling

- Reference servings must be at least 1.
- Planned servings must be at least 1 before scaling or PO generation.
- Ingredient list requires at least one unique existing `FsItem`; quantity must be positive and unit non-empty.
- Deleted/unavailable catalog items produce a clear validation error and remain visible by stored name until corrected.
- Locked PO slots return 422/409 from backend even if a client bypasses disabled UI.
- Network failures keep edits in the form and provide Retry/Save again.

## Tests and Verification

- Unit tests: override/master selection and proportional scaling.
- Feature tests: RND save/reset; FSS read-only/403; locked rejection; cross-cycle slot rejection; nested validation; PO snapshot uses override; master recipe unchanged.
- Frontend tests: local recalculation without per-keystroke fetch; role/lock actions; Back URL; status labels; unsaved-cycle save-before-navigation.
- Regression: Menu Cycle cost, shopping-list generation, PO snapshot, FSS menu, and master recipe editing.
- Run Pint, relevant PHP tests, full frontend Vitest, ESLint, TypeScript, production build, and responsive browser checks at 375/768/1024/1440 widths.

## Success Criteria

- Clicking a populated slot always opens its dedicated page.
- RND edits affect only that slot.
- FSS sees the same current or frozen details read-only.
- Master recipe never changes through slot editing.
- Preview and PO quantities use the same proportional formula.
- No content flash/reload while editing numbers.
- Back returns predictably to the same Menu Cycle.
