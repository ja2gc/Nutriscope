# Backup List Hierarchy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Subagents and worktrees are prohibited for this repository task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace confusing duplicate tab rows with clear restore-point navigation, wrapping type filters, conditional activity, accurate recovery history, and protected actions.

**Architecture:** Keep the existing backup API, pagination, state machine, and shared UI components. Extend `Tabs` with an optional equal-width layout, query the existing `in_progress&category=all` view for conditional activity, and fix `BackupRunResource` so frontend actions match backend deletion rules. Update existing Admin Help/FAQ content rather than creating parallel guidance.

**Tech Stack:** Laravel 12, PHPUnit, Next.js 16 client components, React 19, TypeScript, Tailwind CSS, Vitest.

---

### Task 1: Align protected pre-restore actions

**Files:**
- Modify: `backend/tests/Feature/Backup/AdminBackupApiTest.php`
- Modify: `backend/app/Http/Resources/BackupRunResource.php`

- [ ] **Step 1: Extend the failing safety-snapshot API test**

List the unexpired pre-restore backup and assert the resource does not advertise deletion:

```php
$this->actingAs($admin, 'sanctum')
    ->getJson('/api/admin/backups?section=available&category=safety')
    ->assertOk()
    ->assertJsonPath('data.0.id', $safety->uuid)
    ->assertJsonPath('data.0.actions.can_delete', false);
```

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
php artisan test tests/Feature/Backup/AdminBackupApiTest.php --filter=unexpired_safety_snapshot
```

Expected: FAIL because current resource returns `can_delete: true` for an older protected snapshot.

- [ ] **Step 3: Make resource and controller deletion rules agree**

In `BackupRunResource::toArray()`, calculate the already-loaded protection facts without adding per-row queries:

```php
$isProtectedSafetySnapshot = $this->source->value === 'safety'
    && $this->retention_expires_at?->isFuture() === true;
```

Require `! $isProtectedSafetySnapshot` in completed-backup `can_delete`. Preserve failed-record deletion, latest-verified protection, and active-recovery protection.

- [ ] **Step 4: Run focused test and verify GREEN**

Run the same command. Expected: PASS.

### Task 2: Restructure navigation and conditional activity

**Files:**
- Modify: `frontend/app/admin/backups/backup-page-contract.test.ts`
- Modify: `frontend/components/ui/Tabs.tsx`
- Modify: `frontend/types/backup.ts`
- Modify: `frontend/services/backupService.ts`
- Modify: `frontend/app/admin/backups/page.tsx`

- [ ] **Step 1: Write failing hierarchy contracts**

Assert:

```ts
expect(page).toContain('ariaLabel="Backup views"');
expect(page).toContain('Restore points');
expect(page).toContain('Backup activity');
expect(page).toContain('Restoration activity');
expect(page).toContain('Filter by backup type');
expect(page).toContain('Pre-restore');
expect(page).not.toContain('className="overflow-x-auto"');
expect(page).not.toContain('ariaLabel="Backup category"');
expect(tabs).toContain('fill = false');
```

Keep assertions for shared `Tabs`, existing `Pagination`, all five filters, and ten-item pagination.

- [ ] **Step 2: Run contract test and verify RED**

Run:

```bash
npm test -- app/admin/backups/backup-page-contract.test.ts
```

Expected: FAIL on missing hierarchy, activity, and `Tabs` fill support.

- [ ] **Step 3: Add equal-width support to shared Tabs**

Add optional `fill?: boolean`, default false. With `fill`, retain tab semantics and arrow navigation while applying equal-width, centered buttons:

```tsx
className={`flex border-b border-warm-200 ${className}`}
```

and per item:

```tsx
${fill ? "min-w-0 flex-1 justify-center px-3" : "px-5"}
```

No new tab component or font.

- [ ] **Step 4: Permit the existing all-category query**

Add:

```ts
export type BackupCategoryFilter = BackupCategory | "all";
```

Use `BackupCategoryFilter` only in `listBackups`; visible user filters remain the five concrete backup types.

- [ ] **Step 5: Load active backup work separately**

In `BackupsPage`, keep primary state limited to:

```ts
type BackupView = Exclude<BackupSection, "in_progress">;
const [section, setSection] = useState<BackupView>("available");
const [activeBackups, setActiveBackups] = useState<BackupRunDto[]>([]);
```

Load `listBackups(1, "in_progress", "all")` with the existing list and schedule request. Set `hasActive` from active rows and retain five-second polling. After manual creation, refresh instead of navigating to a permanent In progress tab.

- [ ] **Step 6: Render approved hierarchy**

Use existing `Tabs` with `fill` for **Restore points**, **Failed**, and **Recently deleted**. Render a labelled wrapping group of existing `Button` controls for Daily, Weekly, Monthly, Manual, and Pre-restore. Keep `aria-pressed`, visible focus, minimum target size, and page reset behavior.

When `activeBackups.length > 0`, render **Backup activity** above primary navigation using existing `Card` and `BackupList`. Render **Restoration activity** when the current restore point has a non-terminal recovery state. Do not create a second service or endpoint.

- [ ] **Step 7: Run focused frontend contract and verify GREEN**

Run the Task 2 test command. Expected: PASS.

### Task 3: Remove redundant state badges and clarify recovery history

**Files:**
- Modify: `frontend/app/admin/backups/backup-page-contract.test.ts`
- Modify: `frontend/components/backups/BackupList.tsx`

- [ ] **Step 1: Write failing copy contracts**

Assert completed rows no longer use `labels[backup.state]`, while failure and active stages remain explicit:

```ts
expect(list).not.toContain('completed: "Completed"');
expect(list).toContain('Used for system restore');
expect(list).toContain('Restore attempt failed');
expect(list).toContain('Pre-restore backup protected until');
```

- [ ] **Step 2: Run contract and verify RED**

Run the focused Vitest command. Expected: FAIL on old badge and recovery copy.

- [ ] **Step 3: Implement minimal row presentation**

- Available rows: no Completed badge.
- Active rows: retain Queued, Creating, or Verifying badge.
- Failed rows: retain Failed badge and safe failure message.
- Completed recovery: show `Used for system restore · Completed {date}` as history text.
- Failed, cancelled, or rolled-back recovery: use explicit restore wording and existing semantic tones.
- Pre-restore rows: show `Pre-restore backup protected until {date}` while protected.
- Recently deleted rows: rely on the view and recovery deadline rather than a duplicate badge.

- [ ] **Step 4: Run focused contract and verify GREEN**

Run the same focused Vitest command. Expected: PASS.

### Task 4: Explain statuses and types in existing Admin Help

**Files:**
- Modify: `frontend/lib/helpContent.test.ts`
- Modify: `frontend/lib/helpContent.ts`

- [ ] **Step 1: Write failing Admin Help assertions**

Add `admin-backup-statuses` to Admin-only expectations and assert searches:

```ts
expect(filterHelpItems("Admin", "pre-restore").map((item) => item.id)).toContain("admin-backup-statuses");
expect(filterHelpItems("Admin", "verifying").map((item) => item.id)).toContain("admin-backup-statuses");
expect(filterHelpItems("RND", "pre-restore")).toEqual([]);
```

- [ ] **Step 2: Run Help test and verify RED**

Run:

```bash
npm test -- lib/helpContent.test.ts
```

Expected: FAIL because status guidance is missing.

- [ ] **Step 3: Add one Admin-only status/type answer**

Explain Restore points, Failed, Recently deleted; Queued, Creating, Verifying; Daily, Weekly, Monthly, Manual, Pre-restore; and restoration history. Replace remaining administrator-facing `Safety` wording with `Pre-restore` where it names the UI category while retaining “safety snapshot” where documentation explains the recovery mechanism.

- [ ] **Step 4: Run focused Help test and verify GREEN**

Run the Task 4 command. Expected: PASS.

### Task 5: Verification, review, commit, deploy, and live acceptance

**Files:** All changed task files.

- [ ] **Step 1: Run backend verification**

```bash
php artisan test tests/Feature/Backup/AdminBackupApiTest.php
php artisan test tests/Feature/Backup tests/Feature/Audit/AuditRetentionTest.php
vendor/bin/pint --test
```

Expected: all pass.

- [ ] **Step 2: Run frontend verification**

```bash
npm test -- app/admin/backups/backup-page-contract.test.ts lib/helpContent.test.ts
npm test
npx tsc --noEmit
npm run lint
npm run build
```

Expected: all pass; only already-known non-failing framework warnings may remain.

- [ ] **Step 3: Self-review and scope check**

Inspect every changed file for redundant controls, hidden actions that backend allows, exposed provider data, missing empty states, broken polling, inconsistent wording, font changes, new abstractions, and unrelated edits. Run:

```bash
git diff --check
git status --short
```

- [ ] **Step 4: Commit task files only**

Use neutral Conventional Commits. Do not stage `.codex/config.toml`, `frontend/AGENTS.md`, or the unrelated profile-photo plan.

- [ ] **Step 5: Push and verify deployment**

Push main only after fresh verification. Confirm GitHub deployment success, public `/up` HTTP 200, and `HEAD == origin/main` locally and on the Droplet.

- [ ] **Step 6: Live browser acceptance**

Verify desktop and narrow viewport:

- no nested horizontal or vertical scrolling;
- three equal primary views;
- five wrapping type filters, including Pre-restore at zero;
- conditional backup and restoration activity;
- no Completed badge in Restore points;
- protected pre-restore rows have no Delete;
- Failed and Recently deleted behavior remains correct;
- pagination and empty states remain visible;
- Admin Help searches `pre-restore` and `verifying` return the new answer.

### Task 6: Reconcile documentation and external storyboard after live acceptance

**Files:**
- Modify when outdated: `docs/FAQ.md`
- Modify when outdated: `docs/ROLE-HOW-TO.md`
- Modify when outdated: `docs/modules/admin.md`
- Modify when outdated: `docs/modules/Flowcharts/Backup and Recovery.md`
- Modify when outdated: `docs/modules/STORYBOARD-SCREENSHOT-GUIDE.md`
- Modify when outdated: `docs/operations/backup-recovery.md`
- Modify when outdated: `docs/operations/platform-requirements.md`
- Modify when outdated: `C:\Users\jared\Documents\Storyboarding\Admin Web Console Video Storyboard.md`

- [ ] **Step 1: Search current guidance after UI acceptance**

Search for `Safety`, `In progress`, `Available`, `Completed`, backup tabs, status tags, and deletion wording. Keep still-valid technical references to a safety snapshot; change only administrator-facing category/navigation copy that no longer matches the live page.

- [ ] **Step 2: Update existing documents only**

Document Restore points, Failed, Recently deleted, conditional Backup activity, restoration activity/history, and Pre-restore. Update the existing Admin storyboard scene instead of adding another scene or storyboard file.

- [ ] **Step 3: Verify documentation consistency**

Confirm Markdown fences balance, no placeholders exist, the flowchart retains all valid recovery safeguards, and Help/FAQ/storyboard wording agrees with live behavior.

- [ ] **Step 4: Commit and push documentation only**

Stage only changed documentation/storyboard files. Re-run `git diff --check`, push main, verify deployment if repository documentation changes trigger it, and prove final Git parity.
