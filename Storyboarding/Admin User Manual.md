# Admin Website User Manual

Document version: 1.0 | Issued: 5 November 2026

Prepared by: EMPhive — Louis Figo, Jhobe Fajardo, Maria Beatriz Dela Cruz and Jared Abriol.

Use this manual for routine work and staff training. Start with the prerequisites, follow the visible navigation and numbered steps, and confirm the saved result before continuing. Actions depend on account role, ownership and record state.

[All role manuals](../docs/ROLE-HOW-TO.md) · [Setup and turnover](../docs/operations/README.md) · [FAQ](../docs/FAQ.md)

## Before you begin

Use the institution's NutriScope website with an active **Admin** account. Complete password/recovery onboarding. Establish which institution staff own clinical decisions, user administration, technical operations, backups and approval of recovery work. Practice recovery only in an isolated training target with fictional data.

On a narrow screen, select **Open navigation** in the top bar. The sidebar shows the full menu; use its top arrow (**Close navigation**), select a destination, tap outside it or press **Escape** to close it. Open **My Profile** through the top-right profile button; account details, recovery email and password appear as sections on one scrolling page.

### Initial administrative setup

| Task | Navigate and act | Check before proceeding |
|---|---|---|
| Confirm first administrator | Sign in with the institutional account supplied through the approved initial-data/turnover process | Do not use a public exhibition account as the permanent institutional administrator |
| Establish staff access | **Manage Users**; create named RND/FSS/Admin accounts with the correct role and temporary password | Securely deliver credentials; users complete their own password and recovery-email setup |
| Prepare institutional reports | **Settings** and authorized **Reports → Template Edit** | Hospital/service identity, logos and appropriate signatory defaults reflect the institution |
| Configure budget | **Budget → Fiscal Year Setup** and **Settings → Food Service Budget** | Confirm allocation and shared per-head/day value; later manual adjustments need type, amount and reason |
| Communicate procedures | **Announcements → SOP**; review current text and use **Revise SOP** for an approved change | New history version exists; announce changes to the intended audience |
| Check AI use | **Dashboard** and AI usage controls/explorer | Review current caps, usage/cost settings and display timezone; provider billing/key/model access remain operator responsibilities |
| Prepare backups | Operator configures separate private backup storage, archive password and required worker/scheduler roles | **Backups** readiness checks pass; do not assume bucket existence proves recovery |
| Enable automatic schedules | **Backups** schedule controls | Select needed schedules, review next runs and monitor failures; avoid disabling the last schedule unintentionally |
| Establish recovery readiness | Approved isolated restore rehearsal with the technical operator | Confirm database, protected files, keys and restore capacity; document the result privately |

Admin manages access and oversight; RND prepares clinical Food Library data, operational Inventory/Menu Items, templates and menus. Admin is not a substitute clinical role. Infrastructure setup is in the [installation and turnover manual](../docs/operations/README.md); patient-specific clinical reports remain restricted.

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

### Admin pages

| Page or panel | Visible navigation | Procedure |
|---|---|---|
| Dashboard/AI explorer | Dashboard | [Health, usage and limits](#1-review-system-dashboard) |
| Manage Users/account form | Manage Users | [Create/edit/suspend accounts and reset access](#2-manage-accounts) |
| Announcements/SOP history | Announcements | [Institutional posts and approved procedure revisions](#4-announcements-and-sop) |
| Reports | Reports | [Permitted operational/aggregate reports](#5-reports-and-budget-oversight); no patient clinical report access |
| Budget | Budget | Fiscal-year setup, manual adjustments, summary, ledger and history |
| Audit Logs/event/history | Audit Logs | [Filter, inspect and export safe event details](#3-audit-oversight) |
| Backups/restoration activity | Backups | [Schedules, restore points, retention and controlled recovery](#7-supervise-backups-and-recovery) |
| Settings | Settings | [Branding, report logos, shared budget setting and local preferences](#6-system-settings) |
| Profile/Notifications/Help | Account menu, bell and Help | [Personal security and administrative guidance](#8-help-profile-and-notifications) |

## Daily and periodic work

| Role/time | Work sequence | Completion check |
|---|---|---|
| Admin, routine oversight | Dashboard; account exceptions; audit and backup failures; permitted reports/budget | Problems assigned to the institution's responsible operator |
| Admin, staff change | Create named accounts or suspend departed users; verify correct role and recovery onboarding | Access reflects current responsibilities |
| Institution operator, periodic | [Backups, isolated restore, certificates, resource/billing checks and updates](../docs/operations/maintenance.md) | Operational evidence retained privately; no unnecessary destructive cleanup |

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

## Admin Web Workflow

### 1. Review System Dashboard

1. Open **Dashboard**.
2. Review user totals, aggregate patients in care, AI usage/cost, and audit-event volume.
3. Review daily/monthly AI token usage and configured caps.
4. Use AI Usage Explorer and Recent Activity to identify spikes or failures.
5. Use quick actions for accounts, audit logs, and announcements.

### 2. Manage Accounts

1. Open **Manage Users**.
2. Search or filter by role.
3. To create an account, enter first/last name, sign-in email, role, and temporary password with its confirmation. New accounts are active; use account administration for later status changes.
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
2. Publish system/department posts with category, pin state, text, and images. Under **Visible to**, select one or more roles; select all three for everyone. Audience choices appear in the editor only. Authors retain access to their own posts; Admin retains oversight access to all posts.
3. Edit/delete through the Admin board.
4. Revise SOP only for approved procedural change; confirm the new version appears in History.

### 5. Reports and Budget Oversight

1. Open **Reports** for Program Project Activity, Menu Calendar, Procurement Pack, and Accomplishment PDFs, or the aggregate Demographic Census year/month screen.
2. Preview permitted data; Accomplishment Report shows approved new periods and earlier generated reports. Admin does not receive a review queue.
3. Do not seek Nutrition Intervention Plan or NCP Summary; server access is blocked for Admin.
4. Open **Budget** to inspect fiscal-year summary, ledger and history. Admin can also use fiscal-year setup and create a manual adjustment with a type, amount and reason. Confirm the audited ledger entry and resulting balance; existing ledger entries are not directly rewritten.

### 6. System Settings

1. Open **Settings**.
2. Maintain hospital name, service name, address, accreditation, province, LGU, and report logos.
3. Set food-service budget per head/day.
4. Set local display/notification preferences.
5. Return to Dashboard to confirm metrics after operational changes.

### 7. Supervise Backups and Recovery

1. Open **Backups** and confirm backup health, storage use, and the latest recovery-test date.
2. Use the primary views **Restore points**, **Failed**, and **Recently deleted**. **Backup activity** appears separately only while a backup is Queued, Creating, or Verifying.
3. Use the wrapping Daily, Weekly, Monthly, Manual, or Pre-restore filters. One automatic archive may appear under several schedule filters when it satisfies coinciding schedules. One protected Pre-restore backup is created before each restoration attempt.
4. Enable only the automatic schedules needed; readiness must pass, and each enabled schedule shows its next run.
5. Select **Create backup now** for an independent manual restore point. Manual points have no automatic expiry.
6. Use **Delete** to move an eligible point to Recently deleted for 7 days. Use **Keep backup** to return it to Restore points, or **Delete permanently** only after confirming permanent loss. Protected Pre-restore backups do not show Delete until protection expires.
7. Remove failed records only after reviewing their safe failure message. No usable archive exists for a failed attempt.
8. Use **Restore** only for an approved whole-system recovery. Review newer-data loss, enter the current password and exact confirmation phrase, then monitor **Restoration activity** through completion, cancellation, failure, or rollback.
9. If the application or restore screen is unavailable, contact the institution's authorized technical operator and use [manual R2 recovery](../docs/operations/backup-recovery.md#manual-recovery-when-the-admin-screen-is-unavailable). That procedure retrieves an existing archive/manifest/files and restores an isolated target; it does not require the Admin screen or permit a blind import over the live database.

### 8. Help, Profile, and Notifications

1. Open **Help** in the sidebar to search Shared and Admin-only answers; no RND clinical guidance is exposed.
2. Use Profile to update personal identity/contact/photo, view the sign-in email, manage recovery email and change the password.
3. Use Notifications for announcements addressed to Admin and system alerts.

## End-of-work check

Confirm the correct date and record, save outstanding changes, read any validation or failure message, and reopen the saved record when confirmation is uncertain. Do not treat a failed network request as a successful save. Sign out on shared devices. For cross-role responsibilities use [the handoff sequence](../docs/ROLE-HOW-TO.md#cross-role-operational-closeout).
