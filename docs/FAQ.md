# NutriScope Frequently Asked Questions

Verified against the current web, mobile, and Laravel role gates on **2026-07-20**. This is the user-facing FAQ and source for the role-scoped in-app Help pages. When this file conflicts with an older diagram or plan, current application code wins.

## Start Here

- **RND:** use the web console for Nutrition Care Process (NCP), clinical food data, food-service planning, announcements/SOP, and reports.
- **FSS:** use the mobile app for menu viewing, meal preparation, daily accomplishments, purchasing execution, announcements/SOP, and personal accomplishment reports.
- **Admin:** use the web console for accounts, audit oversight, AI usage controls, announcements/SOP, allowed operational reports, branding, and food-service budget settings.
- For ordered instructions, see [Role How-To Guide](ROLE-HOW-TO.md).
- For presentation sequences, see [System Storyboards](STORYBOARD.md).

## Shared Account and Access Questions

### I forgot my password. What should I do?

Select **Forgot password?** on the sign-in screen, then enter your **verified recovery email**. The reset link is sent to that recovery address, not necessarily to your sign-in email.

### Why did the recovery page say a link was sent even when I received nothing?

The response is intentionally generic. Check spam, confirm you entered the verified recovery email, and wait briefly. If the account has no verified recovery email or the link never arrives, ask an Admin to reset the password.

### My reset link or token does not work. What now?

It may be invalid, expired, or already used. Request a new link. A successful reset revokes existing signed-in sessions.

### What happens on first login?

Accounts created by Admin start with a temporary password and require account setup. Set a new password of at least eight characters and add a recovery email. First-time recovery email setup is accepted as verified. You may choose **Do later**, but a reminder remains until both requirements are completed in Profile.

### Why can FSS not sign in on the website?

FSS accounts are mobile-app only. The server rejects FSS web login. RND and Admin accounts use the web console and are rejected by the FSS mobile app.

### Why does sign-in say my account is deactivated?

Admin suspended the account. Contact an Admin. Reactivating the account is an Admin action.

### Can I change my name, sign-in email, contact number, or profile photo?

Yes. Open **Profile**. Web users can update first name, last name, sign-in email, contact number, and one PNG/JPEG/WebP profile image under 220 KB. FSS mobile supports name, sign-in email, and contact number; role and status remain read-only.

### What is the difference between sign-in email and recovery email?

- **Sign-in email:** used with the password to log in.
- **Recovery email:** receives password-reset links.

Changing an existing recovery email requires a six-digit verification code. The code expires after ten minutes. The old verified address remains active until the new one is verified.

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

- **Announcement:** time-based post with category, audience visibility, pinning, text, and optional images.
- **SOP:** one current standard procedure pinned above announcements. Each revision creates a preserved version in **History**.

### Who can revise the SOP?

RND and Admin. FSS can read the current SOP and its history but cannot revise it.

### Who can edit or delete announcements?

RND can manage their own announcements. Admin can manage announcements through the Admin board. FSS reads announcements only.

### What do notification states mean?

Unread items contribute to the badge. Opening or marking an item read clears its unread state. **Mark all read** clears the current user's unread items only.

## RND: Nutrition Care Process Questions

### What is the correct NCP order?

**Patient → Assessment → Diagnosis/PES → Intervention and meal plan → Monitoring on follow-up → Reports.** The application blocks later steps until required earlier records exist.

### How do I start care for a new patient?

Open **Nutrition Care → Patients**, choose **Create Patient & Start Assessment**, enter the required patient details, and save. NutriScope creates the patient, starts an NCP cycle, and opens Assessment.

### Can a patient have more than one NCP cycle?

Yes. Open the patient profile, choose **ADIME Records**, then **Start New Cycle**. Each cycle keeps its own Assessment, Diagnosis, Intervention, Monitoring, attachments, meal plans, and activity history.

### Why does Diagnosis say Assessment Required?

The current NCP cycle has no saved assessment. Return to Assessment and choose **Save Assessment**.

### Why is Intervention blocked?

Intervention requires a saved assessment and at least one saved diagnosis.

### Why is Monitoring blocked?

Monitoring requires a saved assessment, at least one diagnosis, and an intervention/care plan. The screen is intended for follow-up visits. Current code does not separately count encounters; the saved prior-step records are the implemented gate.

### What are the Assessment sections?

Dietary, Anthropometrics, Client History, Biochemical/Labs, Referral/Screening, and RND Summary. Calculated BMI, weight-related values, risk scoring, and other clinical helpers update from entered data.

### Why must I enter dry weight when edema is present?

Edema can make measured weight unsuitable for prescription calculations. When **Edema Present** is Yes, **Dry Weight** is required before Assessment can save.

### Does uploading a lab or referral file automatically fill Assessment?

No. Current uploads are supporting-document storage for that NCP cycle. They do not run OCR or auto-populate fields. Enter or verify clinical values manually.

### What does Generate Summary do?

It creates an editable draft from current Assessment data. Review it clinically before saving. If source fields change, the page warns that the draft is stale and can regenerate it; Undo restores the previous text when available.

### Is the risk score final clinical judgment?

No. NutriScope derives a score from the entered factors. RND can switch to manual override, select factors, and compare the manual result with the automatic result. RND remains responsible for clinical interpretation.

### How do I write a diagnosis?

Open Diagnosis, choose **Add New Diagnosis**, then build Problem, Etiology, and Signs/Symptoms. Review the editable PES statement and choose **Save Diagnosis**.

### Can AI create a diagnosis automatically?

AI produces draft suggestions only. RND must review, accept, edit, or dismiss each suggestion. Saved AI suggestions are identified as AI-generated.

### What happens when I set an intervention goal?

The application selects a goal/stage, asks the backend calculation service for prescription values, shows the calculation trace, and lets RND review or edit targets before saving. If required Assessment inputs are missing, calculation warnings identify the problem.

### What is included in Intervention?

Food/nutrient delivery and prescription, food guidance, patient meal plan, education, counseling, goal planning, and encounter context including follow-up details.

### Can I make a patient meal plan manually or from a template?

Yes. Meal plans can be created manually, generated, or loaded from a saved template. Review allergens, restrictions, portions, nutrition totals, and variance against the saved prescription before use.

### What can I record in Monitoring?

Follow-up visit data, goal progress, anthropometrics and selected clinical indicators, intake/tolerance, symptoms, next monitoring date, and progress trends. Visit Log records entries; Progress Trends summarizes changes from baseline and targets.

### Can I delete a patient or NCP cycle?

Only before an NCP cycle has all three protected records: Assessment, at least one Diagnosis, and Intervention. Once all three exist, that cycle—and therefore a patient containing it—is protected from normal deletion.

### Where are patient attachments?

Upload them from the cycle's Assessment page. The patient profile **Attachments** tab groups files by NCP cycle so records do not mix.

## RND: Food Library and Food-Service Questions

### What is the difference between Food Library, Inventory, and Foods?

- **Food Library:** clinical foods and clinical recipes with nutrients, macros, micronutrients, allergens, and optional USDA source data.
- **Inventory:** food-service reference catalog for ingredients and supplies, vendor, unit, and purchase cost. It is not a live stock-count workflow.
- **Foods:** food-service recipes and single-ingredient items used in menu cycles.

### How do I import food nutrient data?

Open **Food Library → Foods → Import from USDA**, search, import, then review category, nutrients, and allergen suggestions. Branded USDA foods are excluded from the current search source.

### Does FSS manage inventory quantities?

No. Current FSS mobile navigation has no Inventory tab or stock add/deduct workflow. The web Inventory page is an RND reference catalog.

### How do I build a food-service menu cycle?

Prepare Inventory and Foods first. Open **Food Service → Menu Cycle**, create a Monday-anchored week or load a template, add recipes or single items, save, then activate. A blank name is generated from the date span. Baseline profiles remain visible; one purchase estimate is entered later when generating a suggested list.

### What is a menu-cycle template?

A reusable saved layout. Loading it copies the menu structure into a new dated cycle; edits to the cycle do not edit the template. Review dates and foods before activation.

### What does activating a menu cycle do?

It makes that cycle the active operational week shown to FSS. FSS can view it but cannot author or activate it.

### How is a suggested food shopping list created?

Open **Procurement → Food Shopping Lists → Suggest from Menu**, select a date range, enter one estimated serving count for the span, and generate. Every date must have assigned menu items; otherwise creation is blocked with the exact missing dates. Ingredients marked **Purchase when needed** remain in recipes but are not auto-added.

### Can I make a food or event list without a menu cycle?

Yes. Create a named manual food list and add ingredients directly, just as you would write a shopping list. Related food and supplies lists can use the same event name while remaining separate procurement tracks.

### How are supplies purchased?

Use **Procurement → Supplies Lists**, create a named manual list, add catalog items, quantities, costs, and vendors, then convert it separately from food procurement.

### What happens when a shopping list is converted?

First, review the release checklist. Calculated need stays read-only, but purchase quantity/unit/price/vendor may be changed, manual rows added, and generated rows excluded with a note. When checks pass, **Create and release PO** creates one order grouped by vendor and freezes included rows.

### Who records OR numbers and receipts?

FSS or RND confirms actual quantity and unit price, uploads receipt and proof, then explicitly marks each vendor received. OR number may be recorded when the vendor provides one, but it is optional.

### When does a food purchase order complete?

When every vendor has reviewed actual values, receipt, proof, and explicit received status, and every covered service date has actual served population. Manual food and supplies POs require vendor completion but not served population.

### Why is a PO still open after all receipts were uploaded?

A receipt alone is insufficient: check proof, reviewed actual values, and the vendor's explicit received status. For a suggested food PO, also review **Served days** and record any missing population from Menu/Meal Prep.

### What is budget per head per day?

The planned limit comes from **Settings → Food Service Budget**. Estimated values use included planned purchases and the span estimate. The final **food purchase cost per served patient-day** uses confirmed purchase cost divided by actual served population for the covered period.

### Who can change the fiscal-year budget?

RND can set up budgets and create manual ledger adjustments. Admin's Budget page is read-only, but Admin can change the shared budget-per-head/day setting in Admin Settings.

## Reports Questions

### What is the difference between live preview and archived report?

Live preview renders from current data. **Archive** freezes the as-filed copy so later data or branding changes do not alter it.

### Which reports can RND access?

Program Project Activity, Menu Calendar, Procurement Pack, Accomplishment Report, Demographic Census, Patient Menu Plan, and NCP Summary.

### Which reports can Admin access?

Program Project Activity, Menu Calendar, Procurement Pack, Accomplishment Report, and aggregate Demographic Census. Admin cannot access Patient Menu Plan or NCP Summary.

### Which reports can FSS access?

Only their own Accomplishment Reports.

### Why is an accomplishment report not archived yet?

The FSS user needs one entry for every day from Monday through Sunday. An off-duty entry counts and appears as **X**.

### Can an archived report be changed by editing source data?

No. It is a frozen snapshot. Correct current source data and create a new archive if an updated filed copy is needed.

## FSS Mobile Questions

### What are the current FSS tabs?

Five tabs: **Home**, **Menu**, **Meal Prep**, **Accomplish**, and **Purchase**.

### What should I do first each day?

Check Home for meals to log, pending POs, the active menu cycle, today's service, and announcements. Then handle Purchase receipts, Meal Prep, and Accomplish as work occurs.

### Why does Home say there is no active menu cycle?

RND has not activated a current cycle. Contact RND; FSS cannot create or activate one.

### Can I edit the menu?

No. Menu is read-only for recipes/items and preparation details. FSS may enter or backfill actual served population for service dates.

### How do I mark today's meals served?

Open **Meal Prep**, enter actual total patient population if requested, review today's service rows, then use the served/completion action. If there is a shortfall, confirm the warning only when service should proceed with the recorded exception.

### Where should I enter my daily accomplishment?

Use **Accomplish**. Enter ward and meals distributed, select completed duties, or mark Off duty/absent. Save one accurate entry for the day; additional ward entries may be recorded when needed.

### Why does Off duty save an X?

It is the explicit daily record for a non-working day and counts toward Monday-Sunday report completeness.

### What can I change on a purchase order?

FSS can review the calculated values, confirm or correct actual decimal quantity and actual unit price, upload/delete receipt and proof, optionally save an OR number, and explicitly mark a vendor received. Planned structure and supplier remain locked.

### Why did uploading a receipt not change the vendor status?

Uploading evidence does not silently receive a vendor. Upload both receipt and proof, review actual values, then use **Mark vendor received**. OR number is optional.

### Why can I no longer edit a completed PO?

Completed and archived POs are locked to protect filed operational history.

### Why does camera or photo upload fail?

Allow camera/photo-library permission, confirm network access, and retry with a supported image. If denied earlier, enable permission in the device settings.

### Where are my reports?

Open **Accomplish → My reports**. FSS sees only their own archived accomplishment reports.

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

Admin can set daily and monthly token limits; blank means unlimited. Admin also configures estimated USD cost per one million tokens for dashboard cost display.

### What can Admin do in Audit Logs?

Filter structured events by module/action/actor and other available filters, inspect safe event details and correlated history, export permitted audit data, and update retention settings. Audit views must not be treated as raw clinical-record access.

### Can Admin open patient NCP details?

No standing Admin clinical workflow exists. Admin can see aggregate patient counts and aggregate Demographic Census, but Patient Menu Plan and NCP Summary are blocked server-side.

### What can Admin change in Settings?

Hospital/report branding and logos, food-service budget-per-head/day setting, local display density/reduced motion, and notification preferences.

### What is Admin's Budget access?

Read-only fiscal-year summaries, ledger entries, and activity history. Budget creation and manual adjustment remain RND responsibilities.

## When to Escalate

Contact Admin/support when:

- account is inactive, role is wrong, or the correct platform rejects sign-in;
- no verified recovery email exists and password is forgotten;
- the same save/upload error remains after validation and connectivity checks;
- a menu cycle, PO, report, or patient record appears under the wrong person/date;
- a role can see data or actions it should not see;
- an archived report or audit record appears incomplete or privacy-sensitive.

Include role, screen, date/time, intended action, exact error, and a screenshot without exposing unnecessary patient information.
