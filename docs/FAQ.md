# NutriScope Frequently Asked Questions

Verified against the current web, mobile, Laravel role gates, and rendered reports on **2026-09-26**. This is the user-facing FAQ and source for the role-scoped in-app Help pages. When this file conflicts with an older diagram or plan, current application code wins.

## Start Here

- **RND:** use the web console for Nutrition Care Process (NCP), clinical food data, food-service planning, announcements/SOP, and reports.
- **FSS:** use the mobile app for menu viewing, meal preparation, daily accomplishments, purchasing execution, announcements/SOP, and personal accomplishment reports.
- **Admin:** use the web console for accounts, audit oversight, AI usage controls, announcements/SOP, allowed operational reports, branding, and food-service budget settings.
- For ordered instructions, see [Role How-To Guide](ROLE-HOW-TO.md).
- For presentation sequences, see [System Storyboards](STORYBOARD.md).

## Shared Account and Access Questions

### I forgot my password. What should I do?

Select **Forgot password?** on the sign-in screen, then enter your **sign-in email**. NutriScope finds the account by that sign-in email and sends the reset link to its verified recovery email. The recovery address is never displayed.

### Why did the recovery page say a link was sent even when I received nothing?

The response is intentionally generic. Check spam, confirm you entered the sign-in email, and wait briefly. Unknown accounts and accounts without a verified recovery email receive the same response but no message is sent. If the link never arrives, ask an Admin to reset the password.

### My reset link or token does not work. What now?

It may be invalid, more than 15 minutes old, or already used. Request a new link. A successful reset revokes existing signed-in sessions.

### What happens on first login?

Accounts created by Admin start with a temporary password and require account setup. First enter and confirm a new password of at least eight characters, then select **Next**. On the next step, enter a recovery email and select **Send verification code**. The six-digit code expires after ten minutes; only the correct code verifies the recovery address and completes setup. You may choose **Do later** before or during recovery verification. After deferral, a persistent amber banner appears on authenticated web pages and in the mobile header until both requirements are complete.

### Why can FSS not sign in on the website?

FSS accounts are mobile-app only. The server rejects FSS web login. RND and Admin accounts use the web console and are rejected by the FSS mobile app.

### Why does sign-in say my account is deactivated?

Admin suspended the account. Contact an Admin. Reactivating the account is an Admin action.

### Can I change my name, contact number, or profile photo?

Yes. Open **Profile**. Web users can update first name, last name, contact number, and one PNG/JPEG/WebP profile image. A selected image may be up to 10 MB; reposition and zoom it in the circular crop before applying it. FSS mobile supports name and contact number. The sign-in email, role, and status remain read-only.

### What is the difference between sign-in email and recovery email?

- **Sign-in email:** used with the password to log in.
- **Recovery email:** receives password-reset links.

Forgot Password accepts the sign-in email, while the reset message is delivered to the verified recovery email. Adding or changing a recovery email requires a six-digit verification code. The code expires after ten minutes. Multiple accounts may use the same recovery address, but each account must verify it independently. The old verified address remains active until the new one is verified. **Profile** displays the current verified and pending status by default; select **Add Recovery Email** or **Change Recovery Email** to edit it, or remove it. Removing it disables password reset and returns the setup reminder until a new address is verified.

### How do I change my password while signed in?

Open **Profile**, enter the current password, a new password of at least eight characters, and confirmation. Changing the password revokes existing sessions, so sign in again afterward.

### Why was I signed out after Admin changed my role, status, or password?

Those changes revoke access tokens by design. Sign in again if the account is still active and the platform matches the role.

### Can users change their own role or active status?

No. Only Admin manages role and active status.

## Shared Navigation, Saving, and Communication Questions

### I opened a protected page and was redirected to sign-in. Why?

The session may be missing, expired, revoked, or for the wrong role. Sign in again. If it repeats, ask Admin to confirm the account is active and assigned to the correct role.

### I cannot see an operation another role can see. Is that a bug?

Usually not. NutriScope enforces role access in both the UI and server. RND plans and manages clinical work; FSS executes daily food-service work; Admin manages access and oversight.

### Why are some lists split across pages?

Large directories use server pagination. Use the page controls and filters. A hidden pager usually means only one page exists.

### What should I do if a save fails?

Read the field-level or page error, keep the page open, correct missing values, and retry once. Check connectivity before repeated submissions. If the same error remains, record the screen, time, and action for Admin/support.

### What is the difference between an announcement and an SOP?

- **Announcement:** time-based post with category, audience visibility, pinning, text, and optional images. Post images retain their original proportions inside a fixed responsive frame, and the author uses their current profile photo when one is available.
- **SOP:** one current standard procedure pinned above announcements. Each revision creates a preserved version in **History**.

### Who can revise the SOP?

RND and Admin. FSS can read the current SOP and its history but cannot revise it.

### Who can edit or delete announcements?

RND can manage their own announcements. Admin can manage announcements through the Admin board. FSS reads announcements only.

### What do notification states mean?

Unread items contribute to the badge. Opening a notification marks it read and, when it identifies a record, opens that exact announcement, appointment, NCP follow-up, or purchase order. **Mark all read** clears only the current user's unread state. Informational and resolved notifications have **Dismiss**; an unresolved action-required notification cannot be dismissed until its required action is completed.

## RND: Nutrition Care Process Questions

### What is the correct NCP order?

**Patient → Assessment → Diagnosis/PES → Intervention and meal plan → Monitoring on follow-up → Reports.** The application blocks later steps until required earlier records exist.

### How do I start care for a new patient?

Open **Nutrition Care → Patients**, choose **Create Patient & Start Assessment**, enter the required patient details, and save. NutriScope creates the patient, starts an NCP cycle, and opens Assessment.

### Can a patient have more than one NCP cycle?

Yes. Open the patient profile, choose **ADIME Records**, then **Start New Cycle**. Each cycle keeps its own Assessment, Diagnosis, Intervention, Monitoring, attachments, meal plans, and activity history.

### Where can I see earlier completed ADIME records?

Open the patient profile and choose **ADIME Records**. **Current Cycle** is separate from the always-visible **Past Records** section. Completed and discontinued cycles appear in Past Records, two per page. If it is empty, that patient has no terminal prior cycle recorded; starting a new cycle never changes an earlier one.

### How do scheduled and walk-in visits work?

A Scheduled visit stores date/time and written purpose but does not start automatically. Select **Start Visit** to bind it to the patient's current NCP cycle. A Walk-in starts immediately. One RND can have only one active visit, and the global **Resume** banner keeps the same patient/cycle available after navigating away.

### What appointment outcomes should I use?

Use **Completed** after a normal attended visit, **Ended early** when an active visit stops early, **No-show** when the patient did not attend, **Cancelled** when the planned visit was called off, and **Rescheduled** when a replacement schedule was created. No-show, Cancelled, and Rescheduled do not count as completed clinical care. Past Appointments shows the administering RND for visits that actually started.

### Why does Diagnosis say Assessment Required?

The current NCP cycle has no saved assessment. Return to Assessment and choose **Save Assessment**.

### Why is Intervention blocked?

Intervention requires a saved assessment and at least one saved diagnosis.

### Why is Monitoring blocked?

Monitoring requires a saved assessment, at least one diagnosis, and an intervention/care plan. The screen is intended for follow-up visits. Current code does not separately count encounters; the saved prior-step records are the implemented gate.

### What are the Assessment sections?

Dietary, Anthropometrics, Client History, Biochemical/Labs, Referral/Screening, and RND Summary. Calculated BMI, weight-related values, risk scoring, and other clinical helpers update from entered data.

### How do I record weight-change duration and the nutrition care category?

Enter one positive duration quantity and choose **weeks** or **months**. There is no fixed three-month assumption or duplicate duration field. Select the cycle's required **Nutrition care category** separately from the medical diagnosis, PES diagnosis, and Intervention goal. This describes the main reason for nutrition care in that ADIME cycle; it does not state a confirmed medical diagnosis. If **Other** is selected, complete **Specify category**; Census groups these entries under one Other bucket.

### Where are pregnancy/lactation and stress factor recorded?

Choose None, a confirmed pregnancy trimester, or Lactating in Assessment. A migrated unspecified-pregnancy value must be confirmed before maternal autofill. Stress factor is a legacy field: it is not displayed, accepted from new saves, or used in prescription calculation. Current TEE uses the documented backend method and goal-specific rules.

### Why must I enter dry weight when edema is present?

Edema can make measured weight unsuitable for prescription calculations. When **Edema Present** is Yes, **Dry Weight** is required before Assessment can save.

### Does uploading a lab or referral file automatically fill Assessment?

No. Current uploads are supporting-document storage for that NCP cycle. They do not run OCR or auto-populate fields. Enter or verify clinical values manually.

### What does Generate Summary do?

It creates an editable draft from current Assessment data. Review it clinically before saving. If source fields change, the page warns that the draft is stale and can regenerate it; Undo restores the previous text when available.

### Is the risk score final clinical judgment?

No. NutriScope derives a score from the entered factors. RND can switch to manual override, select factors, and compare the manual result with the automatic result. RND remains responsible for clinical interpretation.

### How do I write a diagnosis?

Open Diagnosis, choose **Add New Diagnosis**, then build Problem, Etiology, and Signs/Symptoms. For Intake, choose **Vitamins** or **Minerals**, then select the specific nutrient from its dropdown. Clinical and Behavioral problem lists, and every Etiology and Signs/Symptoms list, include an **Other** checkbox that reveals a 75-character field. Selected options and enabled Other text compose PES. Additional notes contain reasoning only and are saved separately. Review the editable PES statement and choose **Save Diagnosis**.

### Can AI create a diagnosis automatically?

AI receives relevant calculated BMI/classification and weight changes; height and screening risk are not routinely sent. Adult BMI classification uses Asia-Pacific bands and stays separate from screening and PES diagnosis. BMI/%IBW alone does not establish malnutrition or its severity.

Pediatric BMI classification uses the official [WHO 2007 sex-specific monthly bands](https://www.who.int/toolkits/growth-reference-data-for-5to19-years/indicators/bmi-for-age) at 61–228 completed months; Asia-Pacific bands apply afterward. Month 60 shows BMI without a class. Ages 0–4 retain measurements but omit BMI because the under-five growth calculator is not implemented. Adult IBW/BMR/TEE panel estimates are withheld through month 228; the separate prescription calculator retains its pediatric methods. This is a band lookup, not a full growth assessment or an exact z-score calculator. MUAC screening labels apply only at 6–59 months and do not establish overall nutrition status. The BMI card's info button explains its reference and exclusions.

No. Open **AI Review** and choose **Generate AI Suggestions**. One pass considers eligible nutrition problems and proposes at most three prioritized supported drafts, displayed two per page. Multiple drafts may share a domain. Relevant referral/medication information and selected clinical guidance provide context; patient names are removed. Medical diagnoses do not replace nutrition diagnoses. Normal findings, denied symptoms, history and uncertainty do not independently establish a problem.

Each card shows **Evidence used**. Supported P/E/S explanations appear in Extra Notes, Additional Etiology Notes and Additional Signs and Symptoms Notes; unsupported or repetitive explanations are omitted. Notes allow 100 generated characters each and stay outside PES. Edit selects matching choices and places supported unmatched wording in **Other**. Approval saves the issued draft and its evidence provenance. Duplicate checks include manual/saved diagnoses and pending drafts. Relevant evidence changes or related deletion reopen review, including older custom diagnoses. Deletion alone does not generate; a new draft still needs current evidence. With exhausted evidence, **Generate AI Suggestions** stays clickable and indicates that no additional applicable nutrition diagnosis was found, without token use. When all output is withheld, one message explains that no additional supported nutrition diagnosis could be generated. This does not establish that no applicable diagnosis exists. The button keeps the same label for an explicit retry and retains valid drafts; no automatic retry occurs. When valid drafts remain, the partial-rejection warning is hidden.

Diagnosis allows **ten generation attempts per RND per ten minutes**, independently from monitoring. Another patient or cycle does not reset the allowance. Unsuccessful attempts count; cached reads, status, empty candidate sets, acceptance and dismissal do not. The visible button explains when generation is unavailable and refreshes after expiry. RND must accept or save an edited draft; generation never creates a saved diagnosis. Manual entry remains available.

Pending drafts are retained when you leave. Reopening them does not call the provider. Complete and valid empty results close reviewed candidates; partial rejection allows deliberate retry. Repeated exhausted checks do not fill unused draft slots. Requests use short references and constrained output. Clinical validation remains mandatory, and provider usage is recorded even when output is withheld.

Versioned guidance and its source editions are maintained in the [clinical AI library](developers/ncp/ai.md#selective-clinical-context), checked October 24, 2026. Only matching guidance is sent; no runtime web search occurs. No fixed Academy recommendation for diagnosis count is claimed. RND review remains required.

### What happens when I set an intervention goal?

The application progressively reveals only that goal's applicable stages, asks the backend calculation service for prescription values, and lets RND review or edit targets before saving. The calculation panel shows the goal-calculated baseline, any maternal modifier, and the final energy/protein prescription. If required Assessment inputs are missing—or a legacy pregnancy value lacks a confirmed trimester—calculation warnings identify the problem. Fluid is displayed separately from food scaling; the selected menu day shows approximate fluid from foods and the remaining drink guidance.

### What is included in Intervention?

Food/nutrient delivery and prescription, food guidance, patient meal plan, education, counseling, and goal planning. Visit timing, purpose, attendance, and next scheduling are handled by Appointments/shared visit controls rather than an Intervention-only context.

### How do saved Intervention Plans work?

The **Plans** tab lists complete dated plans newest first. Older plans open read-only. **New Intervention Plan** prefills the previous prescription and notes plus the latest saved menu in the same care cycle, so you can edit only what changed. Copied prescription values are not immediately recalculated. Saving the intervention creates its separate identity; **Save week** saves its menu draft as an independent copy. Cancelling creates nothing and leaves the previous plan unchanged. Each plan may have zero or one saved menu. Education and counseling fields allow 1,200 characters each and 2,200 characters combined to keep the printed plan concise.

### Can I make a patient meal plan manually or from a template?

Yes. Use Menu Items from Food Library to create a week manually, generate it, or load a template. Preview the copied template in the patient’s weekly calendar, review goal/stage/maternal compatibility, optionally **Scale to prescription**, then **Save week**. Replacing nonempty cells requires confirmation. Failed saves retain the draft. Manual menus and templates may place a Snack Menu Item in any meal slot, including milk and fruit in main meals of maternal no-snack patterns.

For **Auto-Generate**, review the preselected available Carb Menu Item, change it if needed, then confirm it for all 21 main-meal slots. Select **Exclude carbs** to omit that separate side. The chosen Carb accompanies a Meal even when the Meal already contains carbohydrates. Included Snacks fill only AM and PM Snack slots. **Exclude snacks** works for every goal, including liver disease, and redistributes targets across main meals. Patient allergies block incompatible Carb choices; staff assess free-text restrictions themselves. An unusable portion blocks generation with an explanation rather than silently replacing or omitting the Carb. Review portions, totals, and patient suitability. Fluid guidance stays outside food scaling and target-match success. Empty snack rows are omitted from the PDF.

### How do I plan Tube Feeding formulas?

Select one **Nutrition support** option in Assessment: **Oral / Per Orem**, **Tube Feeding**, or **NPO / TPN — No food menu**. Existing missing choices are migrated to Oral. A saved Intervention Plan retains its support type; after a change, create a new plan.

Tube Feeding opens formula columns without weekdays or automatic prescription scaling. Select Formula 1, 2, etc. to see its macros and the prescription-selected micros above; each card shows only the formula name, opens by clicking anywhere, and uses the shared top-right X to remove it. Exact measurements remain inside its profile and report. Fluid sits with the macros. **Select formula** in the next empty column selects a Tube Feeding Menu Item, **Load Template** copies a formula collection, and **Create formula** reuses Menu Item creation with Tube Feeding selected. Choose **Use in this plan** or **Save to Food Library and use**; Cancel uses the usual unsaved-change confirmation. Open a profile to adjust its multiplier and ingredients. A change from 2x to 3x scales the current edited amounts by 1.5. **Reset recipe** explicitly restores the captured original. Source items/templates remain unchanged. **Save as template** saves the prepared collection; template profiles themselves are view-only.

Nutrition Intervention Plan and NCP Summary show the saved support type. Tube reports show individual recipes, portions and selected nutrient profiles without a weekly or daily total. NPO / TPN reports retain prescription and guidance without requiring a menu. Census shows the three support counts per qualifying ADIME cycle. BMI classifications use each cycle's initial Assessment, with separate pediatric and adult groups; every classification and nutrition care category appears in order, including zero counts. Older calculation-basis periods are recalculated for display without changing stored snapshots.

### What can I record in Monitoring?

Record observation date/type, follow-up measurements, dietary restrictions, patient-specific labs/intake, symptoms, Progress Assessment and follow-up timing. Maintain allergies and food dislikes in Assessment. Progress Assessment is required, up to 700 characters. **Generate AI Summary** compares unsaved values with earlier readings, available targets and selected goal/clinical context. Identical inputs reuse a result; **Regenerate AI Summary** requests fresh wording. Monitoring has its own allowance of **ten attempts per RND per ten minutes**; another draft does not reset it. Review before **Save Visit**. Existing text is replaced only through **Use this summary**; generation alone saves no visit. History shows five items per page. The latest saved entry in an active cycle can be edited/deleted; older entries are **View only**. Reports display the saved evaluation. Progress Trends provides filtered graphs. Treatment changes belong in a new dated Intervention Plan. Schedule through the shared appointment controls.

Generated Progress Assessment names the supported priority care focus from unmet goals or documented barriers, without prescribing exact treatment steps. This follows the [Academy's Monitoring and Evaluation guidance](https://www.eatrightpro.org/practice/nutrition-care-process/ncp-overview/nutrition-monitoring-and-evaluation) on progress, variance and barriers. The summary remains an editable RND draft within the existing character limit and uses one provider call.

Each populated current indicator compares with its latest earlier nonblank reading, skipping blank intervening visits. If none exists, it uses the Assessment baseline. Without either, no trend is available. Blank current indicators are omitted, not treated as zero; entries dated after the current draft are excluded.

### Can I delete a patient or NCP cycle?

The Delete action is available only on an open current cycle that has not completed all three protected records: Assessment, at least one Diagnosis, and Intervention. Completed or discontinued cycles are preserved in Past Records. Once all three protected records exist, that cycle—and therefore a patient containing it—is protected from normal deletion.

### Where are patient attachments?

Upload them from the cycle's Assessment page. The patient profile **Attachments** tab groups files by NCP cycle so records do not mix.

## RND: Food Library and Food-Service Questions

### What is the difference between Food Library and Inventory?

- **Food Library:** clinical **Ingredients**, **Menu Items**, **Templates**, and a full-tab **Food Calculator** reusing the recall calculator without patient saving. Menu Items contain measured Ingredients and use Meal, Carb, Snack, or Tube Feeding categories. Tube formulas stay in tube-specific searches and library management; Ingredient food-group filters remain separate.
- **Inventory:** Food Service **Ingredients**, **Menu Items**, and **Supplies**. Ingredients and Supplies store vendor, unit, and purchase cost; Menu Items contain recipes and single food items categorized as Meal, Snack, or Beverage for menu cycles. This is not a live stock-count workflow. Menu templates remain under **Menu Calendar**.

### How do I import food nutrient data?

Open **Food Library → Ingredients → Import from USDA**, search, import, then review category, nutrient basis, and allergen suggestions. The importer checks the source's actual 100 g or 100 mL basis. A rate-limit or temporary provider failure saves no partial record; try again later.

### How do clinical Ingredient units and Menu Item portions work?

Recipe amounts describe the whole batch. Nutrients per Menu Item serving equal batch nutrients divided by its declared yield; a planned quantity is the number of servings consumed. Ingredient units convert only within mass or volume families. `piece` is an exact count baseline only when nutrients per measured piece are known; it never implies a gram weight. Unit help is available beside the controls. Changing a baseline from 100 g to 0.1 kg preserves its nutrients and linked recipe amounts; incompatible or precision-losing changes require correction.

### Does FSS manage inventory quantities?

No. Current FSS mobile navigation has no Inventory tab or stock add/deduct workflow. The web Inventory page is an RND reference catalog.

### How do Inventory units affect Foods and Shopping Lists?

Auto Grocery items use a measured recipe unit that converts to the Inventory unit: g/kg/oz for weight, or mL/L/cup/tbsp/tsp for liquids. Pieces and packs match only the same unit. Changing Inventory to a compatible unit converts Cost/unit to keep the same price. For an incompatible change, open each affected Food or editable Menu Calendar item from the Inventory list, enter its new quantity and matching unit, then return and save Inventory. Nothing changes until every listed correction is complete. Completed PO snapshots stay as recorded. Every ingredient has a baseline amount that scales with servings. Non-Auto Grocery may use a different recipe unit, such as tbsp, tsp, or pinch, but adds no automatic cost or Shopping List quantity. Add it manually using the Inventory unit when needed. Use the separate **Adjust to taste** checkbox to group seasonings below a note in the Food profile; their measured amounts still scale.

### How do I build a Menu Calendar week?

Prepare **Inventory → Ingredients**, **Menu Items**, and **Supplies** first. Menu Items use **Meal**, **Snack**, or **Beverage**; **Single food item** creates a menu item with one ingredient. Inventory search sits beside **All**, **Non-Auto Grocery**, and **Auto Grocery** filter buttons. Open **Food Service → Menu Calendar** and fill a Monday–Sunday week, or load a template into its draft and save after review. Picker **View** shows the weekly grid; open a cell item to inspect its read-only profile and return to the same template. Loading over planned cells requires confirmation. **Templates** remains under Menu Calendar and uses the same calendar for editing, including three-meal templates without snacks. There is no copy-day-to-all-days action. A blank menu name is generated from its dates; bulk rice is added later in the shopping list. Food Service recipe unit help retains the Non-Auto Grocery typed-unit exception.

### What is a Menu Calendar template?

A reusable saved layout. Loading it copies the menu structure into a dated week; edits to the week do not edit the template. Review the dates and foods before saving.

### When does a weekly menu become current?

The menu covering today's date is current automatically, even when it has no meals yet. RND can fill that empty week. FSS can view the weekly menu but cannot edit it. After a food PO is generated from a menu, its plan is read-only; historical menus remain viewable.

### How is a suggested food shopping list created?

Open **Procurement → Food Shopping Lists → Suggest from Menu**, select a date range, enter one Estimated servings count, and preview the list. Saving applies that count to every selected Menu Calendar day. You can edit the count on the saved list until **Turn Into PO**; Save then updates those days and recalculates generated needs. Each Food scales from its own recipe serving size. Non-Auto Grocery ingredients stay in recipes but are not added automatically. Add them to the draft when needed; an empty item search suggests Non-Auto Grocery items first. Select **Save Shopping List** to keep the editable list. **Turn Into PO** is a separate step.

Suggested creation needs only a start date (today by default), number of days, and estimated servings. The name defaults to **Menu Shopping list** with the selected span and remains editable inside the list. Missing Menu Calendar plans stop creation and open a popup listing the exact dates to complete.

### What happens if I change a Shopping List quantity?

It changes only the amount to buy. It does not change Estimated servings or the Food recipe. Each item can use its calculated amount, round to the nearest whole unit (0.5 rounds up), or keep a manually entered amount. Changing Estimated servings recalculates generated needs; manual amounts stay as entered. Back lets you save or discard unsaved edits.

### Can I make a food or event list without a menu cycle?

Yes. Choose a procurement date (today by default), create a manual food list, and add ingredients directly. A date-based name is generated automatically; use the title pencil inside the list to rename it. Related food and supplies lists can use the same event name while remaining separate procurement tracks.

### How are supplies purchased?

Use **Procurement → Supplies Lists → New supplies list** and choose a procurement date (today by default). A date-based name is generated automatically and can be edited inside the list. Add catalog items and convert separately from food procurement; name, vendor and cost entry are not conversion requirements.

### What happens when a shopping list is converted?

Review the saved list and select **Turn Into PO**. A warning asks you to confirm that the Shopping List will become read-only. Select **Cancel** to keep editing, or **Turn Into PO and lock list** to create one order grouped by vendor. You can leave a vendor unspecified on the list, then assign one before receiving. If the final vendor changes before evidence is uploaded, use **Change vendor for all** on the group or row-level **Change vendor** for one item.

Budget allocation and remaining balance do not block **Turn Into PO**. Items are included by default; unchecking is an optional exclusion, not a required confirmation step. Suggested lists still need valid servings and menu coverage. Actual values and receiving evidence are handled later.

### Who records OR numbers and receipts?

FSS can enter actual quantity, unit price, or Actual Total and upload up to two receipt images and 12 proof-of-purchase images per vendor. Entering Actual Total derives quantity from the unit price. RND reviews those details on web Procurement and marks each vendor received. OR number may be recorded when the vendor provides one, but it is optional.

### When does a food purchase order complete?

When every vendor has reviewed actual values, receipt, proof, and explicit received status, and every covered service date has actual served population. Manual food and supplies POs require vendor completion but not served population.

### When do Inventory, Budget, and procurement reports update?

When the whole PO completes, Inventory takes the final Unit, Cost/unit, and Vendor; the latest completed PO sets the Inventory default Vendor. Budget records the completed purchase. Procurement Pack and, for a menu-based food PO, Program Project Activity are finalized from the completed PO. Their report names start **PP** and **PPA** respectively, followed by the source name or date. Affected generated marketing labels use **Marketing**. Their dates follow the selected procurement date or span; stored PDF bytes and completed purchase values remain unchanged.

### Why is a PO still open after all receipts were uploaded?

A receipt alone is insufficient: check proof, reviewed actual values, and the vendor's explicit received status. For a suggested food PO, also review **Served days** and record any missing population in **Meal Prep**.

### What is budget per head per day?

The planned limit comes from **Settings → Food Service Budget**. Estimated values use included planned purchases and the span estimate. The final **food purchase cost per served patient-day** uses confirmed purchase cost divided by actual served population for the covered period.

### Who can change the fiscal-year budget?

RND can set up budgets and create manual ledger adjustments. Admin's Budget page is read-only, but Admin can change the shared budget-per-head/day setting in Admin Settings.

## Reports Questions

### What is the difference between live preview and archived report?

Preparing an active report refreshes its current source and template, except completed-PO procurement reports, which are finalized at PO completion. **Archive** freezes exact as-filed PDF bytes and snapshot so later data, plans, templates, or branding changes do not alter it. Demographic Census uses a year/month screen instead of report preparation or archiving. **Download PDF** saves only the selected Census summary directly; it creates no filed report.

### Which reports can RND access?

Program Project Activity, Menu Calendar, Procurement Pack, Accomplishment Report, Nutrition Intervention Plan, and NCP Summary are PDF reports. Each Nutrition Intervention Plan uses the selected saved intervention and its own menu. Four compact cards per row list each recipe's ingredients once, show **Recipe makes: X servings**, and match **Your servings: P1 - Y; P2 - Z** to the weekly menu. Amounts show at most one decimal. Use **listed ingredient amount × your servings ÷ servings the recipe makes**. Saved ingredient edits are included in the listed recipe amounts; direct foods show their recorded portions directly. Incomplete legacy recipe data is repaired against verified catalog measurements before new report use. NCP Summary remains one per cycle and uses its latest saved intervention at preparation. Archived bytes stay frozen. Demographic Census is a year/month screen with direct selected-view PDF download. The internal compatibility identifier for Nutrition Intervention Plan remains `patient_menu_plan`.

### Which reports can Admin access?

Program Project Activity, Menu Calendar, Procurement Pack, Accomplishment Report, and the aggregate Demographic Census screen. For accomplishment reports, select Food Service Staff first, then browse that person's approved reports; Admin has no review tabs. Admin cannot access Nutrition Intervention Plan or NCP Summary.

Admin also has **Reports → Template Edit** for shared header branding and non-clinical report signatories. Patient-report signatories remain controlled by clinical records.

### Which reports can FSS access?

Only their own Accomplishment Reports.

### Why can't I submit an accomplishment period yet?

Each FSS user needs one saved Daily Log for every date of the semi-monthly period, including Off duty where applicable. The first period covers days 1–15; the second covers day 16 through month end. Submission is available once every date has a saved log, including before the period ends. RND sees it in **Reports → Accomplishment Report → To Review** only after submission.

### How are accomplishment corrections reviewed?

FSS can withdraw while a submission is pending, edit the daily logs, and submit again. RND can approve or reject a submitted period. A rejection includes a reason of up to 500 characters; FSS sees it in **Accomplish → My Reports** and can open any day for correction. Approval locks the daily logs and the reviewed PDF. Earlier generated reports remain available in Reports.

### Can an archived report be changed by editing source data?

No. It is a frozen snapshot. Correct current source data and create a new archive if an updated filed copy is needed.

## FSS Mobile Questions

### What are the current FSS tabs?

Six tabs: **Home**, **Announcement**, **Menu**, **Meal Prep**, **Accomplish**, and **Purchase**. Announcement is second and has separate **Announcements** and **SOP** tabs. The header bell opens notifications; the profile icon opens the account side menu.

### What should I do first each day?

Check Home for meals to log, pending POs, this week's menu, today's service, and announcements. Then handle Purchase receipts, Meal Prep, and Accomplish as work occurs.

### Why is no weekly menu shown?

If no menu covers the selected date, contact RND. A current empty week can appear before meals are planned.

### Can I edit the menu?

No. Menu is a read-only weekly reference. Tap a meal to open its scaled food profile on a separate page. Record actual population in **Meal Prep**.

### Where do I record actual population served?

Open **Meal Prep**, choose today or an earlier planned date, review its meals, then record or update a positive whole-number headcount. A Shopping List or PO is not required to record served population. A completed related food PO locks further corrections.

### Where should I enter my daily accomplishment?

Use **Accomplish → Daily Log**. It defaults to today. Choose any past date for a missed log; future dates are blocked, and **Today** returns to the default. Saving again updates that date's log until its period is submitted or approved.

### How does Off duty appear in the report?

It is the explicit daily record for a non-working day and counts toward semi-monthly period completeness. It cannot coexist with working rows for that date.

### What can I change on a purchase order?

FSS can review every vendor item without paging, correct actual decimal quantity or unit price, or enter Actual Total to derive quantity, upload/delete receipt and proof, and optionally save an OR number. The mobile app omits calculation details. RND marks a vendor received on web Procurement after reviewing those details. Before evidence or receiving, **Change vendor for all** changes the group and row-level **Change vendor** moves one item. Planned quantities, units, and calculated need remain locked.

### Why did uploading a receipt not change the vendor status?

Uploading evidence does not silently receive a vendor. FSS uploads receipt and proof and saves actual values; RND then uses **Mark vendor received** on web Procurement. OR number is optional.

### Why can I no longer edit a completed PO?

Completed and archived POs are locked to protect filed operational history.

### Why does camera or photo upload fail?

Allow camera/photo-library permission, confirm network access, and retry with a supported image. If denied earlier, enable permission in the device settings.

### Where are my reports?

Open **Accomplish → My Reports** for your semi-monthly periods, progress, review status, and each date's log. Submit when every date has a saved log; you do not need to wait until the period ends. You can withdraw a pending submission to make corrections. Approved and earlier PDFs are available under Saved PDFs with **View PDF** and **Download PDF**.

### How do I update the Android app?

The app checks periodically and offers the stable download page when a newer version exists. You can also use the profile side menu → **Check for updates**. The website QR always opens the same page, so it does not change between releases.

## Admin Questions

### How do I create a user?

Open **Manage Users → Create Account**, enter name, email, role, active status, temporary password, and confirmation. The new user must complete first-login password and recovery-email setup.

### Can Admin deactivate or delete their own account?

The UI blocks self-deactivation and self-deletion. Use another authorized Admin for Admin-account lifecycle changes.

### What happens when Admin resets a password?

The new password is stored and all of that user's sessions are revoked. Give the password through an approved secure channel. The reset action is audited.

### What can Admin see on the dashboard?

User totals by role, patient count as an aggregate KPI, monthly AI calls/tokens and estimated cost, audit-event counts, AI usage explorer, token limits, quick actions, and recent system activity.

### What do AI token caps do?

Admin can set daily and monthly token limits; blank means unlimited. Caps, dashboard totals and chart periods use Philippine calendar days/months. Diagnosis and Monitoring use Claude Haiku 5.5; default estimated rates are USD 0.10 input and USD 0.50 output per million tokens for these bounded requests, following [Anthropic's pricing](https://platform.claude.com/docs/en/about-claude/pricing). Admin can update the two rates independently.

Changing a rate does not change saved input/output token counts or reprice saved USD estimates. Each later call uses the updated rates; the daily/monthly graph adds each call's saved estimate, including old-rate and new-rate calls on the same day. Peso amounts use the dashboard's exchange-rate conversion.

### What can Admin do in Audit Logs?

Filter structured events by module, report type, action, actor, and month; inspect safe event details and correlated history; and update retention settings. Audit views must not be treated as raw clinical-record access.

### How do I use the patient code and NCP reference in an audit event?

The patient code lets Admin correlate events for the same patient without showing the patient’s name or opening the clinical record. An authorized RND can open **Nutrition Care → Patients (NCP Patients)**, search the code, and select the matching patient. The NCP reference distinguishes a specific Nutrition Care Process record; it is not a direct search code. After finding the patient, an authorized RND can review that patient’s NCP records to match the reference.

### Can Admin open patient NCP details?

No standing Admin clinical workflow exists. Admin can see aggregate patient counts and aggregate ADIME-cycle Demographic Census totals, but Nutrition Intervention Plan and NCP Summary are blocked server-side.

### What can Admin change in Settings?

Hospital/report branding and logos, food-service budget-per-head/day setting, local display density/reduced motion, and notification preferences.

### What is Admin's Budget access?

Read-only fiscal-year summaries, ledger entries, and activity history. Budget creation and manual adjustment remain RND responsibilities.

### How do automatic backup schedules avoid duplicates?

Daily, weekly, and monthly schedules are independent. Each runs after the displayed 1:30 AM Asia/Manila target and creates at most one automatic restore point for its due period. Enabling a schedule does not create an immediate backup. If several categories are due together, one archive satisfies all of them; repeated scheduler checks do nothing after the period is satisfied. Disabling a schedule does not delete existing restore points.

### How long are restore points kept, and what does Delete do?

Automatic retention keeps the latest 3 daily, 2 weekly, and 3 monthly restore points. Manual restore points have no automatic expiry. **Delete** moves an eligible point to **Recently deleted** for 48 hours. **Keep backup** returns it to **Restore points**; **Delete permanently** or expiry purges it when no active restoration protects it. A Pre-restore backup remains protected for 48 hours after restoration finishes, so protected rows do not show Delete.

### What do the backup views, activity stages, and types mean?

**Restore points** contains verified backups, **Failed** contains attempts with no usable archive, and **Recently deleted** contains recoverable deleted backups. **Backup activity** appears only while work is Queued, Creating, or Verifying. The type filters are Daily, Weekly, Monthly, Manual, and Pre-restore. Restoration activity progresses through queued, preparation, validation, ready-to-switch, and switching stages; finished rows state whether the restore completed, failed, was cancelled, or rolled back. One Pre-restore backup is created before every restoration attempt, so multiple protected copies can exist after separate attempts.

### What happens when Admin restores the whole system?

Restore requires the current password and exact confirmation phrase. A queued job creates and verifies a Pre-restore backup (the recovery safety snapshot), restores the selected archive into one temporary MySQL database, validates the database and matching private files, and only then enters maintenance mode to switch application data. Newer records are replaced rather than merged. A successful switch signs users out, removes the temporary database, preserves backup and audit history, and keeps the Pre-restore backup for 48 hours. A failure leaves production unchanged or automatically rolls it back.

## When to Escalate

Contact Admin/support when:

- account is inactive, role is wrong, or the correct platform rejects sign-in;
- no verified recovery email exists and password is forgotten;
- the same save/upload error remains after validation and connectivity checks;
- a menu cycle, PO, report, or patient record appears under the wrong person/date;
- a role can see data or actions it should not see;
- an archived report or audit record appears incomplete or privacy-sensitive.

Include role, screen, date/time, intended action, exact error, and a screenshot without exposing unnecessary patient information.
