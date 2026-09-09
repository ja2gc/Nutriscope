# Seeded Profile Photos, Cropping, and Announcement Media Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Seed the three existing NutriScope demo accounts with local Pexels profile-photo assets, add user-controlled circular cropping, and present announcement/user-uploaded photos in consistent responsive frames without distortion.

**Architecture:** Keep AdminUserSeeder responsible for stable demo account records. Add a separate ProfilePhotoSeeder that runs after it, reads pinned local image files, calls StoredObjectStorage::storeBytes(), and links the resulting stored_objects row to each user. Reuse the current profile update pipeline after a focused client-side circular crop. Extend the existing announcement response with an authenticated author-photo URL, seed one announcement with a pinned local image through the current attachment shape, and centralize blurred-backdrop/contained-foreground rendering in the existing shared image components. The normal database seed must not make an outbound Pexels request; Pexels is only the one-time source for checked-in demo assets.

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit 12, Laravel filesystem, StoredObjectStorage, MySQL, Next.js 16/React 19, Vitest, Expo/React Native.

---

## Scope and decisions

- Existing users remain keyed by their stable emails in backend/database/seeders/AdminUserSeeder.php:
  - admin@nutriscope.local
  - rnd@nutriscope.local
  - fss@nutriscope.local
- No migration is needed. The current schema already has users.profile_photo_stored_object_id and the stored_objects table.
- Do not put a Pexels URL or a base64 data URL in users.profile_photo. The current application expects profile images to be private stored objects and returns /api/auth/profile-photo through UserResource.
- Download three square, non-sensitive demo portraits from Pexels once, resize/crop them locally to roughly 512x512, and keep each file at or below the backend profile limit of 300,000 bytes.
- Record each Pexels photo page, photographer, and photo ID beside the asset or in the seeder source. Attribution is not required by the Pexels license, but retaining provenance is useful and the Pexels API guidance recommends crediting the photographer when possible.
- A rerun must not replace a photo that a real user already uploaded. It must also avoid creating duplicate stored_objects rows or orphaned private files.

## Approved image-presentation decisions

- Use a blurred-backdrop presentation for normal uploaded photos: the same image fills the frame as a darkened blur while a sharp foreground copy uses `object-contain`/`resizeMode="contain"`. The complete foreground image remains visible and is never stretched.
- Announcement feed media uses one consistent responsive frame instead of growing to the source image's natural height:
  - Web: full card width with a height clamped to approximately 180px on narrow phones and 420px on large layouts.
  - Mobile: compute the same bounded frame from the available card width, with a 16:9 target and approximately 180px minimum / 360px maximum.
  - The frame, not the source image, controls layout size. Portrait, landscape, and square photos therefore occupy equal visual weight.
- A detail/full viewer may be larger, but it remains bounded by the viewport, uses the same blur-plus-contain rule, and never pushes controls off screen.
- Upload previews use the same bounded frame so the composer accurately previews the published result.
- Circular avatars remain cropped display surfaces. Profile selection opens a circular crop editor where the user can drag/pan and zoom without stretching the source; confirmation exports a square image and cancellation preserves the old photo.
- Use one focused maintained crop dependency such as `react-easy-crop` only after verifying its current React 19/Next.js compatibility from official package metadata. Do not build custom pointer/canvas controls unless compatibility verification fails.
- Every announcement author renders the author's actual stored profile photo when present. Initials are only the no-photo or image-load-error fallback.
- The existing announcement response does not currently expose an author photo and `/api/auth/profile-photo` only serves the signed-in user's own photo. Add an announcement-UUID-bound authenticated author-photo path, enforce the same visibility rules as the announcement itself, and return that URL as `author.profile_photo`; do not expose storage keys or numeric IDs or create a general user-photo directory endpoint.
- Apply the author-photo contract to the web announcement board, RND dashboard feed/detail, FSS mobile announcement list/detail, and the FSS mobile dashboard announcement card.
- At least one seeded announcement must contain a real local demo image. Read the pinned asset offline and serialize it into the current announcement attachment data shape; do not introduce a second announcement attachment subsystem as part of this work.
- Decorative backgrounds, logos, QR codes, icons, and layout graphics keep their current rendering behavior.

## File map

- Create backend/database/seeders/ProfilePhotoSeeder.php — idempotent demo-photo linking and private storage.
- Create backend/database/seeders/assets/profile/admin.jpg — pinned Pexels asset for the Admin demo account.
- Create backend/database/seeders/assets/profile/rnd.jpg — pinned Pexels asset for the RND demo account.
- Create backend/database/seeders/assets/profile/fss.jpg — pinned Pexels asset for the FSS demo account.
- Modify backend/database/seeders/DatabaseSeeder.php — call ProfilePhotoSeeder immediately after AdminUserSeeder.
- Create backend/tests/Feature/ProfilePhotoSeederTest.php — private storage, idempotence, preservation, endpoint, and audit-noise coverage.
- Modify backend/tests/Unit/SeederIntegrityTest.php — verify seeder ordering and local-asset/no-runtime-download boundaries if that contract is not better covered by the feature test.
- Create backend/database/seeders/assets/announcements/case-conference.jpg — one pinned, demo-safe announcement photo with recorded Pexels provenance.
- Modify backend/database/seeders/AnnouncementSeeder.php — attach the local photo to one stable seeded announcement without network access or duplicate rows.
- Modify backend/app/Http/Resources/AnnouncementResource.php — include `author.profile_photo` using a UUID-based authenticated application URL.
- Add the smallest announcement route/controller method needed for authenticated author-photo streaming; reuse StoredObjectStorage and current announcement visibility/authorization behavior.
- Modify frontend/components/ui/ImageUploadGallery.tsx — shared bounded blurred-backdrop media and fitted foreground behavior.
- Create one focused profile crop component beside the existing profile UI; modify frontend/components/profile/ProfilePageShell.tsx to open it before saving.
- Modify frontend/components/announcements/AnnouncementsBoard.tsx and frontend/app/(rnd)/dashboard/page.tsx — actual author avatars and bounded media.
- Modify frontend/services/announcementService.ts — add `author.profile_photo` to the public UUID contract.
- Modify mobile/components/AnnouncementsScreen.tsx and mobile/app/(tabs)/index.tsx — actual author avatars, attachment rendering, and bounded responsive media.
- Add focused backend, frontend, and mobile tests for seeding, author-photo authorization, crop cancellation/output, fallback avatars, aspect preservation, and responsive frame bounds.

## Task 1: Write the failing seeder and storage tests

**Files:**
- Create: backend/tests/Feature/ProfilePhotoSeederTest.php
- Modify: backend/tests/Unit/SeederIntegrityTest.php

- [ ] **Step 1: Add the private-storage happy-path test.**

Create the PHPUnit test class with RefreshDatabase, fake the private_uploads disk, seed AdminUserSeeder, then seed ProfilePhotoSeeder. Assert that all three stable demo users have a non-null profile_photo_stored_object_id, each related object has purpose = profile, each object exists on the fake private disk, and the stored byte count matches the disk byte count.

The test should use the existing relationship:

~~~
$object = $user->profilePhotoObject;

$this->assertNotNull($object);
$this->assertSame('profile', $object->purpose);
$this->assertContains($object->mime_type, ['image/jpeg', 'image/png', 'image/webp']);
Storage::disk('private_uploads')->assertExists($object->object_key);
$this->assertSame($object->bytes, Storage::disk('private_uploads')->size($object->object_key));
~~~

- [ ] **Step 2: Add the idempotence and manual-photo-preservation tests.**

The first test should snapshot each seeded user’s ID, stored-object ID, object key, and the stored_objects count; run ProfilePhotoSeeder a second time; then assert the snapshot and count are unchanged.

The second test should create a valid object through app(StoredObjectStorage::class)->storeBytes(...), link it to admin@nutriscope.local, run the seeder, and assert that the Admin object ID and bytes are unchanged while the other two accounts receive demo photos.

- [ ] **Step 3: Add the endpoint and audit tests.**

Act as one seeded user and request GET /api/auth/profile-photo. Assert 200, the response content type is an accepted image MIME, and unauthenticated access remains 401. Run the standalone ProfilePhotoSeeder and assert that it does not create an AuditActivity row.

- [ ] **Step 4: Add the DatabaseSeeder ordering contract.**

In SeederIntegrityTest, read database/seeders/DatabaseSeeder.php and assert that it contains ProfilePhotoSeeder::class, that it contains AdminUserSeeder::class, and that the Admin entry occurs before the profile-photo entry. Also assert that ProfilePhotoSeeder.php does not contain Http::, curl_, or a Pexels API call; the regular seed must be offline and deterministic.

- [ ] **Step 5: Run the new tests and confirm the expected red state.**

Run:

~~~powershell
cd backend
php artisan test --compact tests/Feature/ProfilePhotoSeederTest.php tests/Unit/SeederIntegrityTest.php
~~~

Expected: FAIL because ProfilePhotoSeeder and its local assets do not exist yet, and DatabaseSeeder does not reference it yet.

- [ ] **Step 6: Commit the tests only.**

~~~powershell
git add backend/tests/Feature/ProfilePhotoSeederTest.php backend/tests/Unit/SeederIntegrityTest.php
git commit -m "test: define seeded profile photo contracts"
~~~

## Task 2: Add the pinned local Pexels asset pack

**Files:**
- Create: backend/database/seeders/assets/profile/admin.jpg
- Create: backend/database/seeders/assets/profile/rnd.jpg
- Create: backend/database/seeders/assets/profile/fss.jpg

- [ ] **Step 1: Select and download the three source photos once.**

Use the Pexels photo pages, not hot-linked image URLs, and choose images that are suitable for fictional demo accounts. Avoid medical, distressed, or otherwise sensitive contexts. Candidate portrait pages include:

- Portrait of a Smiling Woman — https://www.pexels.com/photo/portrait-of-a-smiling-woman-7780946/
- Portrait of Smiling Woman — https://www.pexels.com/photo/portrait-of-smiling-woman-18029647/
- Portrait of Smiling Woman — https://www.pexels.com/photo/portrait-of-smiling-woman-20603347/

During implementation, record the final photo-page URL, photo ID, photographer name, and photographer page for each selected file. Do not store a Pexels API key in the repository.

- [ ] **Step 2: Normalize the asset files before committing them.**

Crop each image to a square, resize to no more than 512x512, remove EXIF metadata where the chosen image tool permits, and verify all three files are JPEG, PNG, or WebP and no larger than 300,000 bytes. The runtime seeder will still pass the bytes through ImageNormalizer via StoredObjectStorage, so the assets follow the same validation path as user uploads.

- [ ] **Step 3: Verify the asset pack without changing application code.**

Run a read-only check that each file exists, is non-empty, is within the byte limit, and is recognized by PHP’s image inspection functions. Confirm no source image contains a watermark or implies that the pictured person endorses NutriScope.

- [ ] **Step 4: Commit the asset pack separately.**

~~~powershell
git add backend/database/seeders/assets/profile
git commit -m "chore: add demo profile photo assets"
~~~

## Task 3: Implement the idempotent private-object seeder

**Files:**
- Create: backend/database/seeders/ProfilePhotoSeeder.php

- [ ] **Step 1: Add the stable account-to-asset map.**

Use the three existing account emails as keys and admin.jpg, rnd.jpg, and fss.jpg as asset names. Keep the Pexels provenance in the map metadata or an adjacent machine-readable attribution file. The seeder must never identify an account by display name.

- [ ] **Step 2: Implement the offline seeding flow.**

The implementation should follow this structure:

~~~php
public function run(): void
{
    activity()->withoutLogs(fn (): void => $this->seedPhotos());
}

private function seedPhotos(): void
{
    foreach (self::DEMO_PHOTOS as $email => $photo) {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->command->warn("ProfilePhotoSeeder: {$email} was not found.");
            continue;
        }

        if ($user->profile_photo_stored_object_id !== null || $user->profile_photo !== null) {
            continue;
        }

        $path = database_path('seeders/assets/profile/'.$photo['asset']);
        if (! is_file($path)) {
            throw new RuntimeException("Profile photo asset is missing: {$path}");
        }

        $bytes = file_get_contents($path);
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException("Profile photo asset is unreadable: {$path}");
        }

        $object = null;
        try {
            $object = app(StoredObjectStorage::class)->storeBytes(
                $bytes,
                mime_content_type($path) ?: 'image/jpeg',
                pathinfo($path, PATHINFO_EXTENSION),
                'profile',
                $photo['original_name'],
            );

            $user->forceFill([
                'profile_photo' => null,
                'profile_photo_stored_object_id' => $object->id,
            ])->save();
        } catch (Throwable $exception) {
            if ($object !== null) {
                app(StoredObjectStorage::class)->deleteOrQueue($object);
            }

            throw $exception;
        }
    }
}
~~~

Use the project’s existing PHP conventions and imports. storeBytes() is important: it normalizes the image, writes it to the configured private disk, verifies size, calculates SHA-256 metadata, and creates the StoredObject row. Do not call Storage::disk('public'), Storage::url(), or write image bytes directly into users.profile_photo.

- [ ] **Step 3: Run the focused tests and confirm green.**

Run:

~~~powershell
cd backend
php artisan test --compact tests/Feature/ProfilePhotoSeederTest.php tests/Unit/SeederIntegrityTest.php
~~~

Expected: PASS, including private storage, endpoint authorization, idempotence, preservation of an existing manual photo, and zero audit noise.

- [ ] **Step 4: Commit the seeder.**

~~~powershell
git add backend/database/seeders/ProfilePhotoSeeder.php
git commit -m "feat: seed private demo profile photos"
~~~

## Task 4: Wire the seeder into normal database setup

**Files:**
- Modify: backend/database/seeders/DatabaseSeeder.php

- [ ] **Step 1: Add the call directly after AdminUserSeeder.**

Use this order:

~~~php
$this->call([
    AdminUserSeeder::class,
    ProfilePhotoSeeder::class,
    AiUsageLimitSeeder::class,
    // remaining existing seeders unchanged
]);
~~~

This guarantees that the user rows exist before the foreign-key reference is created.

- [ ] **Step 2: Run the complete seeded development flow twice.**

For a disposable development database, run:

~~~powershell
cd backend
php artisan migrate:fresh --seed
php artisan db:seed
~~~

Verify after both runs that there are exactly three demo profile-photo references, no duplicate objects were created, the private files exist, and manually uploaded photos in a non-fresh database remain unchanged. Do not use migrate:fresh against a shared or production database.

- [ ] **Step 3: Run the final verification gate.**

~~~powershell
cd backend
php artisan test --compact tests/Feature/ProfilePhotoSeederTest.php tests/Feature/PersonNameSeederTest.php tests/Unit/SeederIntegrityTest.php
vendor/bin/pint --dirty --format agent
git diff --check
~~~

Expected: all focused tests pass, Pint reports no remaining PHP formatting changes, and git diff --check is clean.

- [ ] **Step 4: Commit only the implementation files.**

~~~powershell
git add backend/database/seeders/DatabaseSeeder.php backend/tests/Feature/ProfilePhotoSeederTest.php backend/tests/Unit/SeederIntegrityTest.php backend/database/seeders/ProfilePhotoSeeder.php backend/database/seeders/assets/profile
git commit -m "feat: seed demo users with private photos"
~~~

## Task 5: Seed one announcement with a local photo and expose author photos

**Files:**
- Modify: backend/database/seeders/AnnouncementSeeder.php
- Create: backend/database/seeders/assets/announcements/case-conference.jpg
- Modify: backend/app/Http/Resources/AnnouncementResource.php
- Modify: the existing auth/profile-photo controller and backend/routes/api.php only as required for UUID-bound author-photo access
- Modify: backend/tests/Feature/DemoSeederCurrentContractTest.php
- Modify or create: focused announcement-resource/profile-photo authorization tests

- [ ] **Step 1: Write failing contracts.**

Assert that normal seeding creates at least one announcement with a decodable image attachment loaded from a repository asset, a rerun keeps the same stable announcement count/content, and AnnouncementResource returns `author.profile_photo` when the author has a stored profile image. Test that the announcement-UUID route is authenticated, applies the same visibility rules as the announcement, streams the correct author's image, returns the current conventional 404/403 outcome for missing, hidden, or no-photo targets, and never reveals an object key or numeric ID.

- [ ] **Step 2: Add and validate the pinned announcement asset.**

Choose a neutral nutrition-team or food-service image from its Pexels photo page. Save one normalized local JPEG/WebP, strip metadata where supported, record photo page/photo ID/photographer provenance beside the existing asset metadata, and verify its MIME, dimensions, byte size, and lack of watermark. Do not hot-link it and do not add a runtime API call.

- [ ] **Step 3: Seed through the current announcement attachment shape.**

Read the local file, convert it to the same data URL accepted by the existing announcement composer, and assign it to one stable `updateOrCreate` row. Keep current public UUID generation and notification/audit behavior unchanged. Do not create a second attachment table or storage service in this task.

- [ ] **Step 4: Add the author-photo response contract.**

Eager-load the author's UUID and profile-photo reference. Return an authenticated announcement-scoped application URL as `author.profile_photo`, or `null` when the author has no photo. Add the smallest streaming route bound by the announcement public UUID, reuse current private storage and image response headers, and apply the same role/visibility query used for announcement viewing. Preserve the existing self-photo endpoint.

- [ ] **Step 5: Run focused backend tests.**

Run the announcement feature/resource tests, profile/private-object tests, demo-seeder contract, and seeder integrity tests. Confirm unauthenticated access fails, each announcement points to the correct author's photo, and the seeded photo survives two seeder runs without network access.

## Task 6: Add circular profile cropping before upload

**Files:**
- Modify: frontend/package.json and lockfile only after official compatibility verification
- Create: one focused crop-dialog component under frontend/components/profile/
- Modify: frontend/components/profile/ProfilePageShell.tsx
- Add: focused Vitest/component tests under frontend/components/profile/

- [ ] **Step 1: Verify dependency compatibility.**

Check the maintained crop package's current official peer dependencies and usage for React 19/Next.js 16. If compatible, install the pinned dependency. If not, stop and reassess before choosing another maintained focused package; do not silently build a custom crop engine.

- [ ] **Step 2: Write failing interaction tests.**

Cover opening after valid file selection, circular crop frame, drag/pan, accessible zoom control, confirm, cancel, Escape/close behavior, portrait/landscape/square sources, and validation/export failure. Assert cancel/failure leaves the existing photo unchanged.

- [ ] **Step 3: Export a square without distortion.**

Use the crop library's pixel crop result and a small canvas export helper to create a square JPEG/WebP data URL within existing limits. Preserve source proportions, apply no stretch, revoke temporary object URLs, and pass only the confirmed crop to the existing profile update endpoint/private storage pipeline.

- [ ] **Step 4: Verify accessibility and responsive behavior.**

Keep visible labels for zoom and actions, keyboard-operable controls, focus containment/restoration through the existing dialog pattern, and a crop area that fits narrow phones without hiding Confirm/Cancel.

## Task 7: Implement one bounded blurred-backdrop media presentation

**Files:**
- Modify: frontend/components/ui/ImageUploadGallery.tsx
- Modify: frontend/components/announcements/AnnouncementsBoard.tsx
- Modify: frontend/app/(rnd)/dashboard/page.tsx
- Replace the two duplicated clinical image viewer bodies with the shared fitted viewer where compatible
- Modify: procurement image consumers only where the shared component does not already cover them
- Add: focused frontend visual-contract/component tests

- [ ] **Step 1: Write failing presentation tests.**

Assert that normal uploaded-image frames contain a blurred/darkened cover background and a sharp contained foreground, have explicit responsive height bounds, and preserve meaningful alt text. Assert that avatar, logo, QR, icon, and decorative-background variants do not receive the normal-photo frame.

- [ ] **Step 2: Centralize the media frame.**

Extend the existing shared gallery/carousel rather than creating module-specific viewers. Use an absolutely positioned blurred `object-cover` backdrop with a dark overlay and a foreground `object-contain` image. Hide the decorative duplicate from accessibility and keep only the foreground alt text exposed.

- [ ] **Step 3: Enforce predictable announcement sizing.**

For web feed/composer cards, use full available width and `clamp(180px, 52vw, 420px)` (or the closest Tailwind/CSS expression supported by the current toolchain). The card width remains constrained by its existing layout. For detail view, cap media to the available viewport height so headers, navigation, and close controls remain reachable. Verify at 320px, 375px, 768px, and desktop widths.

- [ ] **Step 4: Render real author avatars.**

Add a small reusable avatar rendering branch that uses `author.profile_photo`; switch to initials only when the field is null or the image fires an error. Apply it to announcement feed and detail surfaces in both AnnouncementsBoard and the RND dashboard. Do not replace unrelated account/navigation avatar behavior.

- [ ] **Step 5: Verify all web image surfaces.**

Check announcements, dashboard posts, upload previews, receipt/proof previews and viewers, and clinical document viewers with portrait, landscape, square, very small, and maximum-allowed images. Confirm no normal viewer crops or stretches the sharp foreground and no frame consumes the full page unexpectedly.

## Task 8: Bring FSS mobile announcements to the same contract

**Files:**
- Modify: mobile/components/AnnouncementsScreen.tsx
- Modify: mobile/app/(tabs)/index.tsx
- Add or modify: focused mobile contract/component tests

- [ ] **Step 1: Extend mobile types and failing tests.**

Add `attachments`/`attachment` and `author.profile_photo` to the API type used by both announcement surfaces. Tests must require actual avatars when available, initials fallback, attached media in feed/detail, blur-plus-contain rendering, and consistent frame bounds.

- [ ] **Step 2: Add responsive mobile sizing.**

Use the available card width and a 16:9 target to calculate a stable frame, clamped to approximately 180px minimum and 360px maximum. Recalculate on orientation/window changes. Use `resizeMode="cover"` for the blurred background and `resizeMode="contain"` for the sharp foreground.

- [ ] **Step 3: Preserve usable detail navigation.**

Keep photo content inside the existing scrollable detail modal, cap it below the usable window height, and ensure close/navigation controls remain visible. Provide accessibility labels for the foreground image and hide the decorative blur copy from screen readers.

- [ ] **Step 4: Run web/mobile verification.**

Run focused and full frontend tests, TypeScript, ESLint, and production build. Run mobile TypeScript and affected tests. Manually inspect responsive widths and orientation changes during browser/device acceptance; source-image dimensions must never determine card height.

## Optional Task 9: Add a one-time Pexels refresh command

This is optional and should not be part of the normal db:seed path. If the team wants the asset pack refreshed automatically, add backend/app/Console/Commands/RefreshDemoProfilePhotos.php with pinned Pexels photo IDs and a PEXELS_API_KEY environment value. Use Laravel’s HTTP client, fake it in tests, validate the returned download MIME and byte limit, and write only to backend/database/seeders/assets/profile/. The regular seeder should continue consuming local files so tests and deployments remain deterministic and do not require outbound internet access.

The Pexels API requires authorization and documents rate limits; API use should include a visible Pexels link and photographer credit. The simplest reliable automation is therefore: download once from Pexels, commit the approved assets with provenance, and let every normal seed run load those local files.

## Self-review checklist

- The plan covers account lookup, local assets, private object storage, API response behavior, idempotence, manual-photo preservation, audit suppression, seeder ordering, cropping, author-photo authorization, announcement imagery, responsive bounds, web/mobile rendering, and verification.
- No migration is expected. The existing self-photo stream remains, while a narrow announcement-scoped authenticated stream is planned because announcements currently cannot resolve another author's stored photo.
- Announcement photos remain in the current announcement attachment data shape for this scoped work; do not introduce a parallel storage subsystem without a separate current-code security decision.
- Feed frames have stable responsive dimensions independent of source-image dimensions; full/detail viewers remain viewport-bounded.
- Blurred background copies are decorative; sharp contained copies retain meaningful accessibility text.
- No runtime Pexels dependency is introduced in the recommended implementation.
- The optional API command is isolated so a missing Pexels key cannot break ordinary development, tests, or deployment seeding.
