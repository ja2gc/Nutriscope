# PO Vendor Reassignment and Receiving Comparison Design

Date: 2026-08-17

## Goal

Allow RND and FSS users to record the vendor that actually supplied each pending PO item without rebuilding the shopping list, while making calculated, planned, and actual values understandable without crowding the receiving screen.

## Scope

This change is limited to open purchase-order execution and its maintained documentation. It does not add inventory balances, payment processing, invoice matching, stock movement, or a general PO amendment system.

## Considered Approaches

### 1. Change only the whole vendor group

This is the smallest backend change, but it fails when the original vendor can supply some items and not others.

### 2. Put one vendor selector on every item

This supports every reassignment but makes changing an entire group repetitive and makes the scope of the action less obvious.

### 3. Use explicit group-wide and row-level actions

This is the selected approach. **Change vendor for all** sits outside the item table and clearly affects the whole pending group. **Change vendor** sits in an individual row and affects only that item. Both actions reuse the same atomic reassignment service.

## Vendor Reassignment Rules

- RND and FSS may reassign vendors while the PO is in open execution.
- A received vendor group cannot be changed.
- The source and destination groups must have no receipt or proof attachments. The user must remove evidence before changing the structure it documents.
- **Change vendor for all** moves every item from the current group to the selected vendor.
- **Change vendor** moves only the selected row.
- If the PO already has a pending group for the selected vendor, moved items merge into it.
- Otherwise, the system creates one pending vendor group for that vendor.
- If an item move empties the source group, the empty group is deleted.
- Selecting the current vendor is a harmless no-op and does not create a duplicate group.
- Planned and saved actual item values move with the item.
- Source, destination, and PO totals are recalculated in the same database transaction.
- Catalog vendor references continue to update only after the actual vendor group is explicitly marked received.
- Every reassignment is captured in the existing purchase-order audit and revision history with the acting user.

## API and Transaction Design

Add two explicit mutation routes rather than overloading receiving updates:

- `PATCH /api/fss/purchase-order-vendor-groups/{vendorGroup}/supplier` with `supplier_id`
- `PATCH /api/fss/purchase-order-items/{purchaseOrderItem}/supplier` with `supplier_id`

Both routes call one focused service. The service locks the purchase order, source group, item when applicable, and matching destination group inside one transaction. It validates lifecycle and evidence rules after acquiring locks, performs the move or merge, recalculates totals, and writes one audited purchase-order revision.

The existing vendor-group receiving endpoint remains responsible only for optional OR, actual values, and the explicit received transition.

## Receiving Value Hierarchy

The application already stores three stages and must label them accurately:

1. **Calculated need**: recipe-scaled quantity and reference cost from the generated shopping list.
2. **Planned purchase**: quantity, unit, and price reviewed before PO release.
3. **Actual purchased**: quantity and unit price confirmed during receiving.

The PO resource continues exposing all three. Prefilled actual inputs use the planned values as a convenience, but `actual_values_confirmed` remains false until the user saves them.

## Web UX

### Vendor scope

- Place **Change vendor for all** in a labeled card above the group item table.
- Place **Change vendor** in the action area of each item row.
- Use a small confirmation dialog that names the source vendor, target vendor, and affected item count or item name.
- Disable both actions when the group is received, the PO is complete, or evidence prevents reassignment. Show the recovery instruction next to the disabled action or returned error.

### Value comparison

- Keep **Actual purchased quantity** and **Actual unit price** as the primary editable fields.
- Prefill them from the reviewed planned values.
- Show a compact always-visible line: `Planned: quantity unit @ price`.
- Replace the misleading **Calculated qty** label with **Planned quantity** wherever the displayed value is the reviewed purchase quantity.
- Add a native, keyboard-accessible **View calculation details** disclosure for each row.
- Expanded details show calculated need, planned purchase, current actual purchase, and the quantity difference from plan.
- Show **Not reviewed** until actual values are saved and **Reviewed** afterward. Do not represent prefilled values as confirmed.
- After receiving, show the same comparison read-only.

## Mobile and Responsive UX

- Use the same terminology and rules as web.
- Put **Change vendor for all** in the vendor-group header card.
- Put a full-width or clearly labeled **Change vendor** action within each item card; no icon-only control.
- Use an accessible pressable disclosure with expanded state for calculation details.
- Maintain at least a 44-point touch target and allow text to wrap under large system font sizes.
- Keep actual inputs visible by default and secondary calculation detail collapsed.

## Error Handling

Return `422` with a direct recovery message when:

- the PO or source group is already received or completed;
- source or destination evidence must be removed first;
- the selected item does not belong to the routed group or PO;
- the destination vendor is invalid.

Concurrent requests are serialized with row locks. A failed reassignment rolls back every group, item, total, and audit change.

## Testing

Backend feature coverage must prove:

- whole-group vendor replacement;
- whole-group merge into an existing destination group;
- individual item move into an existing or newly created group;
- deletion of an empty source group;
- preservation of planned and actual values;
- recalculated group and PO totals;
- no-op behavior for the same vendor;
- rejection for received, completed, source-evidence, and destination-evidence cases;
- RND and FSS authorization;
- purchase-order audit revision coverage;
- resource output preserves calculated, planned, actual, and confirmation state.

Web and mobile checks must cover the exact action labels and scopes, corrected value labels, default actual inputs, planned reference, expandable calculation details, review status, disabled states, and API payloads.

## Documentation

Update the Food Service flowchart, FSS workflow, role guides, help content, developer maintenance guide, and video storyboard so they no longer claim vendor structure is permanently frozen at PO conversion. The maintained rule becomes: planned quantities and units freeze at release, while pending vendor assignments may be corrected until receiving evidence or completion locks the affected group.
