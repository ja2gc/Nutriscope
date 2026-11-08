# RND Website User Manual

Document version: 1.0 | Issued: 5 November 2026

Prepared by: EMPhive — Louis Figo, Jhobe Fajardo, Maria Beatriz Dela Cruz and Jared Abriol.

Use this manual for routine work and staff training. Start with the prerequisites, follow the visible navigation and numbered steps, and confirm the saved result before continuing. Actions depend on account role, ownership and record state.

[All role manuals](../docs/ROLE-HOW-TO.md) · [Setup and turnover](../docs/operations/README.md) · [FAQ](../docs/FAQ.md)

## Before you begin

Use the institution's NutriScope website with an active **RND** account. Complete account setup, read the current SOP and confirm your authorized responsibilities. If you are presenting or practicing, use a separate training installation with fictional records; the steps below are the same work instructions used in normal operation.

On a narrow screen, select **Open navigation** in the top bar. The sidebar shows the full menu; use its top arrow (**Close navigation**), select a destination, tap outside it or press **Escape** to close it. Open **My Profile** through the top-right profile button; account details, recovery email and password appear as sections on one scrolling page.

### One-time and prerequisite setup

| Feature you want to use | Prepare first | Where to navigate and what to save |
|---|---|---|
| Clinical ingredient search/import | Operator configures USDA access for external search; existing local ingredients need no import request | **Food Library → Ingredients**; search/open a profile or add/import the reviewed ingredient |
| Clinical recipe/oral menu item | Clinical ingredients with correct measurement basis, nutrients and allergens | **Food Library → Menu Items**; create an item, choose its type, add measured ingredients, set servings and save |
| Clinical template | Appropriate clinical menu items or tube formulas | **Food Library → Templates** or the saved Intervention menu/formula collection; review the contents before saving/reusing |
| Assessment | Correct patient and open ADIME cycle | **Nutrition Care → Patients**; open/create patient, select the current cycle, then **Assessment** |
| Diagnosis | Saved Assessment and clinically supported findings | **Diagnosis → Add New Diagnosis**, or **AI Review → Generate AI Suggestions** followed by clinician review |
| Oral intervention planning | Saved Assessment and diagnosis, **Oral / Per Orem** support selection and prescription inputs | **Intervention**; set/review prescription, then create/generate/load an oral menu and save it |
| Tube intervention planning | **Tube Feeding** support selection and a saved/created tube formula | **Intervention → Food / Nutrient Delivery → Select formula / Create formula**; measured formula contents are distinct from oral weekly menus |
| NPO / TPN care | **NPO / TPN — No food menu** support selection and applicable prescription/guidance | **Intervention**; save prescription and guidance without creating a food menu |
| Monitoring | Saved Assessment, diagnosis and Intervention; a follow-up visit | **Monitoring → Visit Log**; enter findings, summary and required Care Decision, then save |
| Food-service menu item | Operational ingredients/supplies, units, costs and applicable vendors | **Food Service → Inventory → Ingredients / Supplies**; create or review the purchasing references first |
| Operational recipe | Ingredients from the operational Inventory, suitable units and baseline servings | **Food Service → Inventory → Menu Items**; create the dish and save measured ingredients/servings; clinical Food Library items are a separate catalog |
| Weekly Menu Calendar | Saved operational menu items and a selected week | **Food Service → Menu Calendar**; add meal-slot items or load a template; review and save |
| Reusable operational template | A prepared weekly menu or existing template | In **Menu Calendar**, use template controls to save/reuse the plan; review the target week's editable slots |
| Suggested shopping list | Menus saved for every requested date and an estimated serving count | **Food Service → Procurement**; choose the span, generate and resolve exact missing-plan dates |
| Manual food/supplies list | Operational references and a procurement date | **Procurement**; choose the manual list type, enter the date/purpose and add the actual intended items |
| PO conversion | Reviewed shopping list; suggested lists require valid estimate/date coverage | Use **Turn Into PO**, review the lock warning, then **Turn Into PO and lock list**; optional item exclusions are not mandatory confirmation gates |
| Vendor receiving | Saved actual values plus required receipt/proof; RND review | **Procurement → PO → vendor**; inspect values/evidence and select **Mark vendor received** |
| Suggested food PO completion | Every covered date has actual served population and required vendor work is complete | FSS saves population in **Meal Prep**; RND reviews the completed PO and resulting budget/report records |
| Clinical or operational PDF | Saved qualifying source record and correct report template/branding | **Reports**; choose the type, patient/cycle or period/record, preview, then download/archive as appropriate |

USDA/AI/email keys, server configuration and initial institutional data are handled by the designated operator through the [setup manual](../docs/operations/README.md). They are not entered in the clinical screens. Production reference seeding supplies the food-service catalog, estimated 50-serving recipes and four reusable menu-post rotations without fictional patients or purchasing history. The operator assigns retained clinical references to the real RND account using the [initial-data procedure](../docs/operations/initial-data.md#clinical-references-for-a-real-rnd). Review portions, prices and preparation before using the food-service references; rice, oil, salt and pepper require manual purchasing. **Show snack slots** can hide empty AM/PM rows in the food-service planner without deleting content.

### What belongs in each library

**Food Library** is for clinical nutrients, recipes, formulas and patient plans. **Food Service → Inventory** is for operational ingredients, supplies, costs and kitchen Menu Items. Prepare the corresponding library for the feature you are using. Importing a USDA ingredient does not create an operational purchasing item, and an Inventory item does not automatically have the clinical nutrient profile required for intervention planning.

## Page directory

| Page or panel | How to reach it | What to do |
|---|---|---|
| Sign in | Open the institution's NutriScope website or FSS app | Use the account's correct platform; [sign-in instructions](#shared-sign-in-and-secure-the-account) |
| Forgot/reset password | Choose the password-recovery option on sign in | Request recovery through the verified recovery mailbox, follow the received reset link and set a new password; contact Admin when recovery is unavailable |
| Account setup | First-login prompt or Profile reminder | Change the temporary password, add/verify recovery email and complete outstanding requirements |
| Profile | Web account menu or mobile Settings | Maintain identity/contact, recovery email, password and supported profile photo controls; role/sign-in email fields can be display-only |
| Notifications | Web Notifications/bell or mobile bell | Open exact linked work, distinguish read from resolved and dismiss only eligible items |
| Help | Web sidebar; mobile Settings → Help & Support → Help | Search the role's available topics and expand answers |
| Mobile download | Website `/mobile-app` | Scan the QR or download/install the institution's signed APK; see [Android operation](<FSS User Manual.md#android-installation-and-updates>) |

### RND pages

| Page or panel | Visible navigation | Procedure |
|---|---|---|
| Dashboard | Dashboard | [Start the day](#1-start-the-day-on-dashboard); open patients, appointment queues and pending purchasing work |
| Patient directory | Nutrition Care → Patients | [Create/select patient](#a-create-or-select-a-patient); search/filter and open the correct record |
| Patient profile | Open a patient | Overview, ADIME Records, Appointments and Attachments; current/past-cycle links retain their own records |
| Appointment/visit controls | Patient Appointments and ADIME header | [Start, finish and resolve visits](#b-start-and-finish-a-visit) |
| Assessment | Nutrition Care → Assessment with a selected patient/cycle | [Assessment](#c-assessment); complete sections, verify calculations and save |
| Diagnosis | Selected cycle → Diagnosis | [PES and AI review](#d-diagnosispes); review each draft before saving |
| Intervention | Selected cycle → Intervention | [Prescription, support type, guidance and dated plans](#e-intervention-and-patient-meal-plan) |
| Monitoring | Selected cycle → Monitoring | [Follow-up, trends and care decision](#f-monitoring-and-evaluation) |
| Clinical Ingredients | Food Library → Ingredients | [Clinical food data](#3-maintain-clinical-food-data); manual entry/USDA import and profiles |
| Clinical Menu Items | Food Library → Menu Items | Create/edit a clinical recipe or tube formula; ingredient measurements produce its nutrient totals |
| Clinical Templates | Food Library → Templates | Review reusable oral menus/formula collections and select the matching support type |
| Food Calculator | Food Library → Food Calculator | Compare foods/amounts without saving a patient Assessment |
| Ingredients/Supplies | Food Service → Inventory | [Purchasing reference catalog](#a-inventory-reference-catalog); unit, cost, vendor and grocery inclusion |
| Operational Menu Items | Food Service → Inventory → Menu Items | [Food-service dishes](#b-foods-and-recipes); recipe creation/profile and single-item forms |
| Menu Calendar | Food Service → Menu Calendar | [Weekly plans](#c-menu-cycle), template selection and slot/line details |
| Menu templates | Menu Calendar template controls | Browse/reuse saved templates; instantiate into the selected editable week and review quantities |
| Procurement | Food Service → Procurement | [Suggested/manual lists, purchase orders, evidence and receiving](#d-procurement) |
| Budget | Food Service → Budget | [Allocation, ledger and adjustments](#e-budget) |
| Announcements/SOP | Announcements | [Publish/read posts and SOP history](#5-announcements-and-sop) |
| Reports and patient-report detail | Reports | [Browse, patient/cycle/dated-plan selection, archives and template editing](#6-reports) |
| Settings | Settings | [Display, notification and budget preferences](#7-help-notifications-settings-and-profile) |

Detail/profile pages open from their parent list, item card or selected menu slot. Return to the parent after saving and confirm the saved result. Older `/recipes`, `/food-database` and food-service Foods list addresses redirect into the current Food Library/Inventory areas; use the visible navigation above.

## Daily and periodic work

| Role/time | Work sequence | Completion check |
|---|---|---|
| RND, start of day | Dashboard; appointments/follow-ups; announcements/SOP; today's menu and pending POs | Required visits and receiving/planning work identified |
| RND, each patient | Verify patient and cycle; start visit; review/update applicable ADIME steps; save; finish visit and schedule follow-up | Correct saved clinical record, care decision and visit outcome; no unattended active visit |
| RND, food-service planning | Maintain ingredients/menu items; plan week; generate dated shopping span; review quantities/vendors; release PO | Covered dates planned and PO snapshot ready for execution |
| RND, delivery/closeout | Inspect FSS actuals and receipt/proof; explicitly mark vendor received; ensure actual population recorded where required | Completed PO, ledger and report figures reflect real recorded work |
| FSS/RND, reporting period | FSS completes all required dates and submits the period; RND approves or rejects with reason; FSS corrects and resubmits if needed | Approved PDF available; approved logs locked |

## Shared: Sign In and Secure the Account

### First sign-in and the persistent reminder

1. Sign in using the account's **sign-in email** and temporary password. Use the institution's website for RND/Admin and Android app for kitchen staff.
2. In account setup, enter and confirm a new password of at least eight characters, then select **Next**.
3. Enter a recovery email whose inbox you can access and select **Send verification code**.
4. Open that inbox, enter the six-digit code and select **Verify recovery email**. The code expires after 10 minutes; **Send another code** replaces it, so use the newest code.
5. **Do later** defers setup. After deferral, the reminder stays until both the password and recovery-email requirements are finished. Select **Open settings** on the reminder to continue in Profile.
6. Confirm your name in Profile; reports and audit attribution use it. Keep temporary credentials and verification codes private.

### Edit account details and photo

1. Open **Profile** using the top-right profile button. Select **Edit Profile** (**Edit** on narrow screens) to change your name or contact number.
2. The photo shows your saved picture, or your name's first letter if no picture is saved. Click the circle or its camera badge, then select **Choose profile picture** or **Change profile picture**. Choose a JPEG, PNG or WebP image up to 2 MB, drag/zoom the circular crop and select **Apply crop**. The account editor opens with the new preview.
3. Review the changes, then select **Save Changes**. To remove a saved picture, open its photo menu and select **Delete profile picture**; the preview returns to your initial and you must save to confirm. **Cancel** discards unsaved details and photo changes. Sign-in email and role are display-only.

### Add, replace or remove recovery email

1. Open **Profile**. The **Recovery email** section shows the current address, or **Not set**. Select **Add** when no address is set; otherwise select **Edit → Change**. The help icon beside the heading explains verification.
2. Enter the new inbox and select **Send Verification Code** (**Send code** on narrow screens). If validation or sending fails, correct the error in the email form. After a successful send, the email field is replaced by **Verification Code**. Open the inbox, enter the newest six-digit code within 10 minutes, then select **Verify Recovery Email** (**Verify** on narrow screens).
3. The section returns to the address display after success. The old inbox remains active until the replacement is verified. **Cancel** discards the unfinished replacement, invalidates its code and returns to the original address; it does not remove the current address. If the code expires, cancel and start again to request a new one.
4. To remove the current address, select **Edit → Remove** and confirm. Remove is offered before starting a replacement. Removal disables reset emails until another address is verified and brings back the persistent setup reminder. Verify a replacement promptly.
5. To change a known password, select **Change Password** (**Change** on narrow screens), enter the current password and enter/confirm the new one, then select **Update Password** (**Save** on narrow screens). Sign in again after the change.

### Forgot password or missing reset email

1. From sign-in, select **Forgot password?** Enter the account's **Sign-in Email**, then select **Send Reset Link**. Do not enter the recovery address in place of the sign-in address.
2. Open the **verified recovery inbox**, including its spam folder. The generic confirmation on the request page does not prove mail was sent; an account without verified recovery cannot receive the link.
3. Open the newest message within 15 minutes. A newer request invalidates the earlier link. The link opens the web reset form with **Sign-in Email** and **Reset Token** supplied.
4. Enter **New Password** and **Confirm New Password**, then select **Reset Password**. Sign in again; the reset ends previous sign-ins.
5. If no usable message arrives, confirm the sign-in address and verified inbox before requesting again. If recovery was never verified, the inbox is inaccessible or the latest link still fails, contact the institution's administrator for an account reset. Do not send passwords or codes in a support message.

## RND Web Workflow

### 1. Start the Day on Dashboard

1. Review **Patients in Care** and the scheduled/overdue appointment queue.
2. Review **Pending POs** and what each PO still needs.
3. Read announcements.
4. Open the relevant patient or operational queue.

### 2. Run the Nutrition Care Process

#### A. Create or Select a Patient

1. Open **Nutrition Care → Patients**.
2. Search/filter and open an existing patient, or choose **Create Patient & Start Assessment**.
   **Last Clinical Review** shows the latest Assessment/lab or Monitoring save, or a valid visit start/finish, across the patient's care cycles. It displays **Today** or days ago using Manila calendar days. Scheduled-only, cancelled and discarded mistaken visits do not advance this date.
3. On a patient profile, use:
   - **Overview** for identity, current status, risk, and current-cycle snapshot.
   - **ADIME Records** for the current cycle and the always-visible, two-per-page Past Records history.
   - **Appointments** for scheduled/walk-in visits, written purpose, attendance status, administering RND, and history.
   - **Attachments** to find supporting documents grouped by NCP cycle.

#### B. Start and Finish a Visit

1. In **Appointments**, create a Scheduled visit with date/time and purpose, or start a Walk-in for the current cycle.
2. A scheduled visit starts only when the RND selects **Start Visit**; time alone never starts it.
3. Use the shared visit controls on Assessment, Diagnosis, Intervention, or Monitoring. One visit may include several ADIME steps.
4. If you leave NCP, use the global **Resume** banner to return to the same patient and cycle.
5. Use **Finish Visit** for a normal end, **End Early** with a reason for an interrupted visit, or **Discard Mistaken Start** only before clinical work is saved.
6. Resolve unattended schedules as **No-show**, **Cancelled** with a reason, or **Rescheduled**. Rescheduling preserves the old record and creates a replacement.
7. Review Past Appointments for status, purpose, work recorded, and **Administered by**. Attendance outcomes do not count as completed clinical care.

#### C. Assessment

1. Open the cycle's **Assessment** step.
2. Complete Dietary, Anthropometrics, Client History, Biochemical/Labs, Referral/Screening, and Summary.
3. If edema is present, enter dry weight.
4. Upload supporting lab/referral documents if needed; manually enter/verify values because uploads do not OCR/autofill.
   Open **Supporting documents** and use **Add file** or drag and drop a supported PDF/image. Uploads save immediately. Preview/download documents from their rows; the small X removes only that attachment.
5. Review calculated BMI, other anthropometrics, and nutritional-risk factors.
6. Generate or edit the RND Summary.
7. Choose **Save Assessment**.

#### D. Diagnosis/PES

1. Open **Diagnosis** after Assessment saves.
2. Choose **Add New Diagnosis**.
3. Select domain: Intake, Clinical, or Behavioral-Environmental.
4. Build Problem, Etiology, and Signs/Symptoms.
5. Review and edit the generated PES statement.
6. Save the diagnosis.
7. Optional: open **AI Review** and choose **Generate AI Suggestions**. Opening/refreshing review alone does not generate. Review the PES, evidence and review notes in **No issues detected** or **May need more thorough review**; both groups still require clinical judgment.
8. Use **Review and edit** when a draft needs correction. Review notes describe uncertainty in the cited support; check the whole Assessment before correcting the draft. Incomplete proposals are not displayed as diagnoses. On generation failure, retry or add the diagnosis manually; do not treat a failed request as a completed clinical review.

#### E. Intervention and Patient Meal Plan

First select one **Nutrition support** choice in Assessment: **Oral / Per Orem**, **Tube Feeding**, or **NPO / TPN — No food menu**. A saved plan retains its support type. The steps below describe Oral menus.

1. Open **Intervention** after at least one diagnosis saves.
2. Set goal and disease stage when applicable.
3. Review backend-generated prescription targets and the calculation trace.
4. Resolve missing Assessment inputs or enter justified values manually.
5. Save food/nutrient delivery targets.
6. Review food recommendations.
7. Create, generate, or load a patient meal plan template. Loaded templates preserve exact items; use **Scale to prescription** to adjust quantities without substitutions. Auto-generation can **Exclude snacks** when the selected goal permits it. Check allergens, restrictions, portions, nutrients, and variance.
8. Complete Education, Counseling, and Goal Planning.
9. Save each section. Do not leave while the page shows unsaved changes.

For **Tube Feeding**, Food / Nutrient Delivery contains formula columns without weekdays. Select Formula 1, 2, etc. to show its macros and prescription-selected micros; cards show only names, open on the whole card, and use the menu-planning X to remove them. Profiles and reports retain exact planned measurements. Fluid shares the macro card. **Select formula** in the next empty column selects a saved Tube Feeding Menu Item; **Load Template** copies a formula collection. **Create formula** opens the existing Menu Item form with Tube Feeding selected. Choose **Use in this plan** or **Save to Food Library and use**. Cancel follows the existing unsaved-change popup. Open a formula profile to edit its multiplier or measured ingredients; later multiplier changes preserve ingredient edits. **Reset recipe** restores the captured recipe. **Save as template** captures the prepared collection; library template profiles remain view-only. **NPO / TPN** retains prescription and guidance without a menu. Generated reports follow the saved support type. Census shows the three support types, separate pediatric/adult BMI groups from initial Assessments, and all nutrition care categories in Assessment order, including zeros.

Food Library also includes a full-tab **Food Calculator**, reusing the Food Recall calculator without saving to a patient.

#### F. Monitoring and Evaluation

1. Use Monitoring after Assessment, Diagnosis, and Intervention exist and the patient returns for follow-up.
2. In **Visit Log**, record date, clinical indicators, intake/tolerance, symptoms, and goal status.
3. In **Progress Trends**, compare current results with baseline and prescription targets.
4. Enter the clinical summary manually or choose **Generate AI Summary**, review the proposal and select **Use this summary**. If the underlying inputs change, generate/review again before using the proposal.
5. Select the required **Care Decision**: Continue, Modify or Discontinue, according to the actual clinical decision. Save the entry and confirm it appears in the visit history.
6. Modify does not itself create a revised Intervention Plan. Open Intervention and create/save the appropriate dated plan when revision is required. Schedule the next visit through the shared visit controls or Appointments tab.

#### G. Close or Preserve an ADIME Cycle

1. Open **Patient → ADIME Records → Current Cycle → Actions**.
2. Use **Complete and Protect** only after Assessment, Diagnosis, and Intervention are clinically complete.
3. Use **Discontinue** with a reason when care stops before normal completion.
4. Use **Delete** only for an open cycle that has not completed all three protected sections.
5. Completed and discontinued cycles move to Past Records. Starting a new cycle never changes them.

### 3. Maintain Clinical Food Data

1. Open **Food Library**.
2. In **Ingredients**, choose **Add Ingredient** for manual entry or **Import from USDA** for external search. Review category, serving/unit basis, nutrients and allergens before saving/importing. Open a result/profile before importing; an import writes a library record, while searching/previewing does not.
3. In **Menu Items**, choose **Create Menu Item**. Enter the name, category/support type, recipe yield and applicable preparation notes. Under **Nutrition source**, choose **Ingredients** to calculate from measured ingredients or **Nutrition facts label** for a branded item's actual label. Enter the required serving basis and values for that choice; do not mix incompatible units. Review totals and choose **Save Menu Item**. Open an existing item's profile to review/edit its ingredients.
4. In **Templates**, review oral menus and formula collections separately. Use the matching support type when loading a template in Intervention. In **Food Calculator**, select foods and amounts to compare totals without writing patient data.
5. Keep this library distinct from food-service purchasing data.

### 4. Plan and Supervise Food Service

#### A. Inventory Reference Catalog

1. Open **Food Service → Inventory**.
2. Maintain ingredients and supplies with name, vendor, applicable base unit and purchase cost. Turn off **Include in generated shopping lists** for bulk pantry ingredients that should remain in recipes but not automatically appear in generated groceries.
3. Treat it as a reference catalog, not live stock quantity.
4. For an ingredient, select **Ingredients → New Ingredient**, enter Name, Vendor if known, Unit and Cost/unit, then **Create**. Use **Include in generated shopping lists** to control automatic grocery inclusion. For supplies, select **Supplies**, use that tab's plus/New control, enter Name, Vendor and Cost/unit, then **Create**; supplies have no user unit input.
5. If a vendor is missing, open **Food Service → Procurement → Suppliers → New Vendor**, enter the vendor details and save, then return to Inventory and select it. Unassigned vendors can be chosen later before receiving.
6. When changing a unit used by recipes, follow the displayed affected-recipe correction links and enter compatible measurements before saving the Inventory change. Use the unit help rather than assuming a piece/pack converts to weight.

#### B. Foods and Recipes

1. Open **Food Service → Inventory → Menu Items**.
2. Choose **New Recipe** for a food-service dish, or use **Single food item** when the menu item contains just one ingredient.
3. Set category, servings, ingredients, quantities, costs, and preparation notes.
4. Confirm recipe/item profiles before menu use.
5. Select **Create Recipe** or **Create single food item**, according to the form. Reopen the item and confirm its measured ingredients and baseline servings. Recipe quantities scale from this baseline; choosing a large baseline is not the actual served population for every future day.

#### C. Menu Cycle

1. Open **Food Service → Menu Calendar**.
2. Open the current empty week or choose the next unplanned week and load a template.
3. Set the week and add one or more ordered meal recipes/items to each slot. Leave the name blank to use the date-span name. Keep bulk rice out of the menu itself.
4. Review baseline ingredients and preparation notes; the purchase estimate is set later for the whole shopping span.
5. Save; optionally save as template. Add bulk **Rice** in kilograms later through the generated shopping-list draft's existing add-item control; an empty search recommends it first.
6. The current week applies by date automatically, even while empty. Four future weeks are maintained. Once a PO is generated for a planned week, its menu is read-only.

#### D. Procurement

1. Open **Food Service → Procurement**.
2. For suggested food, select the date span, enter one estimated serving count, and generate. For an event/manual food list, name it and add ingredients directly.
3. Fix every missing menu date shown by validation; regenerate.
4. Keep calculated need visible while reviewing purchase quantity/unit/price, vendor, manual additions, and exclusions.
5. Create a named manual Supplies List when needed; a related event can use the same purpose name.
6. Review suggested-date coverage and estimate, then choose **Turn Into PO**. Read the warning and choose **Turn Into PO and lock list** to create one vendor-grouped order. Vendors/costs and optional row exclusions need careful review, but are not separate mandatory item-confirmation or budget gates.
7. During execution, monitor actual values, receipt/proof, optional OR, explicit vendor received status, served-day progress, totals, and history.
8. Correct authorized open-execution values when needed; completed/archived POs are historical records.
9. Within a vendor, **Actual receipt total** normally follows **Use calculated total**. Enter the receipt's actual charge if cash rounding changes it; returning to **Use calculated total** restores the live item sum without changing item quantities/prices. After reviewing actuals and required evidence, use **Mark vendor received**. A receipt upload alone does not perform that action.
   Before **Mark vendor received**, use **Add images** or drag and drop receipts/proofs within the displayed limits. Uploads save immediately. The small X removes only its image. Click a thumbnail for the plain viewer, with arrows for multiple images, an exit X and **Remove image**.

#### E. Budget

1. Open **Food Service → Budget**.
2. Select/setup the fiscal year and allocated amount.
3. Review summary and ledger.
4. Enter a manual adjustment only with a clear operational reason.
5. Configure the shared per-head/day limit in **Settings → Food Service Budget**.

### 5. Announcements and SOP

1. Open **Announcements**.
2. Review the current SOP and History before publishing procedure-sensitive work.
3. Add an announcement with category, required body text, optional title, pin state, and optional images. Under **Visible to**, select one or more roles; select all three for everyone. Include RND if other RND users should receive it. Audience choices appear in the editor only. The feed previews three body lines; click anywhere on a card for the full post. Verify the author's profile photo and bounded post image.
   Use **Add images** or drag and drop within the displayed limits. Click a thumbnail to view it, or its small X to remove only that image. Image edits take effect when the announcement is posted or saved.
4. Edit/delete only authorized posts.
5. Use **Revise SOP** for a true procedure revision; saving creates a new history version.

### 6. Reports

For **PPA** and **Procurement Pack**, **Procurement type** defaults to **All** and can select **Menu-suggested food**, **Manual food**, or **Supplies**. Suggested-food PPA output shows recorded served counts for each service date with one total cost. Manual/supplies PPAs show purchased items and procurement date without menu, population or per-head sections. AIR keeps the hospital header/logos and centered item table, leaves manual PO/invoice fields blank, and includes Complete/Partial entries. In **Template Edit**, enter AIR signatory names under their position labels. Filed reports retain their captured configuration.

1. Open **Reports → Browse**.
2. Choose Food Service or Clinical report type.
3. For **Patients NCP** under Clinical, choose the patient, choose one ADIME cycle, then open only the reports filed under that cycle.
4. For other report types, select the record/period and open live preview. For Accomplishment Report, use **To Review** to inspect a submitted FSS period and its PDF, then approve or reject it with a required reason of up to 500 characters.
5. Find approved and earlier generated accomplishment reports in **Reports**; unsubmitted logs are not available to RND.
6. Validate data and branding.
7. Download live output when appropriate, or **Archive** to freeze the formally filed copy.
8. Use **Archived** to view/download frozen copies and activity history.
9. Use **Template Edit** to maintain report branding/signatories when authorized.

### 7. Help, Notifications, Settings, and Profile

1. Open **Help** in the sidebar to search Shared and RND-only answers. Expand a question to read its answer; clear the search to browse by topic.
2. Open Notifications for announcements and appointment/follow-up reminders. Selecting an actionable item opens its exact record. Informational or resolved items can be dismissed; unresolved action-required items cannot.
3. Use Settings for local density/motion, notification preferences, and food-service budget limit.
4. Use Profile to view sign-in email and manage identity, contact, circular drag/zoom photo crop, recovery email, and password.

## End-of-work check

Confirm the correct date and record, save outstanding changes, read any validation or failure message, and reopen the saved record when confirmation is uncertain. Do not treat a failed network request as a successful save. Sign out on shared devices. For cross-role responsibilities use [the handoff sequence](../docs/ROLE-HOW-TO.md#cross-role-operational-closeout).
