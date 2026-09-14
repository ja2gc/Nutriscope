# Food Service, Procurement, and Reports

## Domain boundaries and menu behavior

- Clinical food/recipe ≠ food-service catalog/recipe. Separate schema/calculation; never merge.
- Weekly menu = meals only. No rice line in seeded cycle/template.
- Bulk `Rice`: catalog item; manually add kg to existing generated draft; first empty-search suggestion. No second manual-list feature.
- Preserve ordered multi-line meal slots + public UUID line mutation.
- Ingredient include/exclude changes procurement, not visible meal.
- FSS has no live stock add/deduct workflow. Never restore stale inventory claims.

## Procurement and history

- Real lifecycle services/listeners create menu/list/PO/snapshot/receiving/budget/notification/report outputs.
- Preserve generated/manual lines, planned-vs-actual qty/price, supplier, optional OR, private receipt/proof, completion, PPA, served population, accomplishment.
- After PO conversion, use frozen PO-scaled menu snapshot; never reread mutable recipe.
- Completed history connected + within configured per-head/day cap.
- Supplier reassignment obeys lock/evidence/auth rules.

## Reports

- Preserve historical report identity/`created_at`/branding/signatories/source/template+appearance versions.
- Preview/download read-only: no duplicate, identity mutation, or silent newer record.
- Explicit ID (`meal_plan_id`, etc.); never replace selected history with “latest.”
- FSS accomplishment = progressive semi-monthly: 1–15, 16–month-end.
- Accomplishment PDF = 1 readable A4 landscape page; expected rows/dates; no clip/overlap.
- Server-enforce FSS owner/type scope; RND/Admin only authorized visibility.
- Expired private report bytes → existing prepare/reprepare flow; no public-file fallback.
