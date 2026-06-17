# 04 — UI / UX

> Phase D. Redundant surfaces, missing CRUD, and a date-range desync bug.

## Foods section (new top-level)

- There's both a standalone recipe page and a recipe tab inside Inventory — **redundant**.
  Remove the Inventory one.
- New top-level section named **"Foods"**, parallel to Inventory, Procurement, Menu Cycle, and
  Budget. It covers both recipes and single-ingredient items, and isn't physical stock.
- One home for recipes + single-ingredient items.

## Low-stock dot

- Render low stock as a simple **green/red dot**, not an editable numeric field.
- red = out of stock (qty 0), green = in stock. No threshold input (see
  [03-schema-business-logic.md](03-schema-business-logic.md)).

## Recipe category filter

- Filter categories must match the categories available when editing a recipe:
  **beverage, dinner, snack, lunch, breakfast** (same set).
- Filtering supports **multi-select**, with overflow handling if the filter row gets too wide.

## Procurement

- Missing standard CRUD: today the only visible action is **delete**, and it's the only table in
  the project where you must click into a row to view/edit instead of editing inline. Add full
  **create / read / update / delete with inline editing**, matching every other table.
- Add a way to **manually add an item to a procurement shopping list**.
- The procurement page **reloads on every action in every tab** — noticeably slow. Stop the
  full-page reloads (update state in place / partial refetch).

## Purchase order table columns

- Inconsistent naming: "Buy", "Base unit", "Unit ₱" don't match the inventory convention of
  **quantity / unit / cost per unit**. Rename to the inventory convention.
- The **"Buy" column's purpose must be defined or cut.**

## Suggested menu list — weekday range (bug fix)

- The suggested menu list currently uses a **free-text date range** with no constraint tying it
  to the menu cycle it's generated from. Real bug: a mismatched range can be entered and the
  system will generate and label a list as belonging to a cycle it doesn't match.
- Fix: replace the date range with a **"from weekday to weekday" picker** (e.g. Tuesday to
  Thursday), **contiguous span only**, scoped to that specific cycle's seven day-of-week slots.
  This removes the desync — there's no free-text field that can point outside the cycle.
- **"to" before "from" in week order: disallow in the UI** (require from ≤ to; no wraparound).
- Calendar anchoring: the weekday span resolves to real dates within the cycle's anchored
  Monday-start week (see [00-overview.md](00-overview.md)).
- Affected: `ShoppingListController::generate()` and the procurement suggested-list UI.

## Menu Cycle inline editor

See [05-missing-functionality.md](05-missing-functionality.md) — view/edit a food or recipe
inline using the FS recipe editor UI, instead of a disconnected reference.
