# NutriScope Sequential Screenshot Storyboard Guide

Verified against current role navigation on **2026-07-20**.

This is the screenshot-required storyboard. Complete it by inserting the specified app capture immediately below each **Screenshot needed** instruction. Use demo data and follow the privacy rules in this guide.

For a complete fallback that needs no images, use the [System Storyboard](../STORYBOARD.md).

Use this version when screenshots are available. Each scene already contains the description, expected user action, and exact capture request. Insert the image immediately below its **Screenshot needed** block.

### Screenshot Rules

1. Use seeded/demo data only; do not expose a real patient's identity or clinical data.
2. Keep role, page heading, primary navigation, and main action visible.
3. Hide browser bookmarks, unrelated tabs, device notifications, tokens, recovery codes, email links, and passwords.
4. Use one consistent browser size for RND/Admin and one consistent phone size for FSS.
5. Capture success/ready states unless an exception scene explicitly requests an error.
6. Suggested filenames use `ROLE-PROCESS-NN-short-name.png`.

### Shared Account Process

#### SHARED-01 — Sign In

**Description:** Entry page separates role workspaces by platform. RND/Admin sign in on web; FSS signs in through the mobile app. User enters sign-in email and password, then the server validates account status, role, and platform.

**User should do:** Enter demo credentials and select **Sign In**.

**Next scene:** First-login Account Setup or the role dashboard.

> **Screenshot needed:** Web Login page with NutriScope branding, Email Address, Password, Forgot password, and Sign In visible. Do not show a real password.
>
> **Insert screenshot below this line.**

#### SHARED-02 — First-Login Account Setup

**Description:** Admin-created accounts must replace the temporary password and add a recovery email. The user may finish now or defer, but the reminder remains until both requirements are complete.

**User should do:** Enter a new password, confirm it, enter a demo recovery email, then select **Save account setup**.

**Next scene:** Role dashboard.

> **Screenshot needed:** Account Setup page showing First login, New password, Confirm new password, Recovery email, Save account setup, and Do later. Leave sensitive fields empty.
>
> **Insert screenshot below this line.**

#### SHARED-03 — Forgot Password

**Description:** User requests recovery through the verified recovery email. The system returns a generic confirmation to avoid exposing whether an account exists.

**User should do:** Enter the demo verified recovery email and select **Send Reset Link**.

**Next scene:** Reset Password page opened from the email link, or Admin-assisted reset if no verified recovery address exists.

> **Screenshot needed:** Forgot Password page with verified recovery-email field, Send Reset Link, and Back to sign in.
>
> **Insert screenshot below this line.**

#### SHARED-04 — Profile and Recovery

**Description:** Profile controls personal identity, sign-in email, contact, profile image on web, recovery email, and password. Role/designation is read-only.

**User should do:** Confirm name and contact, then demonstrate the Recovery Email and Change Password sections without submitting secrets.

**Next scene:** Return to role work.

> **Screenshot needed:** Profile page showing Account Information and the Recovery Email heading. Use demo identity; do not show recovery code or password values.
>
> **Insert screenshot below this line.**

### RND Clinical Process

#### RND-NCP-01 — Dashboard Work Queue

**Description:** RND begins from current clinical and operational priorities: patients in care, scheduled follow-ups, pending POs, and announcements.

**User should do:** Identify the patient requiring action and choose **Open NCP** or **Open Patients**.

**Next scene:** Patient Directory.

> **Screenshot needed:** RND Dashboard with Patients in Care, Pending POs, Patient Snapshot/follow-up list, and Announcements visible.
>
> **Insert screenshot below this line.**

#### RND-NCP-02 — Patient Directory

**Description:** Directory is the entry point for searching, filtering, creating, and opening patient records. Creating a patient can immediately start Assessment.

**User should do:** Search for a demo patient and open the row, or select **Create Patient & Start Assessment**.

**Next scene:** Patient Profile or Assessment.

> **Screenshot needed:** Nutrition Care Patients page with search/filter, patient table, and Create Patient & Start Assessment action. Use demo patient names.
>
> **Insert screenshot below this line.**

#### RND-NCP-03 — Patient Profile

**Description:** Patient Profile is the root context for demographics, current NCP snapshot, cycles, attachments, and structured activity.

**User should do:** Review Overview, then select **ADIME Records** to continue/start a cycle.

**Next scene:** ADIME Records.

> **Screenshot needed:** Demo Patient Profile Overview with patient header, status/risk, current-cycle snapshot, and Overview/ADIME Records/Attachments tabs.
>
> **Insert screenshot below this line.**

#### RND-NCP-04 — ADIME Records

**Description:** Every NCP cycle keeps its own Assessment, Diagnosis, Intervention, Monitoring, meal plans, attachments, and activity. Protected completed care-plan cycles cannot be normally deleted.

**User should do:** Open a demo cycle's Assessment step or select **Start New Cycle**.

**Next scene:** Assessment.

> **Screenshot needed:** ADIME Records tab showing one cycle card, step navigation, summaries, and Start New Cycle.
>
> **Insert screenshot below this line.**

#### RND-NCP-05 — Assessment: Dietary and Anthropometrics

**Description:** RND records intake and body measurements. The page calculates clinical helpers; edema requires dry weight for safe prescription calculations.

**User should do:** Complete demo dietary data, weight, height, and required calculation fields.

**Next scene:** Remaining Assessment tabs.

> **Screenshot needed:** Assessment page with patient header, A: Dietary/B: Anthropometrics tabs, calculated summary strip, and representative fields. Do not show real clinical data.
>
> **Insert screenshot below this line.**

#### RND-NCP-06 — Assessment: Labs, Referral, and Summary

**Description:** RND records biochemical/referral data, optionally stores supporting files, reviews automatic/manual risk scoring, and generates an editable summary. Uploads do not currently OCR/autofill.

**User should do:** Review entered values, generate the draft summary, edit as needed, and choose **Save Assessment**.

**Next scene:** Diagnosis.

> **Screenshot needed:** Assessment Summary tab showing Generate/Regenerate Summary, RND Summary field, nutritional-risk section, and Save Assessment. Optional second image: Labs or Referral upload panel labeled as supporting document.
>
> **Insert screenshot(s) below this line.**

#### RND-NCP-07 — Diagnosis Table

**Description:** Saved diagnoses are listed by domain with PES statements and AI attribution when relevant.

**User should do:** Choose **Add New Diagnosis**.

**Next scene:** P/E/S builder.

> **Screenshot needed:** Diagnosis Table with domain filter, saved demo diagnosis, PES Statement column, and Add New Diagnosis.
>
> **Insert screenshot below this line.**

#### RND-NCP-08 — P/E/S Builder and PES Statement

**Description:** RND selects diagnostic domain, Problem, Etiology, and Signs/Symptoms. NutriScope builds an editable PES statement; RND validates it before saving.

**User should do:** Complete P/E/S, review the final sentence, and select **Save Diagnosis**.

**Next scene:** Intervention.

> **Screenshot needed:** PES Statement tab with the completed editable statement and Save Diagnosis. Optional second image: Problem/Etiology/Signs selection tabs.
>
> **Insert screenshot(s) below this line.**

#### RND-NCP-09 — AI Diagnosis Review

**Description:** AI generates draft suggestions from the current patient/Assessment context. RND must accept, edit, or dismiss each suggestion; AI never saves a diagnosis independently.

**User should do:** Demonstrate **Edit** on a safe demo suggestion or explain Accept/Dismiss.

**Next scene:** Saved Diagnosis or Intervention.

> **Screenshot needed:** AI Review tab with a demo suggestion and Accept, Edit, Dismiss controls. Ensure no real patient context or prompt is visible.
>
> **Insert screenshot below this line.**

#### RND-NCP-10 — Intervention Goal and Prescription

**Description:** RND selects the intervention goal/stage. Laravel returns authoritative energy, macro, fluid, and micronutrient targets; the page discloses the calculation trace for review.

**User should do:** Set a demo goal, expand the calculation explanation, review values, and save prescription.

**Next scene:** Patient Meal Plan and supporting Intervention tabs.

> **Screenshot needed:** Intervention Food/Nutrient Delivery tab showing Intervention Goal, prescription values, Save action, and expanded calculation trace. Use non-sensitive demo values.
>
> **Insert screenshot below this line.**

#### RND-NCP-11 — Patient Meal Plan

**Description:** RND creates a manual/generated/template-based meal plan, then checks allergens, restrictions, portions, nutrition totals, and variance against prescription targets.

**User should do:** Open the demo plan, add/adjust an item, review target variance, and save.

**Next scene:** Education/Counseling/Goal Planning/Encounter Context.

> **Screenshot needed:** Meal Plan area with one demo day/meal, foods, nutrition totals or variance, and save/generate/template controls.
>
> **Insert screenshot below this line.**

#### RND-NCP-12 — Intervention Supporting Tabs

**Description:** Education, Counseling, Goal Planning, and Encounter Context turn prescription targets into patient-facing actions and a planned follow-up.

**User should do:** Show completed demo content and the save action for one tab; identify the next follow-up field in Encounter Context.

**Next scene:** Monitoring on follow-up.

> **Screenshot needed:** One representative supporting tab plus Encounter Context showing follow-up planning. Use demo text only.
>
> **Insert screenshot(s) below this line.**

#### RND-NCP-13 — Monitoring Visit Log

**Description:** Once Assessment, Diagnosis, and Intervention exist, RND records follow-up indicators, tolerance/intake, symptoms, goal result, and next date.

**User should do:** Add a demo follow-up visit and save.

**Next scene:** Progress Trends.

> **Screenshot needed:** Monitoring Visit Log with entry form/history and the page header stating follow-up monitoring.
>
> **Insert screenshot below this line.**

#### RND-NCP-14 — Monitoring Progress Trends

**Description:** Progress view compares follow-up data with baseline Assessment and saved prescription targets.

**User should do:** Explain whether the care plan continues, changes, or closes based on demo trend.

**Next scene:** Clinical Reports.

> **Screenshot needed:** Progress Trends tab with summary, goal progress, and trend visualization/cards.
>
> **Insert screenshot below this line.**

#### RND-NCP-15 — Clinical Report Preview and Archive

**Description:** Live NCP Summary/Patient Menu Plan reflects current data. Archive freezes the formally filed version.

**User should do:** Open a demo live preview, validate it, then show Archive or an existing archived copy.

**Next scene:** End of clinical story.

> **Screenshot needed:** Reports Browse with NCP Summary or Patient Menu Plan selected and live preview open. Optional second image: Archived tab with a frozen demo report.
>
> **Insert screenshot(s) below this line.**

### RND Food Library Process

#### RND-LIB-01 — Clinical Foods and USDA Import

**Description:** Food Library stores clinical food references with servings, macro/micronutrients, and allergens. RND may add foods manually or import USDA data and then review mapped values.

**User should do:** Search a demo food, open its nutrient profile, then show **Import from USDA**.

**Next scene:** Clinical Recipes or the NCP patient meal plan.

> **Screenshot needed:** Food Library Foods tab with search/category filters, nutrient columns, Add Food, and Import from USDA. Optional second image: USDA search results or nutrient profile.
>
> **Insert screenshot(s) below this line.**

#### RND-LIB-02 — Clinical Recipes

**Description:** RND combines clinical foods into reusable recipes and reviews calculated nutrient totals and allergens for patient meal planning.

**User should do:** Open a demo recipe or select **Create Recipe**.

**Next scene:** Patient Meal Plan or return to Food Library.

> **Screenshot needed:** Food Library Recipes tab with demo recipe, servings, nutrient totals, and Create Recipe.
>
> **Insert screenshot below this line.**

### RND Food-Service Process

#### RND-FS-01 — Inventory Reference Catalog

**Description:** RND maintains ingredient/supply names, category, vendor, unit, and purchase cost. It is reference data, not quantity-on-hand stock control.

**User should do:** Search and open/create one demo ingredient.

**Next scene:** Foods.

> **Screenshot needed:** Inventory — Reference Catalog with Ingredients/Supplies tabs, search, cost/unit columns, and New Ingredient/Supply action.
>
> **Insert screenshot below this line.**

#### RND-FS-02 — Foods and Recipes

**Description:** RND creates food-service recipes or single-ingredient foods used by Menu Cycle, with serving, ingredient, cost, and prep context.

**User should do:** Open a demo recipe or select New Recipe.

**Next scene:** Menu Cycle.

> **Screenshot needed:** Food Service Foods list with recipe names, category, servings, total cost, and New Recipe/Add Single Ingredient actions.
>
> **Insert screenshot below this line.**

#### RND-FS-03 — Menu Cycle List and Templates

**Description:** RND reviews current/upcoming/past cycles and may create a new cycle or instantiate a reusable template.

**User should do:** Open the active demo cycle.

**Next scene:** Menu Cycle Editor.

> **Screenshot needed:** Menu Cycles list showing cycle/week/status, New Cycle, and Templates section if available.
>
> **Insert screenshot below this line.**

#### RND-FS-04 — Menu Cycle Editor

**Description:** RND assigns recipes/items to a date-named Monday-Sunday cycle, reviews baseline profiles, saves a reusable template if needed, and activates. Purchase estimates are set later per shopping span.

**User should do:** Open one filled slot, review its profile, then show Save and Activate.

**Next scene:** Food Shopping List.

> **Screenshot needed:** Filled Menu Cycle grid with week dates, estimated/served population, meal slots, Save, Save as Template, and Activate. Optional second image: open food-profile modal.
>
> **Insert screenshot(s) below this line.**

#### RND-FS-05 — Generate Food Shopping List

**Description:** RND selects a date span. NutriScope requires every date to have menu coverage, assigned foods, and needed population before generating the complete list.

**User should do:** Enter a complete demo span and select Generate.

**Next scene:** Shopping List Detail.

> **Screenshot needed:** Procurement Food Shopping Lists tab with Suggest from Menu panel, From/To dates, and Generate. Optional exception image: exact missing-date validation.
>
> **Insert screenshot(s) below this line.**

#### RND-FS-06 — Shopping List Review and Conversion

**Description:** RND reviews the one span estimate, calculated need, editable purchase values/vendor, manual additions, exclusions, included total, and release blockers before creating the vendor-grouped PO.

**User should do:** Review the demo list, clear its checklist, and point to **Create and release PO**.

**Next scene:** Purchase Order Detail.

> **Screenshot needed:** Shopping-list detail with span estimate, calculated and purchase columns, inclusion controls, vendors, release checklist, and Create and release PO.
>
> **Insert screenshot below this line.**

#### RND-FS-07 — Purchase Order Supervision

**Description:** RND follows one PO's purpose/name, vendor actual values, receipt/proof, optional OR, received status, served-day progress, lifecycle, and structured activity.

**User should do:** Open one vendor group and explain missing receipt/population requirements.

**Next scene:** FSS receiving or RND operational Reports.

> **Screenshot needed:** PO detail showing PO number, lifecycle, served days, total served population, vendor groups, receipt status, actual/estimated cost, and activity section.
>
> **Insert screenshot below this line.**

#### RND-FS-08 — Budget

**Description:** RND manages fiscal-year allocation and manual adjustments, while completed procurement contributes system ledger entries. Per-head/day limit lives in Settings.

**User should do:** Select a demo fiscal year and explain summary versus ledger.

**Next scene:** Operational Reports.

> **Screenshot needed:** Food Service Budget with fiscal-year selector, summary cards, ledger, and adjustment controls.
>
> **Insert screenshot below this line.**

#### RND-FS-09 — Operational Reports

**Description:** RND previews and archives Program Project Activity, Menu Calendar, Procurement Pack, Accomplishment, and Demographic Census outputs.

**User should do:** Open one demo report and explain live versus archived state.

**Next scene:** End of food-service planning story.

> **Screenshot needed:** Reports Browse with a Food Service report selected and preview/action controls visible.
>
> **Insert screenshot below this line.**

### RND Communication Process

#### RND-COM-01 — Announcements and SOP

**Description:** Current SOP stays pinned above the announcement feed. RND may publish role-targeted announcements and revise SOP; every SOP save preserves history.

**User should do:** Open History, then show Add Announcement or Revise SOP.

**Next scene:** Notifications.

> **Screenshot needed:** Announcements page with current SOP banner, History/Revise controls, pinned announcement, audience/category, and Add Announcement.
>
> **Insert screenshot below this line.**

#### RND-COM-02 — Notifications

**Description:** RND notifications combine announcements and upcoming follow-up reminders. Opening an item marks it opened and routes to supported context.

**User should do:** Open one demo follow-up reminder or mark all read.

**Next scene:** Patient follow-up or Settings.

> **Screenshot needed:** RND Notifications page with unread/read states, follow-up or announcement item, pagination if present, and Mark all read.
>
> **Insert screenshot below this line.**

#### RND-COM-03 — Settings and Profile

**Description:** Settings controls local display/notification preferences and the shared food-service budget limit. Profile controls RND identity, recovery, and password.

**User should do:** Show the Food Service Budget setting, then Profile identity/recovery sections without editing approved values.

**Next scene:** End of RND supporting story.

> **Screenshot needed:** RND Settings with Food Service Budget and Notifications; optional second image: RND Profile Account Information/Recovery Email.
>
> **Insert screenshot(s) below this line.**

#### RND-COM-04 — Help

**Description:** RND Help provides searchable Shared and RND-only answers for account access, the Nutrition Care Process, food service, announcements/SOP, and reports. It has no role switch.

**User should do:** Open Help from the sidebar, search for `dry weight`, then expand the matching question.

**Next scene:** End of RND supporting story.

> **Screenshot needed:** RND Help showing Search Help, the `dry weight` result expanded, and the sidebar Help item selected. Do not show Admin guidance.
>
> **Insert screenshot(s) below this line.**

### FSS Mobile Process

#### FSS-01 — Home

**Description:** FSS begins with meals to log, pending POs, active menu cycle, today's service, and announcements.

**User should do:** Review waiting reasons, then open the active menu or pending PO.

**Next scene:** Menu or Purchase.

> **Screenshot needed:** FSS Home showing five-tab bar, Meals to log today, Pending POs, Active Menu Cycle, and today's service.
>
> **Insert screenshot below this line.**

#### FSS-02 — Menu Cycle

**Description:** Menu is read-only planning content from RND. FSS can open a slot profile and record/backfill actual served population.

**User should do:** Open today's meal slot and review preparation details.

**Next scene:** Menu Item Profile.

> **Screenshot needed:** Menu tab with current cycle, weekdays/meal slots, and served population control.
>
> **Insert screenshot below this line.**

#### FSS-03 — Menu Item Profile

**Description:** Profile gives kitchen-ready recipe/item information: scaled servings, ingredients, quantities, cost/head, and prep notes.

**User should do:** Read the demo preparation notes and close the profile.

**Next scene:** Purchase or Meal Prep.

> **Screenshot needed:** Open recipe/item profile modal with scaled ingredients and preparation notes.
>
> **Insert screenshot below this line.**

#### FSS-04 — Purchase Order List

**Description:** Purchase shows existing food/supplies POs and their execution status. FSS does not author a PO.

**User should do:** Open an open-execution demo PO and select a vendor group.

**Next scene:** Vendor Group Receiving.

> **Screenshot needed:** Purchase tab showing PO list/status and one open demo PO.
>
> **Insert screenshot below this line.**

#### FSS-05 — Vendor Group Receiving

**Description:** FSS reviews calculated values, corrects actual decimal quantity/price, uploads receipt and proof, optionally saves an OR number, then explicitly marks the vendor received.

**User should do:** Review actuals, upload receipt and proof, leave OR blank for a vendor without one, then point to **Mark vendor received**.

**Next scene:** Image Upload.

> **Screenshot needed:** Vendor detail with supplier, status, calculated and actual values, optional OR, Receipt images, Proof of purchase, and Mark vendor received.
>
> **Insert screenshot below this line.**

#### FSS-06 — Receipt/Proof Upload

**Description:** FSS chooses camera or library, optionally adds a caption, and submits operational evidence. Device permission is required.

**User should do:** Choose a demo receipt image; do not upload real financial data for documentation.

**Next scene:** Meal Prep.

> **Screenshot needed:** Upload modal showing Receipt/Proof selector, caption, Library, and Camera. No real receipt details.
>
> **Insert screenshot below this line.**

#### FSS-07 — Meal Prep

**Description:** FSS reviews today's service, enters actual total population, and marks meals served/complete. A shortfall requires explicit confirmation.

**User should do:** Enter demo population and show the completion control.

**Next scene:** Accomplish.

> **Screenshot needed:** Meal Prep tab showing Today's Service, actual total population, meal rows, and served/completion action.
>
> **Insert screenshot below this line.**

#### FSS-08 — Daily Accomplishment

**Description:** FSS records ward, meals distributed, seven duties, or off-duty/absent. This daily data builds the user's weekly report.

**User should do:** Fill a demo entry and select Save accomplishment.

**Next scene:** My Reports.

> **Screenshot needed:** Accomplish tab with daily log summary, ward, meals, duty checklist, off-duty switch, and Save accomplishment.
>
> **Insert screenshot below this line.**

#### FSS-09 — My Accomplishment Reports

**Description:** FSS sees only their own frozen weekly accomplishment reports. A week archives after Monday-Sunday entries exist; off-duty days render X.

**User should do:** Open one demo archived report.

**Next scene:** Communication/account or end of FSS story.

> **Screenshot needed:** My reports list and one opened weekly report grid using demo data.
>
> **Insert screenshot(s) below this line.**

#### FSS-10 — Announcements and SOP

**Description:** Header megaphone opens current SOP, SOP History, and FSS-visible announcements. FSS is read-only.

**User should do:** Open the current SOP and one announcement.

**Next scene:** Notifications or daily work.

> **Screenshot needed:** Mobile Announcements page with SOP card/history and announcement list.
>
> **Insert screenshot below this line.**

#### FSS-10B — Notifications

**Description:** Notification badge and list show FSS alerts. Opening or marking read updates the badge; supported items navigate to announcements or procurement.

**User should do:** Open one demo notification, then return to the tab workflow.

**Next scene:** Target screen or Settings.

> **Screenshot needed:** FSS Notifications screen with unread badge/state and one demo notification. Hide device-level notifications.
>
> **Insert screenshot below this line.**

#### FSS-11 — Settings and Profile

**Description:** Settings controls density, reduced motion, read-all, Profile, and sign out. Profile controls identity, recovery email, and password while role/status remain read-only.

**User should do:** Show Settings, then Profile account information.

**Next scene:** End of FSS story.

> **Screenshot needed:** Settings screen and Profile Account Info/Recovery Email sections. Do not show secrets.
>
> **Insert screenshot(s) below this line.**

#### FSS-12 — Help

**Description:** FSS opens Help from Settings under Help & Support. The page searches Shared and FSS-only answers and remains outside the five main tabs.

**User should do:** Open Settings, select Help, search for `purchase order`, and expand one result.

**Next scene:** End of FSS story.

> **Screenshot needed:** FSS Help showing Search Help and an expanded purchase-order answer; optional second image: Settings with Help & Support → Help. Keep all five bottom tabs unchanged.
>
> **Insert screenshot(s) below this line.**

### Admin Process

#### ADMIN-01 — Dashboard and AI Oversight

**Description:** Admin starts from system health: user totals, aggregate patient count, AI usage/cost, audit volume, token caps, explorer, and recent events.

**User should do:** Review current usage and explain daily/monthly caps.

**Next scene:** Manage Users or Audit Logs.

> **Screenshot needed:** Admin Dashboard with KPI cards, AI Token Caps, AI Usage Explorer, Quick Actions, and Recent Activity.
>
> **Insert screenshot below this line.**

#### ADMIN-02 — Manage Users

**Description:** Admin searches accounts, sees role/status, and opens create/edit/reset/suspend/delete actions. Self-deactivation/deletion is blocked by the UI.

**User should do:** Filter by role and open Create Account.

**Next scene:** Create Account.

> **Screenshot needed:** Manage Users directory with search, role filter, status, row actions, and Create Account.
>
> **Insert screenshot below this line.**

#### ADMIN-03 — Create Account and Onboarding Handoff

**Description:** Admin assigns identity, sign-in email, role, status, and temporary password. The user finishes password/recovery setup on first login.

**User should do:** Demonstrate fields with fake data; do not submit a documentation-only account unless approved.

**Next scene:** User Account Setup or Admin Dashboard.

> **Screenshot needed:** Create Account modal with First/Last Name, Email, Role, Status, Password, Confirm Password, and Create Account. Use obvious demo values.
>
> **Insert screenshot below this line.**

#### ADMIN-04 — Password Reset or Suspension

**Description:** After identity verification, Admin may reset a password or suspend an account. Role/status/password changes revoke sessions and are audited.

**User should do:** Open Reset Password or point to Suspend; do not reveal/set a real password in the screenshot.

**Next scene:** Audit Logs.

> **Screenshot needed:** Reset Password modal with empty fields, or account row showing Active/Suspended action.
>
> **Insert screenshot below this line.**

#### ADMIN-05 — Audit Log Browser

**Description:** Admin filters structured system events by module/action/actor/date and opens safe details. Raw payloads and clinical old/new values are not exposed.

**User should do:** Apply one demo filter and open an event.

**Next scene:** Audit Event/History.

> **Screenshot needed:** Audit Logs with module rail, filters, retention control, paginated event table, and export if visible.
>
> **Insert screenshot below this line.**

#### ADMIN-06 — Audit Event and Historical Record

**Description:** Event detail explains actor, action, subject, timestamp, and allow-listed changes; correlated History shows the safe timeline.

**User should do:** Open a non-sensitive demo account or procurement event and then History.

**Next scene:** Announcements or Reports.

> **Screenshot needed:** Structured Audit Event detail/drawer and Historical audit record page. Avoid patient-sensitive examples.
>
> **Insert screenshot(s) below this line.**

#### ADMIN-07 — Announcements and SOP

**Description:** Admin publishes audience-targeted system posts and revises approved SOP versions.

**User should do:** Show Add Announcement and SOP History/Revise controls.

**Next scene:** Reports.

> **Screenshot needed:** Admin Announcements page with SOP banner and one targeted/pinned demo post.
>
> **Insert screenshot below this line.**

#### ADMIN-08 — Allowed Reports

**Description:** Admin can use Program Project Activity, Menu Calendar, Procurement Pack, Accomplishment Report, and aggregate Demographic Census. Patient Menu Plan and NCP Summary are absent/blocked.

**User should do:** Open one allowed live preview and explain the privacy boundary.

**Next scene:** Budget.

> **Screenshot needed:** Admin Reports catalog showing allowed types and one live preview/Archive action. Frame the image so patient-specific report types are clearly absent.
>
> **Insert screenshot below this line.**

#### ADMIN-09 — Read-Only Budget

**Description:** Admin reviews fiscal-year budget summary, ledger, and activity but cannot create allocations or manual adjustments.

**User should do:** Select a demo fiscal year and explain the read-only indicators.

**Next scene:** Settings.

> **Screenshot needed:** Admin Budget with fiscal-year selector, summary and ledger; ensure mutation controls are absent/read-only.
>
> **Insert screenshot below this line.**

#### ADMIN-10 — System Settings and Branding

**Description:** Admin maintains hospital/report branding, logos, food-service budget-per-head/day, and local display/notification preferences.

**User should do:** Show branding fields and the shared budget setting without overwriting current approved values.

**Next scene:** End of Admin story.

> **Screenshot needed:** Admin Settings with Hospital Branding and Food Service Budget sections. Optional second image: display/notification preferences.
>
> **Insert screenshot(s) below this line.**

#### ADMIN-11 — Notifications and Profile

**Description:** Admin receives Admin/All announcements and system alerts. Profile controls the Admin's own identity, recovery email, photo, and password; role remains read-only.

**User should do:** Show one safe notification and the Profile identity/recovery sections.

**Next scene:** End of Admin story.

> **Screenshot needed:** Admin Notifications plus Admin Profile Account Information/Recovery Email. Do not show codes, passwords, or personal addresses.
>
> **Insert screenshot(s) below this line.**

#### ADMIN-12 — Help

**Description:** Admin Help provides searchable Shared and Admin-only answers for accounts, AI limits, audit logs, announcements/SOP, reports, budget, and settings. It does not expose RND clinical guidance.

**User should do:** Open Help from the sidebar, search for `token caps`, then expand the matching question.

**Next scene:** End of Admin story.

> **Screenshot needed:** Admin Help showing Search Help, the `token caps` result expanded, and the sidebar Help item selected. Do not show RND guidance.
>
> **Insert screenshot(s) below this line.**

### Version 2 Cover/Closing Screenshots

For the final storyboard document, add:

1. **Cover image:** Login page or a three-role composite labeled RND Web, FSS Mobile, Admin Web.
2. **Cross-role closing image:** RND PO detail beside FSS receiving and Admin aggregate oversight.
3. **Final caption:** “RND plans and provides clinical care; FSS executes daily food service; Admin secures and oversees the system.”

## Storyboard Maintenance Rule

When navigation or permissions change, update this file together with [RND](rnd.md), [FSS](fss.md), [Admin](admin.md), and the matching file under [module Flowcharts](Flowcharts/). Confirm both visible navigation and Laravel role middleware before documenting a capability.
