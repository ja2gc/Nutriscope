# NutriScope Role How-To Guide

Verified against current navigation and server permissions on **2026-07-20**. Follow each role's sequence in order. See [FAQ](FAQ.md) for exceptions and troubleshooting.

## Shared: Sign In and Secure the Account

1. Use the correct platform: RND/Admin on web; FSS in the mobile app.
2. Enter sign-in email and password.
3. On first login, replace the temporary password and add a recovery email.
4. If setup was deferred, open **Profile** and finish both items when the reminder appears.
5. In Profile, confirm first/last name because reports and audit attribution use the account name.

## RND Web Workflow

### 1. Start the Day on Dashboard

1. Review **Patients in Care** and scheduled follow-ups.
2. Review **Pending POs** and what each PO still needs.
3. Read announcements.
4. Open the relevant patient or operational queue.

### 2. Run the Nutrition Care Process

#### A. Create or Select a Patient

1. Open **Nutrition Care → Patients**.
2. Search/filter and open an existing patient, or choose **Create Patient & Start Assessment**.
3. On a patient profile, use:
   - **Overview** for identity, current status, risk, and current-cycle snapshot.
   - **ADIME Records** to start/continue cycles and inspect cycle summaries.
   - **Attachments** to find supporting documents grouped by NCP cycle.

#### B. Assessment

1. Open the cycle's **Assessment** step.
2. Complete Dietary, Anthropometrics, Client History, Biochemical/Labs, Referral/Screening, and Summary.
3. If edema is present, enter dry weight.
4. Upload supporting lab/referral documents if needed; manually enter/verify values because uploads do not OCR/autofill.
5. Review calculated BMI, other anthropometrics, and nutritional-risk factors.
6. Generate or edit the RND Summary.
7. Choose **Save Assessment**.

#### C. Diagnosis/PES

1. Open **Diagnosis** after Assessment saves.
2. Choose **Add New Diagnosis**.
3. Select domain: Intake, Clinical, or Behavioral-Environmental.
4. Build Problem, Etiology, and Signs/Symptoms.
5. Review and edit the generated PES statement.
6. Save the diagnosis.
7. Optional: use **AI Review** to generate drafts; accept, edit, or dismiss after clinical review.

#### D. Intervention and Patient Meal Plan

1. Open **Intervention** after at least one diagnosis saves.
2. Set goal and disease stage when applicable.
3. Review backend-generated prescription targets and the calculation trace.
4. Resolve missing Assessment inputs or enter justified values manually.
5. Save food/nutrient delivery targets.
6. Review food recommendations.
7. Create, generate, or load a patient meal plan template; check allergens, restrictions, portions, nutrients, and variance.
8. Complete Education, Counseling, Goal Planning, and Encounter Context.
9. Save each section. Do not leave while the page shows unsaved changes.

#### E. Monitoring and Evaluation

1. Use Monitoring after Assessment, Diagnosis, and Intervention exist and the patient returns for follow-up.
2. In **Visit Log**, record date, clinical indicators, intake/tolerance, symptoms, goal status, and next monitoring date.
3. In **Progress Trends**, compare current results with baseline and prescription targets.
4. Save the monitoring entry; revise the care plan when findings require it.

### 3. Maintain Clinical Food Data

1. Open **Food Library**.
2. In **Foods**, add manually or import from USDA; verify category, serving, nutrients, and allergens.
3. In **Recipes**, combine clinical foods, set servings/category, and verify calculated nutrient totals.
4. Keep this library distinct from food-service purchasing data.

### 4. Plan and Supervise Food Service

#### A. Inventory Reference Catalog

1. Open **Food Service → Inventory**.
2. Maintain ingredients and supplies with category, vendor, base unit, and purchase cost. Mark bulk pantry ingredients **Purchase when needed** when they should remain in recipes but not auto-generate.
3. Treat it as a reference catalog, not live stock quantity.

#### B. Foods and Recipes

1. Open **Food Service → Foods**.
2. Create a food-service recipe or single-ingredient item.
3. Set category, servings, ingredients, quantities, costs, and preparation notes.
4. Confirm recipe/item profiles before menu use.

#### C. Menu Cycle

1. Open **Food Service → Menu Cycle**.
2. Create a cycle or instantiate a template.
3. Set the week and add recipes/items to meal slots. Leave the name blank to use the date-span name.
4. Review baseline ingredients and preparation notes; the purchase estimate is set later for the whole shopping span.
5. Save; optionally save as template.
6. Activate the approved cycle so FSS can execute it.

#### D. Procurement

1. Open **Food Service → Procurement**.
2. For suggested food, select the date span, enter one estimated serving count, and generate. For an event/manual food list, name it and add ingredients directly.
3. Fix every missing menu date shown by validation; regenerate.
4. Keep calculated need visible while reviewing purchase quantity/unit/price, vendor, manual additions, and exclusions.
5. Create a named manual Supplies List when needed; a related event can use the same purpose name.
6. Clear the release checklist, then create and release one vendor-grouped PO.
7. During execution, monitor actual values, receipt/proof, optional OR, explicit vendor received status, served-day progress, totals, and history.
8. Correct authorized open-execution values when needed; completed/archived POs are historical records.

#### E. Budget

1. Open **Food Service → Budget**.
2. Select/setup the fiscal year and allocated amount.
3. Review summary and ledger.
4. Enter a manual adjustment only with a clear operational reason.
5. Configure the shared per-head/day limit in **Settings → Food Service Budget**.

### 5. Announcements and SOP

1. Open **Announcements**.
2. Review the current SOP and History before publishing procedure-sensitive work.
3. Add an announcement with category, audience, title/body, pin state, and optional images.
4. Edit/delete only authorized posts.
5. Use **Revise SOP** for a true procedure revision; saving creates a new history version.

### 6. Reports

1. Open **Reports → Browse**.
2. Choose Food Service or Clinical report type.
3. Select the record/period and open live preview.
4. Validate data and branding.
5. Download live output when appropriate, or **Archive** to freeze the formally filed copy.
6. Use **Archived** to view/download frozen copies and activity history.
7. Use **Template Edit** to maintain report branding/signatories when authorized.

### 7. Help, Notifications, Settings, and Profile

1. Open **Help** in the sidebar to search Shared and RND-only answers. Expand a question to read its answer; clear the search to browse by topic.
2. Open Notifications for announcements and follow-up reminders; mark items/all as read.
3. Use Settings for local density/motion, notification preferences, and food-service budget limit.
4. Use Profile for identity, sign-in email, contact, photo, recovery email, and password.

## FSS Mobile Workflow

### 1. Home

1. Open **Home** after signing in.
2. Check **Meals to log today** and **Pending POs**.
3. Open the active menu-cycle card.
4. Review each pending PO's **Needs receipts** or **Needs served population** status.
5. Review today's service and announcements.

### 2. Menu

1. Open **Menu**.
2. Open the active/current cycle or browse another cycle.
3. Select a meal slot to view scaled recipe/item details, ingredients, cost, and preparation notes.
4. Do not attempt to change planned foods; FSS menu access is read-only.

### 3. Meal Prep

1. Open **Meal Prep**.
2. Review today's planned service and food profiles.
3. Enter actual total patient population when prompted.
4. Use the date selector to enter or correct a previous service date when needed.

### 4. Accomplish

1. Open **Accomplish**.
2. Enter ward and meals distributed.
3. Select every duty completed.
4. If absent/off duty, enable that switch; it records an X and zero meals.
5. Choose **Save accomplishment**.
6. Repeat for another ward only when another distinct entry is required.
7. Open **My reports** to view personal archived weekly reports.

### 5. Purchase

1. Open **Purchase** and select a PO.
2. Open the correct vendor group.
3. If the actual vendor changed before evidence was uploaded, use **Change vendor for all** for the group or **Change vendor** on one item row.
4. Review the visible planned purchase. Expand **Calculation details** only when you need the calculated/planned/actual comparison.
5. Confirm or correct the prefilled actual decimal quantity and unit price, then save.
6. Enter an OR number only when the vendor provided one.
7. Upload at least one receipt and one proof-of-purchase image for the vendor.
8. Use **Mark vendor received**; uploads alone do not change the status.
9. Completed/archived POs lock edits.

### 6. Announcements, SOP, and Notifications

1. Open **Announcement** in the bottom navigation, then switch between Announcements and SOP.
2. Use the bell for notifications; opening a notification may navigate to its target.
3. Use Settings to mark all read if needed.

### 7. Help, Settings, and Profile

1. Use the header account icon to open Settings.
2. Under **Help & Support**, open **Help** to search Shared and FSS-only answers. Help remains outside the six main tabs.
3. Set comfortable/compact density and reduced motion.
4. Open Profile to edit name, sign-in email, contact number, recovery email, and password.
5. Sign out from Settings when the device is shared.

## Admin Web Workflow

### 1. Review System Dashboard

1. Open **Admin Dashboard**.
2. Review user totals, aggregate patients in care, AI usage/cost, and audit-event volume.
3. Review daily/monthly AI token usage and configured caps.
4. Use AI Usage Explorer and Recent Activity to identify spikes or failures.
5. Use quick actions for accounts, audit logs, and announcements.

### 2. Manage Accounts

1. Open **Manage Users**.
2. Search or filter by role.
3. To create an account, enter name, sign-in email, role, active state, and temporary password.
4. Tell the user to use the correct platform and complete first-login setup.
5. To edit, update identity/role/status; role/status/password changes end existing sessions.
6. Use Reset Password only after verifying the requester's identity; transfer the new password securely.
7. Suspend instead of delete when temporary access removal is enough.
8. Do not self-deactivate or self-delete.

### 3. Audit Oversight

1. Open **Audit Logs**.
2. Select a module and apply action, actor, date, or other filters.
3. Open an event for structured safe details; use History for the correlated record timeline.
4. Export only when operationally required and store exports securely.
5. Update retention only under approved policy.
6. Treat missing clinical values as intentional privacy protection, not incomplete logging.

### 4. Announcements and SOP

1. Open **Announcements**.
2. Publish system/department posts with audience, category, pin state, text, and images.
3. Edit/delete through the Admin board.
4. Revise SOP only for approved procedural change; confirm the new version appears in History.

### 5. Reports and Budget Oversight

1. Open **Reports** for Program Project Activity, Menu Calendar, Procurement Pack, Accomplishment Report, or aggregate Demographic Census.
2. Preview live data; archive only approved as-filed copies.
3. Do not seek Patient Menu Plan or NCP Summary; server access is blocked for Admin.
4. Open **Budget** to inspect fiscal-year summary, ledger, and history in read-only mode.

### 6. System Settings

1. Open **Settings**.
2. Maintain hospital name, service name, address, accreditation, province, LGU, and report logos.
3. Set food-service budget per head/day.
4. Set local display/notification preferences.
5. Return to Dashboard to confirm metrics after operational changes.

### 7. Help, Profile, and Notifications

1. Open **Help** in the sidebar to search Shared and Admin-only answers; no RND clinical guidance is exposed.
2. Use Profile for personal identity, sign-in/recovery email, contact, photo, and password.
3. Use Notifications for Admin/All announcements and system alerts.

## Cross-Role Operational Closeout

1. RND activates a menu cycle and converts shopping lists to food/supplies POs.
2. FSS reviews the menu, receives vendor groups by receipt upload, records actual served population, marks meal service complete, and logs daily accomplishment.
3. System completes eligible POs, calculates actual food cost per head/day, and updates budget/audit records.
4. RND reviews outcomes and operational reports.
5. Admin reviews aggregate health, audit events, budget history, and allowed reports without entering patient clinical workflows.
