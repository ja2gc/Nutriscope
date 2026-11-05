# FSS Android User Manual

Document version: 1.0 | Issued: 5 November 2026

Prepared by: EMPhive — Louis Figo, Jhobe Fajardo, Maria Beatriz Dela Cruz and Jared Abriol.

Use this manual for routine work and staff training. Start with the prerequisites, follow the visible navigation and numbered steps, and confirm the saved result before continuing. Actions depend on account role, ownership and record state.

[All role manuals](../docs/ROLE-HOW-TO.md) · [Setup and turnover](../docs/operations/README.md) · [FAQ](../docs/FAQ.md)

## Before you begin

Use the institution's signed NutriScope FSS Android app and an active **FSS** account. Ask Admin for account access and complete the first-login password/recovery setup. Read the current SOP. If practicing, use a separate training installation and fictional purchase evidence.

### Work RND or Admin prepares first

| Feature | Required preparation | Your next action |
|---|---|---|
| Sign in | Admin creates/activates your FSS account; operator publishes the signed APK and working API | Open the app, sign in and complete account setup |
| Menu and food details | RND saves the week and its operational Menu Items in web **Food Service → Menu Calendar** | Tap **Menu**, choose the week/date/meal and open an item for quantities and notes |
| Meal Prep population | An applicable current or previous service date and its menu are available | Tap **Meal Prep**, choose the actual service date and save the actual patient population |
| Purchase actuals/evidence | RND converts the reviewed shopping list into a PO | Tap **Purchase → PO → vendor**; review/save actual values and receipt/proof |
| Receiving confirmation | Your actuals and required evidence have been saved | Ask RND to review and **Mark vendor received** in web Procurement; uploading alone does not receive the vendor |
| Accomplishment report | Save every required date, including actual off-duty days, in the reporting period | Open **Accomplish → My Reports**, inspect missing dates and submit a completed past period |
| Approved PDF | RND approves the submitted period | Return to **My Reports**, open the approved period and view/download its PDF |
| Password recovery | A recovery email is verified on your account | Use the sign-in recovery option; otherwise ask Admin to verify your identity and restore access |

FSS does not configure provider accounts, seed data, create patient care plans or author Menu Calendar plans. Missing menus, catalog items or purchase orders go to RND; account access goes to Admin; server/download failures go to the institution's technical operator.

## Page directory

| Page or panel | How to reach it | What to do |
|---|---|---|
| Sign in | Open the institution's NutriScope website or FSS app | Use the account's correct platform; [sign-in instructions](#shared-sign-in-and-secure-the-account) |
| Forgot/reset password | Choose the password-recovery option on sign in | Request recovery through the verified recovery mailbox, follow the received reset link and set a new password; contact Admin when recovery is unavailable |
| Account setup | First-login prompt or Profile reminder | Change the temporary password, add/verify recovery email and complete outstanding requirements |
| Profile | Web account menu or mobile Settings | Maintain identity/contact, recovery email, password and supported profile photo controls; role/sign-in email fields can be display-only |
| Notifications | Web Notifications/bell or mobile bell | Open exact linked work, distinguish read from resolved and dismiss only eligible items |
| Help | Web sidebar; mobile Settings → Help & Support → Help | Search the role's available topics and expand answers |
| Mobile download | Website `/mobile-app` | Scan the QR or download/install the institution's signed APK; see [Android operation](#android-installation-and-updates) |

### FSS pages

| Page or panel | How to reach it | Procedure |
|---|---|---|
| Home | Home tab | [Today's work](#1-home), menu, purchasing queues and latest announcements |
| Announcement/SOP | Announcement tab | [Read posts, procedures and history](#6-announcements-sop-and-notifications) |
| Menu | Menu tab | [Browse the current/other week and meal slots](#2-menu); planned-food editing stays with RND |
| Food details | A food/menu-item card | Review recipe/ingredient quantities and preparation notes; return to the originating task |
| Meal Prep | Meal Prep tab | [Actual served population](#3-meal-prep), including the selected service date |
| Accomplish/daily log | Accomplish tab | [Daily duties and off-duty entry](#4-accomplish) |
| My Reports/period detail | Accomplish → My Reports | Complete dates, submit, withdraw pending work, correct rejection and download approved PDF |
| Purchase/vendor detail | Purchase tab → PO → vendor | [Actual buying values, vendor changes and evidence](#5-purchase); RND confirms received status |
| Settings/Profile | Header account icon | [Preferences and account security](#7-help-settings-and-profile) |
| Notifications/Help | Bell; Settings → Help | Open linked work or role-scoped answers; these are outside the six main tabs |

## Daily and periodic work

| Role/time | Work sequence | Completion check |
|---|---|---|
| FSS, start of shift | Home; Announcement/SOP; Menu and food details; pending Purchase work | Correct service date, planned meals and vendor tasks understood |
| FSS, during shift | Prepare planned meals; save actual Meal Prep population; record purchase actuals and evidence | Evidence saved and communicated to RND; uploads are not receiving confirmation |
| FSS, end of shift | Save Accomplish daily counts/duties, or the actual off-duty entry; review missing dates; sign out on shared device | Daily log saved for the correct date |
| FSS/RND, reporting period | FSS completes and submits the past period; RND approves or rejects with reason; FSS corrects and resubmits if needed | Approved PDF available; approved logs locked |

## Android installation and updates

1. Use the institution's HTTPS `/mobile-app` page. Download the signed NutriScope FSS APK from that page or its QR; use the approved institution source.
2. If Android requests permission to install from that browser/file manager, enable it only as needed for the trusted APK and follow the device's installation prompts.
3. Open NutriScope and sign in with an active FSS account. Complete account setup before shared-device use.
4. For an update, use the app's update prompt/settings entry or the same institution download page. Install over the existing app so the package/signing identity is preserved. If Android rejects the update, contact the institution's operator; do not uninstall to bypass a signature mismatch.
5. The application requires connectivity to its configured server. A connection failure does not prove a save succeeded; confirm the saved record after connectivity returns before repeating a mutation.

## Shared: Sign In and Secure the Account

### First sign-in and the persistent reminder

1. Sign in using the account's **sign-in email** and temporary password. Use the institution's website for RND/Admin and Android app for kitchen staff.
2. In account setup, enter and confirm a new password of at least eight characters, then select **Next**.
3. Enter a recovery email whose inbox you can access and select **Send verification code**.
4. Open that inbox, enter the six-digit code and select **Verify recovery email**. The code expires after 10 minutes; **Send another code** replaces it, so use the newest code.
5. **Do later** defers setup. After deferral, the reminder stays until both the password and recovery-email requirements are finished. Select **Open** on the reminder to continue in Profile.
6. Confirm your name in Profile; reports and audit attribution use it. Keep temporary credentials and verification codes private.

### Add, replace or remove recovery email

1. Open the profile icon/menu > **Profile**, then choose **Add recovery email** or **Change recovery email**.
2. Enter the intended inbox and select **Send verification code**. Enter the newest six-digit code within 10 minutes, then select **Verify recovery email**.
3. Check that the address is verified before leaving. When replacing an address, the old verified inbox continues receiving password resets until the new one is verified.
4. Removing recovery email disables reset emails until another address is verified and brings back the persistent setup reminder. Verify a replacement promptly.
5. To change a known password in Profile, enter the current password and enter/confirm the new one. Sign in again after the change.

### Forgot password or missing reset email

1. From sign-in, select **Forgot password?** Enter the account's **Sign-in Email**, then select **Send Reset Link**. Do not enter the recovery address in place of the sign-in address.
2. Open the **verified recovery inbox**, including its spam folder. The generic confirmation on the request page does not prove mail was sent; an account without verified recovery cannot receive the link.
3. Open the newest message within 15 minutes. A newer request invalidates the earlier link. The link opens the web reset form with **Sign-in Email** and **Reset Token** supplied.
4. Enter **New Password** and **Confirm New Password**, then select **Reset Password**. Sign in again; the reset ends previous sign-ins. Kitchen staff complete the reset in the browser, then return to the Android app to sign in.
5. If no usable message arrives, confirm the sign-in address and verified inbox before requesting again. If recovery was never verified, the inbox is inaccessible or the latest link still fails, contact the institution's administrator for an account reset. Do not send passwords or codes in a support message.

## FSS Mobile Workflow

### 1. Home

1. Open **Home** after signing in.
2. Check **Meals to log today** and **Pending POs**.
3. Open **This week's menu** to review the plan for today's service date.
4. Review each pending PO's **Needs receipts** or **Needs served population** status.
5. Review today's service and announcements.

### 2. Menu

1. Open **Menu**.
2. Check the displayed service date; Menu starts on today and its current week. Use the date controls to review another day.
3. Select a meal slot to view servings, ingredients, and preparation notes.
4. Do not attempt to change planned foods; FSS menu access is read-only.

### 3. Meal Prep

1. Open **Meal Prep**.
2. Review today's planned service and food profiles.
3. Enter actual total patient population when prompted.
4. Use the date selector to enter or correct a previous service date when needed.

### 4. Accomplish

1. Open **Accomplish**.
2. Enter the number of ward diet lists collected and meals distributed.
3. Select the other five duties completed.
4. If off duty, enable that switch; the report labels the date **Off duty**.
5. Choose **Save today's log** or **Save past log**, depending on the selected date.
6. Saving the same date updates the existing entry until its period is submitted or approved.
7. Open **My Reports** to check period progress. Save every required date, including off-duty days, then select **Submit for review**. Submission does not require waiting until the period ends once all dates are logged. Withdraw while pending to edit; after rejection, read RND's reason, correct the dates, and resubmit. Approval locks the logs and makes the PDF available to view or download.

### 5. Purchase

1. Open **Purchase** and select a PO.
2. Open the correct vendor group.
3. If the actual vendor changed before evidence was uploaded, use **Change vendor for all** for the group or **Change vendor** on one item row.
4. Review the visible planned purchase. Expand **Vendor line details** to review the planned and actual purchase lines.
5. Confirm or correct the prefilled actual decimal quantity and unit price, then save.
   If entering a row's **Actual Total**, review the derived quantity at its current unit price. The separate vendor **Actual receipt total** may reflect cash rounding: enter the actual charge when needed, or select **Use calculated total** to return to the sum of item totals. This does not rescale the items.
6. Enter an OR number only when the vendor provided one.
7. Upload at least one receipt and one proof-of-purchase image for the vendor.
8. Ask RND to review the saved actual values and evidence and use **Mark vendor received** on web Procurement. Uploads alone do not change the status.
9. Completed/archived POs lock edits.

### 6. Announcements, SOP, and Notifications

1. Home shows the three latest announcements with their tags. Open **Announcement** in the bottom navigation for the full list, ten posts per page, then switch between Announcements and SOP.
2. Use the bell for notifications; opening a notification may navigate to its target.
3. Use Settings to mark all read if needed.

### 7. Help, Settings, and Profile

1. Use the header account icon to open Settings.
2. Under **Help & Support**, open **Help** to search Shared and FSS-only answers. Help remains outside the six main tabs.
3. Set comfortable/compact density and reduced motion.
4. Open Profile to edit name and contact number, view the read-only sign-in email, and manage recovery email and password.
5. Sign out from Settings when the device is shared.

## End-of-work check

Confirm the correct date and record, save outstanding changes, read any validation or failure message, and reopen the saved record when confirmation is uncertain. Do not treat a failed network request as a successful save. Sign out on shared devices. For cross-role responsibilities use [the handoff sequence](../docs/ROLE-HOW-TO.md#cross-role-operational-closeout).
