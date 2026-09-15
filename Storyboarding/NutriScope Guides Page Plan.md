# NutriScope Guides Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` or `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Add a simple, role-specific **Guides** tab inside Help for RND, FSS, and Admin using written instructions and optional Google Drive videos, without storing video files in NutriScope or adding a video database. FSS accomplishment guidance must describe two numeric fields, five checkbox duties, and semi-monthly reports.

**Architecture:** Keep the recording scripts in the repository-root canonical `Storyboarding/` folder. Convert only their user actions and expected results into concise in-app guides. RND and Admin guide content belongs to the website; FSS guide content belongs to the Android app. Each video remains in Google Drive and NutriScope stores only its stable view link in the appropriate guide content file.

**Tech stack:** Existing Next.js Help page, existing Expo/React Native Help screen, static TypeScript guide content, Google Drive view-only video links, and existing role authentication.

---

## Final Name and Location

Use **Guides** as the tab name under **Help**.

- RND Help: **FAQ | Guides**
- FSS Help: **FAQ | Guides**
- Admin Help: **FAQ | Guides** using the verified Admin storyboard.

Do not use **Storyboards** as the user-facing name. A storyboard is a production document for recording. A guide is the finished instruction the user reads or watches.

Role selection is automatic from the signed-in account. Do not add a role selector or a “Who this guide is for” section.

## What Users See

The Guides tab should remain useful even when no video is available.

1. Show one short sentence: **Follow these guides in order when learning the complete workflow, or open only the task you need.**
2. Show a compact **Overall workflow** using the guide titles in their required order.
3. Show one card per guide with:
   - guide number;
   - plain-language title;
   - one-sentence purpose;
   - **Open guide** action;
   - **Watch video** action only when a real Google Drive URL exists.
4. Open a guide on a separate page, not a modal.
5. The detail page contains only:
   - **Before you start**, when another guide must be completed first;
   - numbered instructions using the real page and button labels;
   - **What should happen**;
   - optional **Watch video** button;
   - normal Back action.
6. A dependency must name and link to the exact guide, for example: **Refer to Guide 2 — Create a Recipe and Its Standard Serving Size.**

Do not expose recording setup, camera directions, narration text, demo-data cleanup, or technical verification notes in the user-facing Guides tab.

## Content Source

- RND Guides come from `RND Food Service Operations Video Storyboard.md` Scenes 1–10.
- FSS Guides come from `FSS Mobile App Video Storyboard.md` Scenes 1–11.
- Admin Guides come from `Admin Web Console Video Storyboard.md` Scenes 1–11.
- Scene order is the full learning sequence.
- Optional exception captures may be converted into short troubleshooting notes only when they add information that is not already clear in FAQ.
- The current application remains the source of truth. Recheck visible labels and role permissions before publishing or changing a guide.
- FSS Daily Log uses the exact numeric labels **Collected diet list from different wards.** and **Apportioned and distributed food to in patient in the different wards.**, five checkbox duties, past-date entry with **Today** reset, and **Off duty** with report `X`.
- FSS reports currently use day 1–15 or day 16–month-end periods with separate **View PDF** and **Download PDF** actions.

## Video Storage and Updates

1. Record with demo data only. Do not expose real patient, account, receipt, or financial information.
2. Upload one finished video per guide to Google Drive.
3. Set each file to view-only access suitable for the intended users.
4. Copy the stable viewing URL into that guide's optional `videoUrl` field.
5. Hide **Watch video** when `videoUrl` is absent; never show a disabled or broken placeholder button.
6. To correct an existing video without changing its link, upload the replacement through Google Drive's file-version management. The NutriScope link can remain unchanged.
7. A completely new guide or a changed in-app instruction requires the normal website deployment or mobile app release for the affected role.

NutriScope does not upload, stream, or store the video file. The database remains unchanged.

## Minimal Data Shape

Use the same small structure in each client while keeping the actual role content in the client that displays it:

```ts
type Guide = {
  id: string;
  order: number;
  title: string;
  purpose: string;
  prerequisites: Array<{ guideId: string; label: string }>;
  steps: string[];
  expectedResult: string;
  videoUrl?: string;
};
```

Role content remains separate and is selected from the signed-in role. RND and Admin reuse the website guide components; FSS uses the mobile components. This avoids adding a backend endpoint or database only to serve static training text.

## Files for the Future Implementation

### RND website

- Create `frontend/lib/guideContent.ts` for verified RND and Admin guide content and dependency lookup.
- Create `frontend/components/help/GuideList.tsx` for the ordered compact cards.
- Create `frontend/components/help/GuidePage.tsx` for one guide's steps and optional video action.
- Modify `frontend/components/help/HelpPage.tsx` to show role-scoped **FAQ | Guides** for RND and Admin.
- Create `frontend/app/(rnd)/help/guides/[guideId]/page.tsx` for a separate, protected guide page.
- Create `frontend/app/admin/help/guides/[guideId]/page.tsx` for a separate, Admin-protected guide page that reuses the same website guide component.
- Add focused tests beside the current Help tests.

### FSS Android app

- Create `mobile/lib/guideContent.ts` for verified FSS guide content and dependency lookup.
- Create `mobile/components/help/GuideList.tsx` for touch-friendly ordered cards.
- Modify `mobile/app/help.tsx` to show **FAQ | Guides**.
- Create `mobile/app/guide/[guideId].tsx` for a separate guide screen with the standard Back action.
- Add focused tests beside the current mobile Help tests.

Do not add Laravel migrations, models, video-upload endpoints, or Admin video-management screens.

## Implementation Tasks

### Task 1: Add content contracts and tests

- [ ] Add a failing website test proving RND and Admin guide order is unique per role, every dependency resolves within the same role, and only real `https://` video links are accepted.
- [ ] Add the minimal `Guide` type, validation helper, and role-scoped content translated from the RND and Admin storyboards.
- [ ] Add the equivalent failing FSS test and minimal FSS content translated from the FSS storyboard.
- [ ] Confirm no camera direction, narration block, cleanup step, or guessed video URL appears in user content.

### Task 2: Add the RND and Admin Guides tabs

- [ ] Add a failing Help-page contract test for role-scoped **FAQ | Guides** on RND and Admin.
- [ ] Reuse the existing Help page cards, spacing, colors, focus styles, and responsive width.
- [ ] Show ordered guide cards with **Open guide** and an optional **Watch video** link.
- [ ] Add protected RND and Admin guide routes, reuse the same website guide component, and return a normal not-found result for an unknown or cross-role guide ID.
- [ ] Verify keyboard navigation, visible focus, readable heading order, and a 44-pixel minimum action height.

### Task 3: Add the FSS Guides tab

- [ ] Add a failing mobile contract test for **FAQ | Guides**, guide order, and hidden missing-video actions.
- [ ] Reuse the existing Help header, cards, typography, colors, safe-area spacing, and search screen patterns.
- [ ] Add touch-friendly guide cards and the separate Expo Router guide screen.
- [ ] Keep all actions at least 48 dp high and add accessibility roles and labels.
- [ ] Preserve the normal Android Back behavior and the user's prior Help scroll position where practical.

### Task 4: Add verified Drive links

- [ ] Upload only privacy-safe final recordings.
- [ ] Open every sharing link in a signed-out browser to confirm the intended viewer can play it.
- [ ] Add each confirmed URL to the matching role guide.
- [ ] Confirm a guide with no URL still works completely as written instructions.

### Task 5: Verify the complete feature

- [ ] Run the focused web Help and guide tests.
- [ ] Run the focused mobile Help and guide tests.
- [ ] Run web TypeScript/lint checks required by the project.
- [ ] Run mobile TypeScript and test suites sequentially on Windows.
- [ ] Test the RND flow at desktop and narrow mobile browser widths.
- [ ] Test FSS on a small phone, large phone, and tablet in portrait and landscape.
- [ ] Confirm RND, FSS, and Admin can see only their own role's guides.
- [ ] Confirm all cross-guide links open the exact named guide.
- [ ] Confirm no video is stored in or uploaded through NutriScope.

## Acceptance Checklist

- The tab is named **Guides**.
- The signed-in role determines the content automatically.
- The first view is compact and ordered rather than one long wall of instructions.
- Written instructions work without video.
- Every dependency names and links to an exact guide.
- Videos use verified Google Drive view links only.
- Missing video links produce no empty or disabled control.
- Video files and video metadata are not stored in the database.
- Existing FAQ behavior remains available and unchanged.
