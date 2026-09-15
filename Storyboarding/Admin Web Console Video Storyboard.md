# Admin Web Console Video Storyboard

Verified against the current Admin website, Laravel role guards, and backup/recovery workflow on **2026-09-15**.

Use this as both the recording checklist and narration script. Record with demo accounts and non-sensitive sample data only. Never display passwords, recovery links, access tokens, `.env` values, backup archives, storage keys, or patient clinical records.

## How to Use This Script

1. Complete **Recording Setup** before filming.
2. Record the numbered scenes in order. A scene that needs an earlier result names it under **Before this scene**.
3. Follow **On-screen actions**, then use **Narration** as the spoken explanation.
4. Confirm the **Expected result** before continuing.
5. Future **Help → Guides** pages should use shortened actions and expected results. Recording notes, narration, and cleanup stay in this file.

**Overall sequence:** Sign in securely → review system health → communicate with users → manage access → review reports and budget → investigate audit history → supervise backups → maintain settings and account tools → sign out.

## Words Used in This Script

- **Admin:** Website role responsible for accounts, system configuration, operational oversight, audit review, and backup initiation.
- **RND:** Registered Nutritionist-Dietitian role responsible for clinical nutrition care and food-service planning.
- **FSS:** Food Service Staff role responsible for mobile food-service execution.
- **Restore point:** Verified whole-system backup listed on **Backup & Recovery**.
- **Recently deleted:** Recoverable backup archive awaiting permanent deletion after 48 hours.
- **Pre-restore backup:** Protected whole-system safety snapshot created before each restoration attempt, retained for rollback and for 48 hours after the attempt finishes.

## Recording Setup

- Use a demo Admin account. Complete temporary-password and recovery-email setup before recording unless Scene 1 will demonstrate it.
- Create two disposable demo users: one active RND and one active FSS. Do not alter real accounts.
- Prepare one privacy-safe announcement image and two small report-logo images.
- Use aggregate or synthetic data only. Do not open patient-specific reports or raw clinical audit values.
- For Scene 8, use a non-production environment. Backup storage, encryption, queue worker, scheduler, MySQL tools, and private object storage must be configured before creating or restoring a backup.
- If backup infrastructure is not configured, record only the default **Automatic backups are disabled.** state and explain readiness requirements. Never expose secret values.
- Never perform a production restore for a recording.

## Scene 1 — Sign In and Secure the Admin Account

**Purpose:** Enter the Admin website and complete required first-login security.

**Before this scene:** Complete **Recording Setup** above.

**On-screen actions**

1. Open NutriScope and sign in with the demo Admin account.
2. If prompted, replace the temporary password and add a recovery email.
3. Open the Admin Dashboard and point out the Admin-only navigation.
4. Briefly show that Admin uses the website, while FSS uses the mobile app.

**Narration**

> Admin access is limited to active Admin accounts. New accounts may be required to replace a temporary password and add a recovery email. Admin manages system operations but does not receive the RND patient-care workspace or FSS mobile workflow.

**Expected result:** The Admin Dashboard opens and the account no longer has unfinished onboarding requirements.

## Scene 2 — Review Dashboard Health and AI Limits

**Purpose:** Start with system-level activity, user counts, and AI usage rather than patient records.

**Before this scene:** Refer to **Scene 1 — Sign In and Secure the Admin Account**.

**On-screen actions**

1. Review total users and counts for Admin, RND, and FSS.
2. Point out aggregate patients in care without opening patient clinical information.
3. Review monthly AI calls, tokens, estimated cost, and recent audit activity.
4. Open the AI Usage Explorer and inspect daily or monthly usage.
5. Update a demo token cap only if the recording environment permits it, then confirm the saved value.
6. Use a quick action to return to a feature page, then return to the dashboard.

**Narration**

> The dashboard summarizes system health and workload. Patient information remains aggregate. AI limits control usage at system level, while recent events help Admin decide whether account, audit, or communication work needs attention.

**Expected result:** Current KPIs, AI usage, limits, and recent system events are visible without exposing patient-level clinical records.

## Scene 3 — Publish Announcements and Revise the SOP

**Purpose:** Send role-appropriate communication and preserve approved procedure history.

**Before this scene:** Refer to **Scene 1 — Sign In and Secure the Admin Account**.

**On-screen actions**

1. Open **Announcements**.
2. Create a demo announcement with category, audience, text, image, and pin setting.
3. Edit the demo announcement, then show its audience, the author's profile photo, and the bounded image frame that preserves the post photo's original ratio.
4. Open the pinned SOP and save a small demo revision.
5. Open **History** and point out the current version, prior version, author, role, and timestamp.
6. Remove the disposable announcement after its behavior is shown.

**Narration**

> Announcements reach only their selected audience. The approved SOP stays pinned and every saved revision creates history rather than silently replacing the previous procedure.

**Expected result:** The announcement reaches its intended roles and SOP History contains the new current version plus earlier versions.

## Scene 4 — Create and Maintain User Accounts

**Purpose:** Provision accounts, correct access, and revoke sessions after sensitive changes.

**Before this scene:** Use the disposable RND and FSS identities from **Recording Setup**.

**On-screen actions**

1. Open **Manage Users** and search or filter the list.
2. Create a demo account with name, sign-in email, role, active status, and temporary password.
3. Edit the demo account's name or role and save.
4. Reset its password and explain secure out-of-band delivery.
5. Suspend and reactivate the account, showing the status change.
6. Point out that Admin cannot deactivate or delete the currently signed-in Admin account.
7. Delete only the disposable account created for this recording.

**Narration**

> Admin controls account identity, role, status, and password resets. Role, status, and password changes revoke active sessions and create audit events. Self-deactivation and self-deletion are blocked to prevent accidental lockout.

**Expected result:** Account changes are saved, session-revoking actions are reflected, and the signed-in Admin remains protected from self-lockout.

## Scene 5 — Review Allowed Reports

**Purpose:** Browse operational reports while preserving the clinical privacy boundary.

**Before this scene:** Ensure the demo environment contains at least one allowed operational report.

**On-screen actions**

1. Open **Reports** and review the report catalog.
2. Open an allowed Program Project Activity, Menu Calendar, Procurement Pack, Accomplishment, or aggregate Demographic Census report.
3. Use Preview and Download and explain that they read the latest saved report data.
4. Archive an inactive demo report and open the **Archived** tab.
5. Restore or delete only the disposable archived report when permitted.
6. Point out that Patient Menu Plan and NCP Summary are unavailable to Admin.

**Narration**

> Admin can review approved operational and aggregate reports. Preview and Download do not create a second report or alter its identity. Archiving hides an inactive saved report. Patient-specific clinical reports remain blocked by the server, not merely hidden from the page.

**Expected result:** Allowed reports can be browsed and managed, while patient-specific report types remain inaccessible.

## Scene 6 — Inspect the Budget Without Editing RND Records

**Purpose:** Review fiscal-year allocation, ledger entries, and activity in read-only mode.

**Before this scene:** Use a fiscal year with demo budget activity.

**On-screen actions**

1. Open **Budget**.
2. Select a fiscal year and review allocation, spending, and remaining balance.
3. Open ledger entries and budget activity.
4. Point out the absence of create-budget and manual-adjustment actions.

**Narration**

> Admin can inspect budget status and history for oversight. Creating a fiscal-year budget and making manual ledger adjustments remain RND responsibilities.

**Expected result:** Budget totals, ledger, and activity are visible without Admin mutation controls.

## Scene 7 — Investigate Audit Logs Safely

**Purpose:** Trace system activity without exposing raw secrets or unauthorized clinical content.

**Before this scene:** Complete at least one demo action from Scenes 3 or 4.

**On-screen actions**

1. Open **Audit Logs** and select a module.
2. Filter by action, actor, and date, then move between result pages.
3. Open one event to review its safe summary and context.
4. Open **Historical audit record** when available and compare approved before/after fields.
5. Export the filtered audit view only if the demo environment permits secure local handling.
6. Review retention settings and save a demo-safe change only when authorized.

**Narration**

> Audit Logs provide structured, paginated oversight. They avoid raw properties, file contents, prompts, credentials, and unauthorized clinical value differences. Sensitive audit access is also recorded.

**Expected result:** Admin can find and understand the demo action through safe event details, history, and filters.

## Scene 8 — Supervise Backup and Whole-System Recovery

**Purpose:** Understand automatic schedules, manual restore points, retention, and staged recovery safeguards.

**Before this scene:** Follow the backup requirements in **Recording Setup**. Use non-production infrastructure only.

**On-screen actions**

1. Open **Backups** and review backup health, storage use, and the latest successful recovery-test date. Explain that **Failed** means a backup attempt produced no usable restore point and may be removed as a failed record; **Recovery test** is the latest successful isolated restore verification, not a production restore. Dates come from stored backup/recovery records, so investigate any unexpected old date instead of treating it as a hardcoded label. For a clean demo, show the default **Automatic backups are disabled.** message before enabling anything.
2. Point out the independent Daily, Weekly, and Monthly toggles, fixed retention counts, and next Asia/Manila run for each enabled schedule.
3. Use **Restore points**, **Failed**, and **Recently deleted** to show the primary views. Use the wrapping Daily, Weekly, Monthly, Manual, and Pre-restore filters and pagination. Explain that one automatic archive may appear under several schedule filters when it satisfies coinciding schedules.
4. In configured staging, select **Create backup now**, then show the conditional **Backup activity** panel moving through Queued, Creating, and Verifying before the result appears in Restore points. Confirm the Manual filter shows the point without an automatic expiry date.
5. Show an automatic point's **Expires on** date. Show that a protected Pre-restore row has no Delete action. Move an older eligible demo point with **Delete**, show its 48-hour Recently deleted deadline, then select **Keep backup**.
6. Open one failed attempt, review its safe message, open the delete confirmation, and cancel. If the staging record is disposable, remove it and confirm the audit event remains.
7. Open **Restore** for an eligible backup and review the newer-data loss warning, fresh-authentication requirement, and exact confirmation phrase. Cancel before switching unless this is an approved recovery drill.
8. Explain that **Restoration activity** shows the current stage and that a real restoration creates one Pre-restore backup, restores one temporary MySQL database, verifies matching uploaded files, enters shared maintenance mode only when ready and explicitly enabled, performs health checks, and automatically restores the Pre-restore backup on failure. Separate attempts create separate protected copies.

**Narration**

> Automatic schedules are off by default. Daily, weekly, and monthly schedules can be enabled independently only after readiness checks pass. One archive can satisfy coinciding schedule categories, and current-period idempotency prevents repeated scheduler checks from creating duplicates. Manual backups use the same encrypted, verified pipeline and have no automatic expiry. Delete moves an eligible point to Recently deleted for 48 hours. Whole-system recovery is staged outside the browser request, validates one temporary database and matching files first, and keeps a Pre-restore backup for rollback. On the selected single-Droplet deployment, switching remains gated by Redis maintenance mode, storage, recovery-test, and explicit restore-enable checks. Backup archives and credentials are never sent to the browser.

**Expected result:** Admin understands schedule state, primary views, type filters, conditional activity, expiry, pagination, failure cleanup, Recently deleted, idempotency, and guarded whole-system recovery without exposing secrets or changing production.

## Scene 9 — Maintain Branding, Budget Setting, and Preferences

**Purpose:** Update shared report presentation and local display preferences.

**Before this scene:** Use the privacy-safe logo files from **Recording Setup**.

**On-screen actions**

1. Open **Settings**.
2. Select **Edit**, update demo hospital/service branding fields, and use the visible shared image pickers to upload the left and right report logos. Save and confirm both logos render in equal square boxes without stretching; cancel one edit to show display mode returns unchanged.
3. Save **Food Service Budget per head per day** with a valid non-negative value.
4. Switch between Comfortable and Compact density.
5. Toggle local announcement and follow-up notification preferences.

**Narration**

> Branding and the food-service per-head setting are shared system configuration. Density and notification preferences affect this browser's experience. Secrets and infrastructure credentials are never managed from this page.

**Expected result:** Shared settings save successfully and local preferences change without exposing platform configuration.

## Scene 10 — Use Notifications, Help, and Profile

**Purpose:** Find system alerts, role-safe guidance, and personal account settings.

**Before this scene:** Publish the demo announcement from **Scene 3**.

**On-screen actions**

1. Open **Notifications**, select the demo announcement notification, confirm it opens the exact announcement, return, and dismiss the informational notification. Use **Mark all read** when available.
2. Open **Help**, search `pre-restore`, expand the backup status/type guidance, then search `verifying` to show activity-stage help.
3. Confirm no RND clinical or FSS execution answers are shown.
4. Open **Profile**. Confirm the sign-in email is light, display-only account identity. Select **Edit** for a demo-safe name/contact change, cancel once, then save and confirm the page returns to display mode. Update recovery email only if the demo can complete its verification flow.
5. Select a demo profile photo, show the circular crop frame, drag/pan and zoom without stretching the image, cancel once to preserve the current photo, then select it again and apply the square crop.
6. Change the password only if the recording account can be updated safely.
7. Point out that role/designation is read-only in Profile.

**Narration**

> Notifications collect Admin and system alerts, open the exact supported record, and allow informational or resolved items to be dismissed. Help is role-scoped, so Admin sees shared and administrative guidance without clinical workflows. Its Backup and Recovery answers explain schedules, retention, Restore points, Failed, Recently deleted, backup activity, Pre-restore backups, restoration history, and expected sign-out after a whole-system restore. Profile manages personal identity and recovery settings. Profile photos use a circular drag-and-zoom crop without distorting the source, while canceling or a failed save keeps the current photo. Role changes remain controlled through account administration.

**Expected result:** Notifications, role-safe Help, and personal Profile tools work without crossing role boundaries.

## Scene 11 — Sign Out Safely

**Purpose:** End the Admin session and protect administrative access.

**Before this scene:** Finish all other recording scenes and cleanup actions that require Admin access.

**On-screen actions**

1. Open the profile menu.
2. Select **Sign out**.
3. Try returning to an Admin URL and confirm sign-in is required.

**Narration**

> Signing out ends the current session. Administrative pages cannot be reopened without authentication and an active Admin role.

**Expected result:** The sign-in page opens and protected Admin pages no longer display.

## Optional Exception Captures

- Suspended account: sign-in is rejected and existing sessions no longer work.
- Unauthorized role: a non-Admin account cannot open `/admin` pages or `/api/admin` endpoints.
- Backup readiness failure: enabling a schedule returns a safe checklist without revealing secrets.
- Final schedule disable: Admin must confirm before all automatic schedules are turned off.
- Empty report or audit search: page shows a clear empty state and preserves filters.
- Failed upload: invalid logo or profile image shows validation without saving the file.
- Recovery failure drill: history states that the restore attempt Failed or Rolled Back and its Pre-restore backup remains protected until processing finishes.

## Closing Shot

**On-screen actions**

1. Show the signed-out screen.
2. Display a short title card: **Admin protects access, oversight, communication, configuration, and recovery.**

**Narration**

> NutriScope gives Admin system-level control without granting routine patient-care access. Sensitive actions are authorized, validated, audited, and recoverable.

## Recording Cleanup

- Delete only disposable announcements and accounts created for recording.
- Restore demo settings changed solely for filming.
- Remove downloaded audit exports, reports, receipts, or images from the recording device when no longer needed.
- Do not delete backup restore points needed for an approved recovery drill or retention test.
- Confirm the active Admin account, RND and FSS demo access, automatic schedule choices, and notification settings are in their intended final state.
