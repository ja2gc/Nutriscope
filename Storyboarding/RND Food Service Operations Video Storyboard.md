# RND Food Service Operations Video Storyboard

Verified against the current RND website, FSS Android app, and food-service rules on **2026-08-27**.

Use this as both the recording checklist and narration script. Create every named record during the recording; do not rely on existing menu cycles, lists, or purchase orders. Existing user accounts may be used only to sign in.

## How to Use This Script

1. Complete **Recording Setup** before filming.
2. Record the numbered scenes in order. A scene that needs an earlier result includes an exact **Before this scene** reference.
3. Follow **On-screen actions** while recording, then use **Narration** as the spoken explanation.
4. Confirm the **Expected result** before moving to the next scene.
5. The future NutriScope **Help → Guides** page will use shorter user instructions and optional video links. Camera directions and cleanup notes stay in this recording script.

**Overall sequence:** Create reusable food-service records → create a recipe → plan the weekly menu → make and review a shopping list → release the purchase order → record deliveries and people served → review the budget and reports.

## Words Used in This Script

- **RND:** Registered Nutritionist-Dietitian who plans food service on the NutriScope website.
- **FSS:** Food Service Staff who receive purchases and record service information in the NutriScope Android app.
- **Baseline servings:** The standard number of servings for which a recipe's ingredient quantities were entered.
- **Menu cycle:** A Monday-to-Sunday meal plan.
- **Vendor:** The supplier or seller providing an item.
- **PO (purchase order):** The approved buying record created from a reviewed shopping list.
- **Planned and actual values:** Planned values show what was approved; actual values show what was really purchased or served.

## Recording Setup

- Pick a future Monday-to-Sunday week that is not already covered by a menu cycle.
- Use future dates in the current fiscal year (budget year) and confirm that its budget exists.
- Prepare two small receipt/proof image files for each vendor shown.
- Use two roles: RND on the website and FSS in the native mobile app.
- Suggested record names below use `Demo Video` so they are easy to remove after recording.

## Scene 1 — Create the Food, Supply, and Vendor List

**Purpose:** Prepare the reusable records needed by recipes, shopping lists, and purchase orders.

**Before this scene:** Complete **Recording Setup** above.

**On-screen actions**

1. Sign in as RND, open **Food Service → Procurement → Suppliers**, and create **Demo Video Main Vendor** and **Demo Video Alternate Vendor**.
2. Open **Food Service → Inventory**.
3. Create ingredient **Demo Video Chicken** with category **Meat**, selected unit **kg**, **Demo Video Main Vendor**, and a realistic price. Leave **Include in generated shopping lists** enabled.
4. Create ingredient **Demo Video Seasoning Mix** with category **Condiment**, selected unit **kg**, a vendor, and a price. Turn off **Include in generated shopping lists**.
5. Create supply **Demo Video Meal Container** with its vendor and price.
6. Point out the **Auto grocery** and **Purchase when needed** badges.

**Narration**

> Food-service planning begins with reusable food, supply, and vendor records. They store names, units, suppliers, and current reference prices. They do not track how much stock is currently in storage. The chicken will be added automatically to shopping lists made from a menu. The seasoning mix can still be used in recipes, but staff must add it to a shopping list when they decide it is needed.

**Expected result:** Both vendors, both ingredients, and the meal container are saved and available for later scenes.

## Scene 2 — Create a Recipe and Its Standard Serving Size

**Purpose:** Create one reusable recipe with exact ingredient quantities for 50 servings.

**Before this scene:** Refer to **Scene 1 — Create the Food, Supply, and Vendor List** for the ingredients used here.

**On-screen actions**

1. Open **Food Service → Foods** and create **Demo Video Chicken Stew**.
2. Set the recipe baseline to **50 servings**.
3. Add **Demo Video Chicken** with an exact baseline quantity such as **6.250 kg**.
4. Add **Demo Video Seasoning Mix** with an exact baseline quantity such as **0.125 kg**.
5. Add short preparation notes and save.
6. Open the recipe/profile and point to baseline servings and exact measurements.

**Narration**

> A recipe stores exact ingredient measurements for a standard number of servings. The seasoning quantity remains part of the recipe even though it is marked purchase when needed. That setting only prevents it from being added automatically to a shopping list.

**Expected result:** **Demo Video Chicken Stew** is saved with a 50-serving standard and exact ingredient quantities.

## Scene 3 — Create and Reuse a Weekly Menu

**Purpose:** Place the saved recipe into a weekly plan and save that structure for reuse.

**Before this scene:** Refer to **Scene 2 — Create a Recipe and Its Standard Serving Size** for the recipe used in the menu.

**On-screen actions**

1. Open **Food Service → Menu Cycle** and create a new cycle.
2. Choose the prepared Monday as the week start and leave the name blank.
3. Assign **Demo Video Chicken Stew** and, where useful, a second meal line to at least one slot on every date included in the purchase. Fill all required days so the menu can be activated and used to make a shopping list. Do not add rice as a weekly-menu line.
4. Open one slot before generating a shopping list. Show **Purchase estimate: Not set**, baseline quantities, and preparation notes.
5. Save the cycle. Point out its automatic date-span name.
6. Choose **Save as Template** and name it **Demo Video Weekly Template**.
7. Activate the cycle.
8. Open the saved template to show its menu, rename or edit it, save, and cancel one edit to return to display mode. Then load it into another future week, change one slot, and show that the saved template itself did not change. Delete only a disposable template if demonstrating Delete.

**Narration**

> A weekly menu can be created manually or copied from a saved template. Templates support open, edit, rename, delete, and load. A slot can contain more than one ordered meal line. If no name is entered, NutriScope displays the week's dates as its name. Loading a template creates a new weekly copy, so changing that week does not change the original template. Weekly menus show meals only; bulk rice is handled later in the shopping-list draft. Before a shopping list is generated, the food profile shows the recipe's standard quantities because no purchase estimate has been entered yet.

**Expected result:** The weekly menu is saved, available as a reusable template, and active for its selected dates.

## Scene 4 — Make a Food Shopping List from the Menu

**Purpose:** Calculate how much food is needed for the planned dates and estimated number of servings.

**Before this scene:** Refer to **Scene 2 — Create a Recipe and Its Standard Serving Size** and **Scene 3 — Create and Reuse a Weekly Menu**.

**On-screen actions**

1. Open **Food Service → Procurement → Food Shopping Lists**.
2. Choose **Suggest from Menu**.
3. Select only the fully planned date span.
4. Enter one estimated serving count, for example **120**, for the whole span.
5. Generate the list.
6. Show that the menu slot profile now reflects the 120-serving purchase estimate.
7. In the shopping list, point to the calculated need for **Demo Video Chicken**.
8. Confirm that **Demo Video Seasoning Mix** was not auto-added.
9. Choose **Add food item** with an empty search, point out **Rice** as the first recommendation, and add the required kilograms to the same generated draft.

**Narration**

> Enter the estimated serving count once for the selected dates. NutriScope adjusts each recipe from its standard serving size. For example, a 50-serving recipe is multiplied by 120 divided by 50. It then combines the required amount of each automatically included ingredient across all selected dates. Purchase-when-needed ingredients are not added automatically. The same generated draft already accepts manual additions, and Rice is recommended first when the item search is empty.

**Expected result:** The list shows the calculated chicken requirement for 120 servings, bulk rice as a manually added kilogram row, and the seasoning mix excluded until added manually.

## Scene 5 — Review Quantities, Prices, and Vendors

**Purpose:** Turn the calculated food requirement into a practical buying plan without losing the original calculation.

**Before this scene:** Refer to **Scene 4 — Make a Food Shopping List from the Menu** for the generated list.

**On-screen actions**

1. Open the shopping list by selecting its name. Use the pencil beside the detail title to rename it, cancel once, then save the intended name and confirm it returns to display mode.
2. Keep the calculated requirement visible.
3. Edit the chicken purchase quantity using three decimals, select its purchase unit, then edit the current purchase-unit price and vendor.
4. Use **Add food item** to manually add **Demo Video Seasoning Mix** only for this purchase, showing how to include a one-time pantry item.
5. Add another low-priority manual ingredient, then delete that manual row.
6. On a generated row, turn off **Buy** and enter an optional exclusion note such as **Removed for cost review**; turn it back on for the final PO.
7. Review the included total and **Before PO release** checklist.

**Narration**

> The list name opens its detail; rename is available only beside the opened title. Calculated need stays read-only so staff can always see what the menu required. They can still change the amount to buy, choose a buying unit from the shared unit list, and change current price and vendor. Manually added rows cover one-time needs such as pantry replenishment. An automatically calculated row is excluded instead of deleted, so the original requirement remains visible for review.

**Expected result:** The list contains realistic buying values, an included manually added seasoning item, and no unresolved release blockers.

## Scene 6 — Create Manual Lists for Events and Supplies

**Purpose:** Show how to prepare one-time food and supply lists without creating a weekly menu.

**Before this scene:** Refer to **Scene 1 — Create the Food, Supply, and Vendor List** for the reusable items selected here.

**On-screen actions**

1. Return to Food Shopping Lists and create **Demo Video Outreach Event — Food** as a manual food list.
2. Add ingredients directly without creating a menu cycle.
3. Open **Supplies Lists** and create **Demo Video Outreach Event — Supplies**.
4. Add **Demo Video Meal Container**.
5. Point to the shared purpose in both names and the separate food/supplies tracks.
6. Leave these lists in draft or complete them separately if the video needs a second PO example.

**Narration**

> A one-time event does not need a weekly menu. Staff can create a manual food list and a separate manual supplies list. Using the same event name makes their shared purpose clear while keeping food and supplies in their correct buying sections.

**Expected result:** Separate draft food and supplies lists exist for the same demo event.

## Scene 7 — Create and Release the Purchase Order

**Purpose:** Convert the reviewed shopping list into the approved record that FSS will receive against.

**Before this scene:** Refer to **Scene 5 — Review Quantities, Prices, and Vendors**. The fiscal-year budget must also be available as stated in **Recording Setup**.

**On-screen actions**

1. Return to the suggested shopping list.
2. Intentionally clear one vendor to show the visible blocker, then restore it.
3. Confirm that the fiscal-year budget and included total permit release.
4. Select **Create and release PO**.
5. Open the new PO and point out its PO number, shopping-list purpose/name, vendor groups, and locked planned values.

**Narration**

> Releasing the purchase order locks the approved buying plan. NutriScope checks that included items have vendors, the menu dates and serving estimate are complete when required, and the fiscal-year budget is sufficient. It then groups the approved items by vendor and keeps the planned values unchanged for later comparison and reports.

**Expected result:** A released purchase order shows its number, purpose, vendor groups, and locked planned values.

## Scene 8 — Record What Each Vendor Delivered

**Purpose:** Record the real quantities, prices, vendors, receipts, and proof for the released purchase order.

**Before this scene:** Refer to **Scene 7 — Create and Release the Purchase Order**. A **vendor group** means the items currently assigned to one vendor inside the purchase order.

**On-screen actions**

1. Switch to FSS and open **Purchase**.
2. Open the newly created PO and its first vendor.
3. Before uploading evidence, use the group-level **Change vendor for all** and select **Demo Video Alternate Vendor**; confirm that the group moves to that vendor.
4. On one item row, open **Change vendor** and move only that item back to **Demo Video Main Vendor**; show the resulting vendor groups.
5. Open a vendor again. Show the planned purchase and prefilled editable actual values, so correct items need no retyping.
6. Expand **Calculation details** once to show calculated need, planned purchase, actual purchase, and the difference; collapse it again.
7. Change the actual chicken quantity to a realistic decimal such as **14.875 kg** and confirm/edit the actual unit price.
8. For a single-item vendor, optionally enter the receipt total to demonstrate deriving weight from price.
9. Upload at least one receipt and one proof-of-purchase image.
10. Leave the official receipt (OR) number blank for one vendor and point out that it is optional.
11. Select **Mark vendor received**.
12. Repeat for every vendor. For another vendor, enter an official receipt number to show both valid cases.

**Narration**

> Receiving begins with the locked buying plan, but staff may correct the vendor when the intended seller cannot supply an item. This correction is allowed only before receipt or proof images are attached. Planned values stay frozen, and actual fields start with the planned values so staff edit only what changed. Confirming receipt updates the inventory reference item's current purchase unit, conversion, and price for future planning without rewriting the PO row. Calculation details remain collapsed until needed. Each vendor requires reviewed actual values, a receipt image, a proof-of-purchase image, and the Mark vendor received action. An official receipt number is optional.

**Expected result:** Every vendor group is marked received with reviewed actual values and the required evidence.

## Scene 9 — Record How Many People Were Served

**Purpose:** Save the real number of people served for every date covered by the suggested food purchase order.

**Before this scene:** Refer to **Scene 3 — Create and Reuse a Weekly Menu** for the service dates and **Scene 8 — Record What Each Vendor Delivered** for receiving.

**On-screen actions**

1. Open **Meal Prep** for each covered service date.
2. Enter the positive actual population served and select **Record actual served** or **Update actual served**.
3. Return to the PO and show served-day progress.
4. Complete all covered dates so the suggested food PO can close.

**Narration**

> Estimated servings were used to plan the purchase. FSS later records how many people were actually served on each date. A purchase order created from a menu needs every covered date before it can finish because NutriScope uses the real total to calculate food purchase cost per person served per day. Manual food and supplies purchase orders do not require this step.

**Expected result:** Every covered service date has a saved actual population and the suggested food purchase order can complete.

## Scene 10 — Check the Budget and Reports

**Purpose:** Confirm that completed purchasing appears correctly in budget records and report outputs.

**Before this scene:** Refer to **Scene 8 — Record What Each Vendor Delivered** and **Scene 9 — Record How Many People Were Served**.

**On-screen actions**

1. Return to RND web and open the completed PO.
2. Show completed vendor evidence, confirmed actual totals, served population, and **Food purchase cost per served patient-day**.
3. Open **Food Service → Budget** and show the PO deduction in the fiscal-year budget history and the updated remaining balance.
4. Open **Reports** and preview the **Procurement Pack** purchase report and **Program Project Activity** report for the new PO.
5. Point out actual quantities/prices, evidence, optional OR shown as **Not provided**, and final status.
6. If previewing an unfinished manual list/PO, point out the visible draft/incomplete banner.

**Narration**

> After completion, the confirmed quantities, prices, and evidence appear in the final purchase order, fiscal-year budget record, Procurement Pack, and Program Project Activity report. Food purchase cost per person served per day covers food purchasing only; it is not the complete cost of nutrition care or food service. Any unfinished buying record remains clearly marked as a draft.

**Expected result:** The completed purchase order, budget deduction, remaining balance, and report previews agree with the recorded actual purchase.

## Closing Shot

**On-screen actions**

1. Show the flowchart or briefly revisit Inventory, Foods, Menu Cycle, Procurement, Budget, and Reports.

**Narration**

> NutriScope connects reference data, exact recipes, reusable weekly planning, practical shopping review, evidence-based receiving, actual service population, budget effects, and reports. It does this without pretending to maintain live stock or automatically calculate pantry leftovers.

## Recording Cleanup

- Do not delete completed/final records that the system intentionally locks.
- If cleanup is necessary, record in a disposable demo database or reset the demo database after filming.
- Delete only draft `Demo Video` records that the UI safely allows; do not alter unrelated client data.
