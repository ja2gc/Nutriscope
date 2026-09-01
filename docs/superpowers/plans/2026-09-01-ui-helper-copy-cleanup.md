# UI Helper Copy Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove redundant web/mobile helper copy and expose only non-obvious AI token cost behavior through one accessible reusable help affordance.

**Architecture:** Keep this a source-only UI cleanup. Add one web `InfoHint` component on top of the existing Radix Popover wrapper, use it for AI token-cap/cost guidance, remove summary copy elsewhere, and preserve existing safety/permission/data-interpretation guidance. Mobile receives copy removals only, then a version/build/release update.

**Tech Stack:** Next.js 16, React 19, Radix Popover, Vitest/jsdom, Expo/React Native, Node test runner, Expo EAS Android preview builds.

---

### Task 1: Lock copy-removal contracts

**Files:**
- Create: `frontend/app/ui-helper-copy-contract.test.ts`
- Modify: `mobile/lib/mobileNavigation.test.cjs`
- Create: `mobile/lib/ui-helper-copy-contract.test.cjs`

- [ ] **Step 1: Write failing web source-contract tests**

Create a Vitest source contract that reads the audited pages/components, joins their text, and asserts that the approved high-confidence strings and obvious role summaries are absent. Also assert that security, upload, procurement-proof, and generated-draft guidance remains present.

```ts
expect(source).not.toContain("System configuration, active directories");
expect(source).not.toContain("Find answers for");
expect(source).not.toContain("Select all that apply for the");
expect(source).not.toContain("Specific, measurable nutrition goals");
expect(source).toContain("Receipt and proof are required");
expect(source).toContain("Generated text is a draft");
```

- [ ] **Step 2: Write failing mobile source-contract tests**

Update the menu navigation contract and add a dedicated Node contract covering login, help, procurement, prep, menu, and food details.

```js
assert.doesNotMatch(menu, /Read-only weekly plan/);
assert.doesNotMatch(menu, /Actual served population is recorded in Meal Prep/);
assert.doesNotMatch(login, /Food service operations|Secure Connection/);
assert.match(procurement, /Receipt, proof, and reviewed actual values are required/);
```

- [ ] **Step 3: Verify RED**

Run:

```powershell
Set-Location frontend
npm test -- app/ui-helper-copy-contract.test.ts
Set-Location ../mobile
node --test lib/mobileNavigation.test.cjs lib/ui-helper-copy-contract.test.cjs
```

Expected: failures name existing redundant strings.

### Task 2: Add accessible web info affordance

**Files:**
- Create: `frontend/components/ui/InfoHint.tsx`
- Create: `frontend/components/ui/InfoHint.test.tsx`
- Modify: `frontend/app/admin/dashboard/page.tsx`

- [ ] **Step 1: Write failing component test**

Render an `InfoHint`, activate its accessible button, verify the explanatory card appears, then press Escape and verify dismissal.

```tsx
expect(screen.getByRole("button", { name: "How AI token costs are calculated" })).toBeTruthy();
await user.click(screen.getByRole("button", { name: "How AI token costs are calculated" }));
expect(screen.getByText(/input tokens × input rate/i)).toBeTruthy();
await user.keyboard("{Escape}");
expect(screen.queryByText(/input tokens × input rate/i)).toBeNull();
```

- [ ] **Step 2: Verify component test fails**

Run `npm test -- components/ui/InfoHint.test.tsx` from `frontend`.

Expected: fail because `InfoHint` does not exist.

- [ ] **Step 3: Implement minimal component**

Create a client component using existing `Popover`, `PopoverTrigger`, and `PopoverContent`. Trigger is a 44px icon button with `CircleHelp`, visible focus state, and caller-provided `label`. Content is plain text with an optional title; Radix handles focus, Escape, outside-click, and positioning.

```tsx
<Popover>
  <PopoverTrigger asChild>
    <button type="button" aria-label={label} className="inline-flex min-h-11 min-w-11 ...">
      <CircleHelp aria-hidden="true" className="h-4 w-4" />
    </button>
  </PopoverTrigger>
  <PopoverContent className="w-[min(20rem,calc(100vw-2rem))] p-0" sideOffset={6}>
    {title && <p className="font-bold">{title}</p>}
    <p className="text-sm leading-6">{children}</p>
  </PopoverContent>
</Popover>
```

- [ ] **Step 4: Replace AI summary with useful help**

Remove the generic admin dashboard subtitle and the persistent token-cap summary. Place `InfoHint` beside `AI Token Caps` with this content:

```text
Daily and monthly limits stop additional AI requests after total token use reaches the limit. Leave a limit blank for unlimited use. Estimated USD cost = (input tokens ÷ 1,000,000 × configured input rate) + (output tokens ÷ 1,000,000 × configured output rate), then the dashboard converts it using its USD-to-PHP rate.
```

- [ ] **Step 5: Verify GREEN**

Run `npm test -- components/ui/InfoHint.test.tsx app/ui-helper-copy-contract.test.ts` from `frontend`.

Expected: both pass.

### Task 3: Remove redundant web summaries

**Files:**
- Modify audited pages under `frontend/app/(rnd)/dashboard`, `calendar`, `food-service`, `food-library`, `food-database`, `ncp`, `notifications`, `settings`, and `profile`
- Modify audited pages under `frontend/app/admin`
- Modify `frontend/app/login/page.tsx`
- Modify `frontend/app/mobile-app/page.tsx`
- Modify shared components under `frontend/components/help`, `reports`, `announcements`, `foodservice`, and `mobile-app`

- [ ] **Step 1: Remove page/title/button/role restatements**

Delete the approved high-confidence copy and context-dependent summaries. Preserve surrounding layout and actions. Remove empty wrapper elements left by deleted text.

- [ ] **Step 2: Keep or tighten useful behavior guidance**

Keep profile recovery-email behavior, destructive recovery warnings, upload constraints, procurement proof requirements, chart legends, generated-draft warnings, and menu-slot calculation semantics. Tighten useful notes only where needed:

```text
Descriptions and contacts appear on procurement reports.
Starting a new cycle does not change prior ADIME records.
Pinned announcements appear first.
```

- [ ] **Step 3: Preserve unrelated pagination edits**

Review every touched dirty file against `git diff` and keep existing pagination/quick-action changes intact.

- [ ] **Step 4: Verify web contract**

Run `npm test -- app/ui-helper-copy-contract.test.ts` from `frontend`.

Expected: pass with all approved strings absent and protected guidance present.

### Task 4: Remove redundant mobile summaries

**Files:**
- Modify: `mobile/app/login.tsx`
- Modify: `mobile/app/help.tsx`
- Modify: `mobile/app/(tabs)/procurement.tsx`
- Modify: `mobile/app/(tabs)/prep.tsx`
- Modify: `mobile/app/(tabs)/menu.tsx`
- Modify: `mobile/app/food-details.tsx`

- [ ] **Step 1: Remove obvious labels and summaries**

Delete mobile role/purpose subtitles, connection-status marketing copy, procurement/prep summaries, `Read-only weekly plan`, the actual-served cross-page note, and the FSS read-only suffix. Preserve accessibility labels and operational warnings.

- [ ] **Step 2: Verify mobile contracts**

Run:

```powershell
node --test lib/mobileNavigation.test.cjs lib/ui-helper-copy-contract.test.cjs
npx tsc --noEmit
```

Expected: all pass with no TypeScript errors.

### Task 5: Full verification and rendered inspection

**Files:**
- No planned source changes unless verification finds a scoped defect

- [ ] **Step 1: Run frontend verification**

Run from `frontend`:

```powershell
npm test
npm run lint
npm run build
```

Expected: zero test failures, zero lint errors, production build exit code 0.

- [ ] **Step 2: Inspect rendered web pages**

Start the local app with existing services, open representative public/RND/admin pages, and verify no empty gaps, broken headings, inaccessible help button, console errors, or unexpected HTTP 4xx/5xx responses. Check desktop and narrow viewport.

- [ ] **Step 3: Inspect mobile source/render risk**

Use Expo/React Native checks available locally and verify edited JSX trees remain valid. If a local runtime is available, inspect login, help, procurement, prep, menu, and food detail screens.

- [ ] **Step 4: Self-review task diff**

Run `git diff --check`, inspect task files line-by-line, and confirm unrelated files/hunks are excluded from staging.

### Task 6: Version, build, publish, and deliver Android release

**Files:**
- Modify: `mobile/app.json`
- Modify: `mobile/release.json`

- [ ] **Step 1: Bump release metadata**

Change Expo version from `1.2.5` to `1.2.6` and Android version code from `9` to `10`.

- [ ] **Step 2: Build signed preview APK from tested local source**

From `mobile`, run:

```powershell
npx eas-cli@latest build -p android --profile preview --non-interactive --wait --json
```

Record completed build ID and artifact URL.

- [ ] **Step 3: Download and hash APK**

Download the artifact to a task-specific temporary path and run `Get-FileHash -Algorithm SHA256`. Update `mobile/release.json` with version `1.2.6`, code `10`, artifact URL, and lowercase hash.

- [ ] **Step 4: Commit and push complete tested release**

Stage only the spec, plan, contract tests, UI component, audited copy files, mobile version files, and completed `mobile/release.json`. Commit with a task-only Conventional Commit message. Push `main` once, only after local checks and the EAS build/hash pass.

- [ ] **Step 5: Wait for release publication**

After the single complete-release push, wait for the `Publish FSS Android release` workflow to finish. Do not report delivery from EAS completion alone.

- [ ] **Step 6: Verify stable handoff**

Verify:

```text
https://nutriscope.live/mobile-app
https://nutriscope.live/downloads/nutriscope-fss.json
https://nutriscope.live/downloads/nutriscope-fss.apk
```

Require HTTP 200, expected JSON/MIME, version `1.2.6`, code `10`, non-zero APK size, matching SHA-256, and `HEAD == origin/main` before completion.
