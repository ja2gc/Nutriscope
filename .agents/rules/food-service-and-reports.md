# Food Service, Procurement, and Reports

## Domain boundaries

- Clinical food/recipe and food-service catalog/recipe are separate calculation domains unless current code proves otherwise.
- Preserve ordered menu lines, public UUID mutation contracts, ingredient inclusion/exclusion, and visible-vs-procurement semantics.
- Reuse the real lifecycle services/listeners for shopping lists, purchase orders, receiving, budgets, notifications, and reports.

## Procurement and history

- Keep planned-vs-actual quantities/prices distinct.
- Preserve supplier, receiving, proof/receipt, completion, program/activity, population, accomplishment, and source relationships required by current workflow.
- Historical procurement uses the frozen snapshot/version captured by the workflow; never reread mutable source data.
- Supplier changes, receiving, and inventory effects obey current authorization and lock/evidence rules.

## Reports

- Preserve historical identity, creation time, source, branding/signatories, and template/appearance versions.
- Preview/download is read-only: no duplicate, identity mutation, or silent replacement with “latest.”
- Pass explicit record identity through generation; do not infer the wrong historical record.
- Server-enforce owner/type/role scope. Private expired bytes use the existing prepare/reprepare path.
- Inspect real generated PDFs for page count, readable layout, expected rows/dates, clipping, and overlap.
