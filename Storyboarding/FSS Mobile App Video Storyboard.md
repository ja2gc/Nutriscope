# FSS Mobile App Video Storyboard

Verified against the current Expo/React Native screens and Laravel FSS role gates on **2026-09-15**.

Use this as both the recording checklist and narration script. The main recording uses only the NutriScope Android app. Preparation completed by RND is listed as setup and is not repeated during the FSS recording.

## How to Use This Script

1. Complete **Recording Setup** before filming.
2. Record the numbered scenes in order. A scene that needs an earlier result includes an exact **Before this scene** reference.
3. Follow **On-screen actions** while recording, then use **Narration** as the spoken explanation.
4. Confirm the **Expected result** before moving to the next scene.
5. The future NutriScope **Help → Guides** page will use shorter user instructions and optional video links. Camera directions and cleanup notes stay in this recording script.

**Overall sequence:** Install and sign in → check today's work → read announcements and procedures → review the menu → record people served → receive purchases → complete daily logs → open semi-monthly reports → use account tools and sign out.

## Words Used in This Script

- **APK:** The Android installation file used to install NutriScope FSS.
- **RND:** Registered Nutritionist-Dietitian who prepares menus and purchase orders on the website.
- **PO (purchase order):** The approved buying record prepared by RND and received by FSS.
- **Vendor:** The supplier or seller providing the purchased items.
- **SOP (Standard Operating Procedure):** The current approved instructions for food-service work.
- **Daily Log:** One FSS accomplishment record for a date, with two numeric counts, five checkbox duties, or Off duty.

## Recording Setup

- Install the current signed APK from `https://nutriscope.live/mobile-app` on an Android phone or emulator.
- Use an active demo account assigned to the Food Service Staff (FSS) role. Do not use an RND or Admin account.
- Ask RND to prepare one active Monday-to-Sunday menu containing demo meals and food profiles. For that setup, refer to **RND Food Service Operations Video Storyboard, Scene 3 — Create and Reuse a Weekly Menu**.
- Ask RND to prepare one open demo purchase order with items assigned to vendors. For that setup, refer to **RND Food Service Operations Video Storyboard, Scene 7 — Create and Release the Purchase Order**.
- Prepare privacy-safe demo receipt and proof images. Do not use real financial or patient records.
- For the report scene, prepare privacy-safe Daily Logs across one semi-monthly period for the signed-in FSS user.
- Hide device notifications, passwords, verification codes, email links, tokens, and unrelated apps before recording.
- Keep the phone in portrait orientation and use one consistent display size throughout the main recording.

## Scene 1 — Install and Open NutriScope FSS

**Purpose:** Install the current Android app from NutriScope's permanent download page.

**Before this scene:** Complete **Recording Setup** above.

**On-screen actions**

1. On a phone, open `https://nutriscope.live/mobile-app` or scan the permanent QR shown on a desktop.
2. Select **Download NutriScope APK**.
3. If Android asks, allow this browser to install the app. Then install and open **NutriScope FSS**.
4. Point out the NutriScope `N` launcher icon and show that NutriScope opens as its own full-screen app.

**Narration**

> Food Service Staff use the NutriScope Android app. The website provides one permanent download location: a phone can download the current APK directly, while a desktop shows a QR code that opens the same page on a phone. Android may still show an installation warning because the app is installed outside Google Play.

**Expected result:** The FSS app's sign-in screen opens. RND and Admin continue to use the website.

## Scene 2 — Sign In and Secure the Account

**Purpose:** Sign in with an FSS account and replace temporary account details when required.

**Before this scene:** Refer to **Scene 1 — Install and Open NutriScope FSS**.

**On-screen actions**

1. Enter the demo FSS sign-in email and password, then select **Sign In**.
2. If this is a new account, show **Secure your account**.
3. Enter and confirm a new password, then select **Next**.
4. Enter a demo recovery email and select **Send verification code**.
5. Enter the captured six-digit code and select **Verify recovery email**.
6. Mention **Do later** without using it in the main success path; explain that deferral shows a persistent Profile reminder in the mobile header.

**Narration**

> Only active Food Service Staff accounts can sign in to this app. RND and Admin accounts continue to use the website. First-login setup replaces the temporary password, then verifies the recovery email with a six-digit code. Staff may choose Do later, but the persistent header reminder remains until both requirements are finished.

**Expected result:** The signed-in FSS Home screen and main navigation open.

## Scene 3 — Check Today's Work

**Purpose:** See the menu, service records, purchases, and announcements that need attention.

**Before this scene:** Refer to **Scene 2 — Sign In and Secure the Account**.

**On-screen actions**

1. Open **Home**.
2. Show **Meals to log today** and **Pending POs**.
3. Point out the **This week's menu** card.
4. Show a waiting reason such as **Needs receipts** or **Needs served population**, if available.
5. Scroll to today's service rows and Announcements.

**Narration**

> Home shows the current dated menu, pending purchases, served population, and announcements. The current week appears even before RND fills its meal slots. If no menu covers a selected date, FSS contacts RND.

**Expected result:** The viewer understands the day's priorities and the six primary tabs: Home, Announcement, Menu, Meal Prep, Accomplish, and Purchase.

## Scene 4 — Read Announcements and SOP

**Purpose:** Read current announcements and the approved food-service procedure.

**Before this scene:** Refer to **Scene 2 — Sign In and Secure the Account**.

**On-screen actions**

1. Open the second bottom tab, **Announcement**.
2. In **Announcements**, open the seeded demo post with a photo. Point out the author's real profile image and the fixed responsive post-photo frame, then close its detail sheet.
3. Switch to **SOP**.
4. Read the current Standard Operating Procedure and open **History**.

**Narration**

> The Announcement tab contains two separate views. Announcements show messages intended for Food Service Staff, including the author's profile photo and optional post images without stretching or cropping the original photo. SOP shows the current Standard Operating Procedure and its earlier versions. FSS can read this information but cannot change it.

**Expected result:** Announcements, the current SOP, and SOP History open within the Announcement section.

## Scene 5 — Review the Weekly Menu and Food Details

**Purpose:** Check the meals for each day and open the complete preparation details for a selected food.

**Before this scene:** RND must first activate the weekly menu. Refer to **RND Food Service Operations Video Storyboard, Scene 3 — Create and Reuse a Weekly Menu**.

**On-screen actions**

1. Open **Menu** and select the active cycle.
2. Point out **Read-only weekly plan** and **Planned population**.
3. Select a weekday, show multiple meal lines when present, and open one line. Point out that bulk rice is not represented as a menu meal.
4. On the separate Food profile page, show its source label, servings, ingredients, scaled quantities, and preparation notes.
5. Use the normal back action to return to Menu.

**Narration**

> RND controls the weekly menu. FSS can choose a day and open the complete food details needed for preparation, but cannot change the menu entry or reusable recipe. The page clearly states whether it is showing the version saved for that menu entry, the reusable recipe, or the locked version saved when the purchase order was released. The number of people actually served is entered in Meal Prep, not Menu.

**Expected result:** Menu remains read-only and the food profile behaves like a normal mobile page rather than a desktop-style modal.

## Scene 6 — Record How Many People Were Served

**Purpose:** Save or correct the real number of people served on a planned date.

**Before this scene:** Refer to **Scene 5 — Review the Weekly Menu and Food Details** for the planned meals and dates.

**On-screen actions**

1. Open **Meal Prep**.
2. Use **Service date** to choose today or an earlier planned date.
3. Review that date's meal rows; optionally open and return from one food profile.
4. Under **Actual population served**, enter a positive whole-number demo headcount.
5. Select **Record actual served**. If a record already exists, change the value and select **Update actual served**.
6. If demonstrating a past date, use **Today** to return to the current date.

**Narration**

> Meal Prep shows the selected date's planned meals and records how many people were actually served. NutriScope uses this number to finish a purchase order created from the menu and calculate food purchase cost per person served per day. Staff do not need to mark each meal as prepared or served.

**Expected result:** The saved headcount appears as the currently recorded population for that date.

## Scene 7 — Record a Vendor Delivery

**Purpose:** Confirm what a vendor actually delivered and attach the required buying evidence.

**Before this scene:** RND must first release the purchase order. Refer to **RND Food Service Operations Video Storyboard, Scene 7 — Create and Release the Purchase Order**. To review it from Home first, refer to **Scene 3 — Check Today's Work**. A **vendor group** is the set of purchase-order items assigned to one vendor.

**On-screen actions**

1. Select the notification bell and open the prepared action-required PO notification. Confirm it opens the exact purchase order, then return once and show that **Dismiss** remains visible but disabled while receiving is incomplete.
2. Open the same PO and select a vendor group.
3. Before uploading evidence, optionally demonstrate **Change vendor for all** or row-level **Change vendor** with demo suppliers.
4. Review **Planned purchase** and the prefilled **Actual purchased** values.
5. Correct **Actual qty** and **Actual unit price** only when the demo receipt differs.
6. Expand **Calculation details**, then collapse it.
7. Enter an official receipt number only if the demo vendor supplied one.
8. Select **Save actuals and optional OR**.
9. Under Receipt images and Proof of purchase, select **Upload**, choose Receipt or Proof, optionally add a caption, then choose **Library** or **Camera**.
10. Open one uploaded image to show its private in-app preview.
11. Point out the note that RND marks the vendor received on web Procurement after reviewing actual values, receipt, and proof.
12. After RND confirms the vendor, return to **Notifications** and show that the receiving action has resolved; dismiss it.

**Narration**

> FSS opens an existing purchase order; FSS does not create it. An action-required notification opens that exact purchase order and cannot be dismissed while receiving is incomplete. The approved quantity and price stay visible while staff correct the actual quantity and unit price when delivery differs. A vendor can be corrected only before receipt or proof images are attached. FSS saves actual values and uploads receipt and proof; RND reviews these on web Procurement and marks the vendor received. The official receipt number is optional.

**Expected result:** The vendor status changes to received, locked records no longer expose editing controls, and the resolved notification becomes dismissible.

## Scene 8 — Complete Today's or a Missed Daily Log

**Purpose:** Record two numeric accomplishment counts and completed duties for today or any earlier date.

**Before this scene:** Refer to **Scene 2 — Sign In and Secure the Account**. This work log is separate from the number of people served in **Scene 6 — Record How Many People Were Served**.

**On-screen actions**

1. Open **Accomplish** and keep **Daily Log** selected.
2. Show that **Log date** starts on today.
3. Enter **Collected diet list from different wards.** and **Apportioned and distributed food to in patient in the different wards.**
4. Select each applicable checkbox under **Accomplishment rows**.
5. Select **Save today's log**.
6. Choose a previous date to show **Backfilling a missed daily log** and the **Today** action.
7. Correct the same date and select **Save past log**.
8. Use **Today** to return to the current date.
9. Optionally explain **Off duty** without mixing it with working rows.

**Narration**

> Daily Log opens on today's date but allows any earlier date when work was not entered on time. Future dates are not allowed. Enter two non-negative whole numbers and use checkboxes for the other duties. Re-saving updates the same date. An Off duty entry displays an X in the report.

**Expected result:** The selected date shows its saved numeric counts and checkbox duties.

## Scene 9 — View and Save a Semi-monthly Accomplishment Report

**Purpose:** Complete a semi-monthly period, submit it for RND review, and open the approved PDF.

**Before this scene:** Refer to **Scene 8 — Complete Today's or a Missed Daily Log**. Save one entry for every date, including Off duty days, after the period ends.

**On-screen actions**

1. Inside **Accomplish**, switch to **My Reports**.
2. Open the period and show the saved-day count and each date's status. Tap a date to return to its Daily Log.
3. Submit the complete past period. Show that Daily Log becomes read-only.
4. Withdraw while RND review is pending, correct a date, and submit again.
5. After RND rejection, read the reason, correct the requested date, and resubmit. After approval, show the locked period.
6. Open the approved report from the period or Saved PDFs. Use **View PDF** and **Download PDF** and inspect the readable semi-monthly table.

**Narration**

> My Reports shows each semi-monthly period's progress and status. Only a complete past period can be submitted. Withdrawal or rejection permits correction; approval locks daily logs and the submitted PDF. Earlier generated reports remain available under Saved PDFs. Mobile opens the same stored PDF reviewed by RND.

**Expected result:** Only the demo FSS user's periods and PDFs are visible, and approval leaves the submitted PDF unchanged.

## Scene 10 — Use Notifications, Help, Account Tools, and Updates

**Purpose:** Show where secondary tools are located without crowding the main navigation.

**Before this scene:** Refer to **Scene 2 — Sign In and Secure the Account**.

**On-screen actions**

1. Select the header notification bell.
2. Open one supported demo notification and return; show read/unread state.
3. Open the header profile icon to show the side menu.
4. Open **Help**, search `purchase order`, and expand one FSS answer.
5. Open **Settings** and show **Display density**, **Reduce motion**, and **Mark all notifications read**.
6. Open **Profile** and show display-first Account Info, the read-only sign-in email, Edit/Save/Cancel for identity/contact fields, the recovery-email status with Add/Change/Remove actions, and the collapsed Change password control without entering secrets.
7. Return to the side menu and select **Check for updates**.

**Narration**

> The notification bell and profile menu keep secondary tools available without crowding the main navigation. Help contains general answers and FSS instructions. Settings control how information is displayed and allow notifications to be marked as read. Profile lets staff update their own account details, while role and account status remain controlled by Admin. Check for updates compares the installed app version with NutriScope's latest published version.

**Expected result:** The app reports either the current installed version or offers **Open download page** when a newer APK exists.

## Scene 11 — Sign Out Safely

**Purpose:** End the signed-in session so another person cannot open private FSS information.

**Before this scene:** Refer to **Scene 10 — Use Notifications, Help, Account Tools, and Updates** for the profile menu location.

**On-screen actions**

1. Open the profile side menu.
2. Select **Sign out**.
3. Confirm **Sign out** in the confirmation dialog.

**Narration**

> Signing out clears the saved sign-in session and returns the device to the FSS sign-in screen. Staff should sign out when a device is shared.

**Expected result:** Private app pages are no longer accessible without signing in again.

## Optional Exception Captures

- **No menu for date:** choose a planned date or contact RND.
- **Upload permission denied:** show the safe camera/library permission message.
- **Incomplete receiving:** show the missing actual values or evidence that RND needs before marking the vendor received.
- **Backdated accomplishment:** show the warning and **Today** recovery action.
- **Network failure:** show **Retry** rather than an empty list.
- **Incomplete period:** show saved-day count and missing dates before submission.
- **Update available:** show the version comparison and **Open download page**.

## Closing Shot

**On-screen actions**

1. Return to Home and briefly show the six-tab navigation.

**Narration**

> NutriScope gives Food Service Staff one focused Android workflow: review RND's plan, record how many people were served, receive purchases with evidence, document daily accomplishments, follow announcements and SOP, and access their own semi-monthly reports. Planning and clinical functions remain in the RND website, while administration remains in the Admin website.

## Recording Cleanup

- Prefer a disposable demo database or reset the demo environment after filming.
- Do not delete completed or archived records that the application intentionally locks.
- Delete only draft demo data through supported UI actions.
- Remove demo receipt/proof images if the vendor group remains unlocked and cleanup is permitted.
- Do not alter unrelated accounts, menus, POs, reports, or patient data.
