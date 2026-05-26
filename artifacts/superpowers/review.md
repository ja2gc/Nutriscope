# Superpowers Review — Milestone 1: Frontend Auth UI

**Scope:** Login page, AuthContext, authService, Next.js API routes (login/logout/me), middleware, RND layout, TopBar logout  
**Date:** 2026-05-27  
**Reviewer:** Superpowers Agent (automated)

---

## Blockers

### B-1 · Logout does not clear cookie on backend failure → stale session

| Detail | |
|---|---|
| **Files** | `TopBar.tsx:L25-32`, `logout/route.ts:L6-23` |
| **Severity** | **Blocker** — user appears logged out in React state but the cookie survives |

**Problem:**  
In `TopBar.tsx`, if `logout()` throws (e.g. network failure or 500 from Laravel), the `catch` block only logs to console and re-throws. `AuthContext.logout()` sets `user = null`, but the Next.js API route `logout/route.ts` is never reached, so `nutriscope_token` cookie **is never deleted**. On next page load, middleware sees the cookie, allows access, and `fetchCurrentUser` may succeed — silently re-authenticating the user.

**Proposed fix:**  
The frontend logout API route should **always** delete the cookie, regardless of whether the Laravel backend call succeeds. Additionally, `TopBar.handleLogout` should redirect even on error (best-effort logout).

```diff
 // frontend/app/api/auth/logout/route.ts
 export async function POST(_req: NextRequest) {
   const cookieStore = await cookies();
   const token = cookieStore.get("nutriscope_token")?.value;

+  const res = NextResponse.json({ message: "Logged out." }, { status: 200 });
+  res.cookies.delete("nutriscope_token");
+
   if (token) {
-    await fetch(`${LARAVEL_API}/auth/logout`, {
-      method: "POST",
-      headers: {
-        Authorization: `Bearer ${token}`,
-        Accept: "application/json",
-      },
-    });
+    try {
+      await fetch(`${LARAVEL_API}/auth/logout`, {
+        method: "POST",
+        headers: {
+          Authorization: `Bearer ${token}`,
+          Accept: "application/json",
+        },
+      });
+    } catch {
+      // Best-effort: token already cleared client-side
+    }
   }

-  const res = NextResponse.json({ message: "Logged out." }, { status: 200 });
-  res.cookies.delete("nutriscope_token");
   return res;
 }
```

```diff
 // frontend/components/layout/TopBar.tsx
 const handleLogout = async () => {
   try {
     await logout();
-    router.push("/login");
+    router.replace("/login");
   } catch (err) {
-    console.error("Failed to log out:", err);
+    // Force redirect even on failure — cookie is cleared server-side
+    router.replace("/login");
   }
 };
```

---

### B-2 · Root page (`/`) bypasses middleware — unauthenticated access to server redirect

| Detail | |
|---|---|
| **Files** | `middleware.ts:L16`, `app/page.tsx` |
| **Severity** | **Blocker** — unauthenticated user hits `/` → server-side `redirect("/dashboard")` → middleware redirects to `/login` → redirect chain that is fragile and exposes route |

**Problem:**  
`middleware.ts L16` explicitly excludes `pathname === "/"` from auth checks (`isInternalOrStatic`). The root `page.tsx` unconditionally calls `redirect("/dashboard")` server-side. This creates:
1. An unnecessary redirect chain (`/ → /dashboard → /login` for unauthenticated users)
2. A 302 to `/dashboard` leaking that route exists even for unauthenticated visitors

**Proposed fix:**  
Remove `"/"` from the `isInternalOrStatic` check. The middleware matcher already excludes `_next`, `api`, and `favicon.ico` — root `/` should be handled like any other page:

```diff
 // frontend/middleware.ts
-  const isInternalOrStatic =
-    pathname.startsWith("/_next") ||
-    pathname.startsWith("/api") ||
-    pathname.includes(".") || // files like favicon.ico, images
-    pathname === "/"; // landing page or initial load redirect
+  const isInternalOrStatic =
+    pathname.startsWith("/_next") ||
+    pathname.startsWith("/api") ||
+    pathname.includes("."); // files like favicon.ico, images
```

After this change, unauthenticated users hitting `/` will be redirected directly to `/login` by middleware (single redirect). Authenticated users will proceed to root `page.tsx` which redirects to `/dashboard`.

---

## Majors

### M-1 · Middleware auth check is cookie-presence only — no token validation

| Detail | |
|---|---|
| **File** | `middleware.ts:L5` |
| **Severity** | **Major** — expired/revoked tokens pass middleware, user sees shell flash before redirect |

**Problem:**  
The middleware only checks `request.cookies.get("nutriscope_token")?.value` for truthiness. If the token is expired or revoked on the backend, middleware still allows the request. The RND layout then calls `fetchCurrentUser()`, gets a 401, and `AuthContext` sets `user = null`, causing the layout's `useEffect` to redirect to `/login`. The user sees a brief flash of the loading spinner and layout shell before being bounced.

**Assessment:**  
This is an inherent limitation of Next.js Edge Middleware (no access to external APIs for token validation). The current design is acceptable as a **defense-in-depth** layer, but the consequence (layout flash) should be mitigated.

**Proposed fix:**  
No middleware change needed. Instead, harden the `/me` route handler (already correctly clears cookie on 401 — ✅ good) and ensure the RND layout does not render children until auth is confirmed. The current implementation already does this correctly with the `if (!user) return null` guard at `layout.tsx L54-56`. **No code change required; documenting as accepted risk.**

---

### M-2 · No role-based redirect — all roles land on `/dashboard` regardless

| Detail | |
|---|---|
| **Files** | `login/page.tsx:L37`, `middleware.ts:L24` |
| **Severity** | **Major** — FSS and Admin users are sent to RND dashboard layout with no guard |

**Problem:**  
After login, `login/page.tsx L37` hard-redirects to `/dashboard`. The RND layout at `(rnd)/layout.tsx` wraps `/dashboard` but has no role check — it only checks if `user` exists. An FSS or Admin user would see the RND sidebar and layout.

**Assessment:**  
FSS and Admin routes don't exist yet, so this is acceptable for Milestone 1. However, it should be addressed before Milestone 9 (FSS) and Milestone 10 (Admin).

**Proposed fix (deferred):**  
Add a role-based redirect map in the login page's post-login handler:

```typescript
// Future implementation
const ROLE_HOME: Record<string, string> = {
  RND: "/dashboard",
  FSS: "/fss/dashboard",
  Admin: "/admin/dashboard",
};
router.replace(ROLE_HOME[res.user.role] || "/dashboard");
```

> **NOTE:** No code change now — this is tracked as a future task.

---

### M-3 · `NEXT_PUBLIC_API_URL` env variable is unused — potential confusion

| Detail | |
|---|---|
| **File** | `.env.local` |
| **Severity** | **Major** — misleading config may cause future bugs if a developer mistakenly uses the public URL in client-side code |

**Problem:**  
`.env.local` defines both `NEXT_PUBLIC_API_URL` and `LARAVEL_API_URL`. The `NEXT_PUBLIC_` prefix exposes the value to client-side bundles. However, no frontend code uses `NEXT_PUBLIC_API_URL` — all API calls go through Next.js API routes (`/api/auth/*`), which use `LARAVEL_API_URL` server-side only. If a future developer uses `NEXT_PUBLIC_API_URL` in a client component, they'd bypass the BFF pattern and send requests directly to Laravel, leaking the Sanctum token handling.

**Proposed fix:**  
Remove the unused variable from `.env.local`.

---

## Minors

### m-1 · Login page shows spinner during initial auth check (not just login action)

| Detail | |
|---|---|
| **Files** | `login/page.tsx:L119`, `AuthContext.tsx:L19` |
| **Severity** | **Minor** — UX friction: button shows "Processing..." and inputs are disabled during the initial `refreshUser()` call on page load |

**Problem:**  
`AuthContext` starts with `loading: true` and calls `refreshUser()` on mount. While that runs, the login page's Button receives `loading={loading}` and inputs receive `disabled={loading}`. The user sees a disabled form with "Processing..." text before they've done anything.

**Proposed fix:**  
Distinguish between `initializing` (first mount check) and `loading` (active login/logout action):

```diff
 // contexts/AuthContext.tsx
+const [initializing, setInitializing] = useState<boolean>(true);
-const [loading, setLoading] = useState<boolean>(true);
+const [loading, setLoading] = useState<boolean>(false);

 const refreshUser = async () => {
   try {
-    setLoading(true);
     setError(null);
     const currentUser = await fetchCurrentUser();
     setUser(currentUser);
   } catch (err: any) {
     setUser(null);
     ...
   } finally {
-    setLoading(false);
+    setInitializing(false);
   }
 };
```

Then export `initializing` and use it in the RND layout for the loading screen, while the login page uses only `loading` for button state.

---

### m-2 · AuthContext error is not cleared on successful navigation

| Detail | |
|---|---|
| **File** | `AuthContext.tsx:L43-55` |
| **Severity** | **Minor** — if a user fails login, then navigates away and returns, the error may persist in context |

**Assessment:**  
Currently low impact since `/login` is the only public page. The login form already clears `validationError` on input change. The context `error` is cleared at the start of `login()`. **Accepted risk for now.**

---

### m-3 · Missing unique IDs on interactive elements (SEO/testing)

| Detail | |
|---|---|
| **File** | `login/page.tsx` |
| **Severity** | **Minor** — no `id` on the form or submit button for automated testing |

**Problem:**  
The `<form>`, `<Button>`, and error container lack unique `id` attributes. This hinders browser automation and testing.

**Proposed fix:**  
```diff
-          <form onSubmit={handleSubmit} className="space-y-4">
+          <form id="login-form" onSubmit={handleSubmit} className="space-y-4">
             ...
-              <Button type="submit" loading={loading}>
+              <Button id="login-submit" type="submit" loading={loading}>
```

---

### m-4 · `router.push` vs `router.replace` inconsistency on logout

| Detail | |
|---|---|
| **File** | `TopBar.tsx:L28` |
| **Severity** | **Minor** — `router.push("/login")` after logout allows browser back-button to return to a protected page shell |

**Problem:**  
Login page uses `router.replace("/dashboard")` correctly (prevents back-button to login), but logout uses `router.push("/login")`. After logout, pressing Back brings the user back to the dashboard URL — middleware will redirect to login again, but it's a UX stutter.

**Proposed fix:** Already addressed in B-1 fix (change to `router.replace`).

---

## Nits

### N-1 · Hardcoded `/dashboard` redirect target in multiple places

| Detail | |
|---|---|
| **Files** | `login/page.tsx:L21`, `login/page.tsx:L37`, `middleware.ts:L24`, `app/page.tsx:L4` |
| **Severity** | **Nit** — maintainability |

Consider extracting route constants to a shared `routes.ts` file:
```typescript
export const ROUTES = {
  LOGIN: "/login",
  DASHBOARD: "/dashboard",
} as const;
```

---

### N-2 · `any` type in catch blocks

| Detail | |
|---|---|
| **File** | `AuthContext.tsx:L28` |
| **Severity** | **Nit** — `catch (err: any)` appears 3 times; use `unknown` + type narrowing |

---

### N-3 · `useEffect` dependency lint — `refreshUser` not in deps

| Detail | |
|---|---|
| **File** | `AuthContext.tsx:L39-41` |
| **Severity** | **Nit** — `refreshUser` is not memoized with `useCallback` and is not listed in the `useEffect` deps. Currently safe because the function doesn't change identity across renders in practice, but will trigger ESLint `react-hooks/exhaustive-deps` warnings. |

---

## Summary

| Severity | Count | Action Required |
|----------|-------|-----------------|
| **Blocker** | 2 | Must fix before marking Milestone 1 review complete |
| **Major** | 3 | 1 fix now (M-3), 2 accepted/deferred (M-1, M-2) |
| **Minor** | 4 | Fix m-1 and m-3 now; m-2, m-4 accepted or covered by blocker fixes |
| **Nit** | 3 | Optional polish |

### Next Actions

1. **Fix B-1**: Harden logout route to always clear cookie; fix redirect in TopBar
2. **Fix B-2**: Remove `/` bypass in middleware
3. **Fix M-3**: Remove `NEXT_PUBLIC_API_URL` from `.env.local`
4. **Fix m-1**: Split `loading` vs `initializing` in AuthContext *(optional, can defer)*
5. **Fix m-3**: Add IDs to login form elements
6. Mark Milestone 1 review checkbox as complete in milestones.md after fixes are applied and verified
