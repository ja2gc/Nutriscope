# FSS Native App Consolidation

## Goal

Keep RND and Admin on the browser website and make the Expo Android app the only FSS operational client. Preserve one stable website address for downloading the newest APK.

## Product decisions

- Keep six primary tabs: Home, Announcement, Menu, Meal Prep, Accomplish, and Purchase. Announcement is second and separates Announcements and SOP into internal tabs.
- Keep notifications and the profile menu in the header. Put Profile, Notifications, Help, Settings, update checking, and sign out in the secondary menu.
- Accomplish contains Daily Log and My Reports views. Daily Log opens on the device's local date, accepts past dates, rejects future dates, and always shows a Today action after another date is selected.
- Allow multiple working rows for one date so staff can record separate wards. Do not allow off-duty and working rows to coexist for one user and date.
- Menu is a read-only weekly reference. Meal Prep owns the selected-day operational view and actual served population.
- Food and recipe details open on a dedicated read-only mobile screen with normal back navigation.
- The weekly accomplishment report sums ward populations and combines completed duties for each date.
- FSS can open their own archived report details and download or share the authenticated PDF.
- Remove the FSS PWA, service worker, offline shell, web install prompts, and obsolete service-completion controls. Old FSS website links redirect to the Android download page.
- The public download page shows an APK button on phones and a QR code on desktop. Both use a stable URL; releases replace the APK and metadata without changing the QR code.

## Security and data rules

- Laravel remains the authority for roles and dates. Only an authenticated FSS user can create a Daily Log row.
- Dates use Philippine local time for the future-date boundary.
- FSS reads stay scoped to the signed-in user. FSS report downloads use the existing report authorization.
- Actual served population remains the lifecycle input for suggested food purchase orders. It does not deduct inventory.
- Existing accomplishment rows remain immutable in this change; corrections do not bypass archived-report and audit controls.

## Implementation sequence

1. Add failing backend tests for role, future date, off-duty conflicts, and multi-ward report aggregation.
2. Apply the smallest request-validation and report-generator changes; run focused tests and formatting.
3. Add mobile date helpers and source-contract tests, then build the Accomplish date flow, report switcher, and PDF action.
4. Simplify Menu and Meal Prep, add dedicated food details, and implement the secondary account menu.
5. Replace PWA distribution with the stable APK landing page and cleanup old registrations.
6. Update concise FSS help and FAQ content.
7. Run backend, frontend, and mobile verification; inspect the complete diff; commit only task files; push `main`; verify the remote ref.

## Release flow

1. Increase the Expo app version and Android version code.
2. Build the signed APK with the existing EAS preview or production APK profile.
3. Replace `nutriscope-fss.apk` and `nutriscope-fss.json` on the download host.
4. Verify the stable download URL, metadata, SHA-256, QR destination, and update prompt.

The QR code does not change between releases. Android may still show its normal approval prompt for an APK installed outside Google Play.
