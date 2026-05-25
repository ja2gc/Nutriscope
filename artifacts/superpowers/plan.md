## Goal
Implement Milestone 1 Frontend Auth UI, including Next.js API routes for secure HttpOnly cookie storage, an Auth Context, Route Protection Middleware, the Login Page, and the initial RND shell layout. Apply strict clinical UI/UX design rules (structured, high-density, no decorative visual noise).

## Assumptions
- Next.js uses App Router (`frontend/app`).
- The backend API is accessible locally at `http://127.0.0.1:8000/api`.
- Tailwind CSS v4 is configured and will be used for strict 4px/8px grid spacing and clinical color palettes.
- `lucide-react` will be used for clean, professional iconography.

## Plan
1. **Next.js Auth API Routes (Cookie Storage)**
   - **Files**: `frontend/app/api/auth/login/route.ts`, `frontend/app/api/auth/logout/route.ts`, `frontend/app/api/auth/me/route.ts`
   - **Change**: Create Next.js server-side route handlers. `/login` forwards credentials to Laravel, extracts the `token`, and sets an `HttpOnly` cookie via `cookies().set()`. `/me` forwards the cookie as a Bearer token to fetch user data. `/logout` hits Laravel logout and clears the cookie.
   - **Verify**: Syntax check.

2. **Auth Context & Service**
   - **Files**: `frontend/contexts/AuthContext.tsx`, `frontend/services/authService.ts`, `frontend/app/layout.tsx`
   - **Change**: Create `authService.ts` to call the internal Next.js API. Create `AuthContext` to manage `user` state and provide `login`/`logout` actions. Wrap `children` in `app/layout.tsx` with `<AuthProvider>`.
   - **Verify**: Ensure context compiles cleanly without type errors.

3. **Route Protection Middleware**
   - **Files**: `frontend/middleware.ts`
   - **Change**: Intercept requests to protected areas (`/rnd`, `/fss`, `/admin`). Check if the `token` cookie exists. If missing, redirect to `/login`. If the user accesses `/login` while already authenticated, redirect them to their dashboard.
   - **Verify**: Syntax check.

4. **Login Page UI**
   - **Files**: `frontend/app/login/page.tsx`, `frontend/components/ui/Input.tsx`, `frontend/components/ui/Button.tsx`
   - **Change**: Build a clinical, data-first login form. Use a white card on a light gray background with a primary clinical blue actionable button. No gradients or glassmorphism. Wire the form submission to `login()` from `AuthContext` and display error states clearly.
   - **Verify**: Run `npm run lint` or `npm run build` to verify Next.js build step.

5. **RND Shell Layout & Global UI**
   - **Files**: `frontend/app/(rnd)/layout.tsx`, `frontend/app/(rnd)/dashboard/page.tsx`, `frontend/components/layout/Sidebar.tsx`, `frontend/components/layout/TopBar.tsx`, `frontend/app/globals.css`
   - **Change**: Define core clinical colors in `globals.css` (primary teal/blue, semantic status colors). Build the persistent `Sidebar` (left-aligned) and global `TopBar` (user profile, logout). Implement the `layout.tsx` wrapper for all `/rnd` pages, maintaining maximum width constraints.
   - **Verify**: Run `npm run build` to confirm the layout builds successfully.

## Risks & mitigations
- **Risk**: Token leakage or XSS vulnerabilities.
- **Mitigation**: The token will be stored strictly in an `HttpOnly` cookie set by the Next.js API layer, ensuring the client-side JavaScript never has direct access to the raw Laravel Sanctum token.

## Rollback plan
- Delete the `frontend/app/api/auth/` directory.
- Delete `frontend/contexts/`, `frontend/services/`, and `frontend/components/`.
- Remove `frontend/app/login/` and `frontend/app/(rnd)/` directories.
- Delete `frontend/middleware.ts` and revert `frontend/app/layout.tsx`.
