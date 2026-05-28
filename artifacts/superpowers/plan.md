## Goal
Apply all required fixes from the Milestone 1 auth UI review (review.md) in order: B-1, B-2, M-3, m-1, m-3. Verify login/logout flows work correctly after all changes.

## Plan

### Step 1: Fix B-1 — Logout cookie + redirect hardening
- **Files**: `frontend/app/api/auth/logout/route.ts`, `frontend/components/layout/TopBar.tsx`
- **Change**: Logout API route always deletes cookie before calling Laravel (try/catch around backend call). TopBar uses `router.replace` and redirects on error too.
- **Verify**: Read both files to confirm correct structure.

### Step 2: Fix B-2 — Remove root `/` middleware bypass
- **Files**: `frontend/middleware.ts`
- **Change**: Remove `pathname === "/"` from `isInternalOrStatic` check.
- **Verify**: Read middleware.ts to confirm only `_next`, `/api`, and file extensions are excluded.

### Step 3: Fix M-3 — Remove unused `NEXT_PUBLIC_API_URL`
- **Files**: `frontend/.env.local`
- **Change**: Delete the `NEXT_PUBLIC_API_URL` line.
- **Verify**: Read .env.local to confirm only `LARAVEL_API_URL` remains.

### Step 4: Fix m-1 — Split loading vs initializing in AuthContext
- **Files**: `frontend/contexts/AuthContext.tsx`, `frontend/app/(rnd)/layout.tsx`, `frontend/app/login/page.tsx`
- **Change**: Add `initializing` state (starts true, set false after first `refreshUser`). `loading` starts false, only set during login/logout actions. Export `initializing`. RND layout uses `initializing` for loading screen. Login page uses only `loading` for button state.
- **Verify**: Read all three files to confirm correct state usage.

### Step 5: Fix m-3 — Add unique IDs to login form elements
- **Files**: `frontend/app/login/page.tsx`
- **Change**: Add `id="login-form"` to form, `id="login-submit"` to submit button, `id="login-error"` to error container.
- **Verify**: Read login page to confirm IDs present.

### Step 6: Verify — Build check
- **Command**: `cd frontend && npx next build` (or `npm run build`)
- **Verify**: Build succeeds with no errors.

## Risks & mitigations
- **Risk**: Splitting `loading`/`initializing` could break the RND layout loading guard.
- **Mitigation**: RND layout switches from `loading` to `initializing` for its conditional render.

## Rollback plan
```bash
git checkout -- frontend/app/api/auth/logout/route.ts frontend/components/layout/TopBar.tsx frontend/middleware.ts frontend/contexts/AuthContext.tsx frontend/app/login/page.tsx frontend/app/\(rnd\)/layout.tsx
```
