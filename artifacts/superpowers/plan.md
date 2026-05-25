### Goal
Apply the `ui-ux-pro-max` clinical SaaS design intelligence to modernize NutriScope's user interface. Transition the UI from a cold, legacy hospital-style design to a premium, high-density clinical SaaS console.

**Key visual & UX directives:**
1. **Branding**: "Nutri" in clinical emerald green (`#059669`), "Scope" in warning/accent tangerine orange (`#EA580C`). Custom premium logo SVG combining a health leaf and diagnostic lens reticle.
2. **Aesthetics**: Clean data-centric density, subtle grid lines, modern 8px rounded corners (`rounded-lg`), slate/zinc high-contrast color scheme.
3. **Layout**: A premium dark sidebar (`bg-zinc-950` / `bg-slate-950`) contrasting with clean, off-white page canvases.
4. **Custom Iconography**: Avoid boilerplate AI icon selections (e.g. `LayoutDashboard`, `HeartPulse`, `Activity`, `Users`). Replace with professional, distinctive Lucide icons like `Compass`, `CookingPot`, `HeartHandshake`, `Salad`, `TrendingUp`, `CalendarDays`, `BellDot`, `Sliders`, `Megaphone`.
5. **Warm Copy**: Replace bureaucratic/military jargon ("System Authentication", "Role: Registered Dietitian", "EXIT") with encouraging, high-fidelity humanized labels ("Welcome back", "Sign Out", active context-driven role display). Correct login email placeholders to reflect local seeds (`admin@nutriscope.local`, `rnd@nutriscope.local`).

---

### Assumptions
- Next.js is configured with Tailwind v4, utilizing `@theme` directives in `frontend/app/globals.css`.
- The user is currently running the frontend dev server (`npm run dev`) and laravel api server.
- All dependencies like `lucide-react` are fully installed and compatible.
- The user's system runs Windows. No OS or environment issues are expected during frontend builds.

---

### Plan

1. **Global Theme & Color Tokens Overhaul**
   - **Files**: `frontend/app/globals.css`
   - **Change**: 
     - Redefine custom theme color tokens under Tailwind v4 `@theme`.
     - Introduce brand primary green (`--color-brand-green-50` to `900` / `#059669`), brand secondary orange (`--color-brand-orange-50` to `900` / `#EA580C`), dark sidebar slate/zinc tokens (`#09090b` / `#020617`), and modern semantic status highlights.
     - Establish subtle global baseline focus rings (`focus-visible:ring-2 focus-visible:ring-emerald-500/30`) and transitions.
   - **Verify**: Run `npx tsc --noEmit --skipLibCheck` inside the `frontend` folder to check compilation.

2. **Handcrafted Brand Logo & Wordmark Component**
   - **Files**: `frontend/components/ui/Logo.tsx` [NEW]
   - **Change**: 
     - Create a highly professional, reusable React component for the NutriScope logo.
     - Build a custom inline SVG that acts as the brand mark (a stylized green leaf merging seamlessly with an orange diagnostic crosshair/lens reticle).
     - Render the wordmark as "Nutri" in medium/bold emerald green text and "Scope" in heavy tangerine orange text.
   - **Verify**: Check compilation with `npx tsc --noEmit --skipLibCheck`.

3. **Core Input & Button Component Modernization**
   - **Files**: `frontend/components/ui/Input.tsx`, `frontend/components/ui/Button.tsx`
   - **Change**: 
     - Refine `Input` component styling: replace uppercase text-xs tracking-wider labels with warm, semibold text-gray-700. Add soft border radii (`rounded-lg`), update focus rings to primary brand green, and clean up focus ring transition.
     - Refine `Button` component styling: transition base styles from plain `rounded` to `rounded-lg`. Update primary variant from plain blue to Emerald Green (`bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800`), update hover transition timing (200ms), and refine focus indicators.
   - **Verify**: Compile check with `npx tsc --noEmit --skipLibCheck`.

4. **Premium Dark Sidebar & Distinctive Iconography**
   - **Files**: `frontend/components/layout/Sidebar.tsx`
   - **Change**: 
     - Completely overhaul sidebar aesthetics to use a modern dark SaaS contrast layout (`bg-zinc-950` / `bg-slate-950` dark surface, slate-200/400 text, emerald active indicators).
     - Incorporate the brand new `<Logo />` component in dark mode styling.
     - Replace generic AI icons with distinctive, highly descriptive alternatives:
       - *Dashboard* -> `Compass` (Overview & Navigation)
       - *Recipes Database* -> `CookingPot` (Culinary operations)
       - *NCP Patients* -> `HeartHandshake` (Therapeutic alignment & patient care)
       - *Food Service* -> `Salad` (Kitchen operations & menus)
       - *Reports Center* -> `TrendingUp` (Data tracking & summaries)
       - *Calendar* -> `CalendarDays` (Schedule tracking)
       - *Notifications* -> `BellDot` (Activity indicators)
       - *System Settings* -> `Sliders` (Adjustable preferences)
     - Bind the footer dynamically to the authenticated user's role from `useAuth` context, outputting warm copy ("Active Session: [Name] ([Role])").
   - **Verify**: Compile check with `npx tsc --noEmit --skipLibCheck`.

5. **Warm Clinical Operations Console Header**
   - **Files**: `frontend/components/layout/TopBar.tsx`
   - **Change**: 
     - Humanize the page headers and module titles (e.g. from cold "Preferences & System Variables" to "System Settings & Preferences").
     - Style the user card using subtle green/orange background touches.
     - Replace the uppercase cold "EXIT" button with a friendly "Sign Out" action using an orange-tinted hover state and clean `LogOut` icon.
   - **Verify**: Compile check with `npx tsc --noEmit --skipLibCheck`.

6. **High-Fidelity Humanized Login Interface**
   - **Files**: `frontend/app/login/page.tsx`
   - **Change**: 
     - Rewrite cold intranet labels ("System Authentication", "Authorized clinical personnel access only", "Protected by AuditMiddleware") into highly professional yet human and warm welcoming copy ("Welcome to NutriScope", "Clinical nutrition and food service management designed for healthcare professionals.").
     - Integrate the newly minted split-color `<Logo />` component.
     - Correct default email input placeholders and examples to point directly to seeded accounts (e.g., `admin@nutriscope.local` or `rnd@nutriscope.local`) rather than generic `rnd@nutriscope.com`.
     - Align form fields, borders, background, and error alert banners to follow the emerald/orange and soft-rounded visual style.
   - **Verify**: Run `npx tsc --noEmit --skipLibCheck` to verify compilation.

7. **Brand Aligned Action & Overview Dashboard**
   - **Files**: `frontend/app/(rnd)/dashboard/page.tsx`
   - **Change**: 
     - Humanize page copy (e.g., "Patients Awaiting Nutrition Care" instead of "Active Patients Needing Assessment", "Broadcast Feed" instead of "Internal Broadcasts").
     - Inject warm greeting headers matching user session context (e.g., "Good morning, [Name]. Here is your nutrition care overview for today.").
     - Swap generic layout icons inside the KPI cards with unique brand icons matching the sidebar update (e.g. `HeartHandshake` for cases, `Salad` for food readiness).
     - Refine card border colors, active state highlights, and status tags to match the new emerald green/orange palette.
   - **Verify**: Perform a complete Next.js production build check `npm run build` in the `frontend` folder to guarantee clean compilation.

8. **Design System Documentation & Global Layout Refinement**
   - **Files**: `frontend/app/layout.tsx`, `docs/ui/design-system.md`
   - **Change**: 
     - Sync the official project design system at `docs/ui/design-system.md` to reflect the updated emerald green / orange brand colors, premium dark sidebar style, custom typography pairings, rounded-lg component frameworks, and warm/professional copywriting guidelines.
     - Update metadata elements in `layout.tsx` to maintain full aesthetic consistency.
   - **Verify**: Run a full production build check `npm run build` inside `frontend`.

---

### Risks & mitigations
- **Risk**: Build or compilation errors due to missing icons or TS syntax errors in Next.js pages.
- **Mitigation**: Run local typescript type checking (`npx tsc --noEmit --skipLibCheck`) after every step. Never leave placeholders or incomplete imports.
- **Risk**: Layout shifts or navigation breaking when switching sidebar icons or background classes.
- **Mitigation**: Keep structural HTML identical; update only tailwind classnames and icon imports. Verify collapsible transition responsiveness on multiple widths.

---

### Rollback plan
- In case of failure, discard local changes using git checkout:
  `git checkout -- frontend/app/globals.css frontend/app/login/page.tsx frontend/components/ui/Input.tsx frontend/components/ui/Button.tsx frontend/components/layout/Sidebar.tsx frontend/components/layout/TopBar.tsx frontend/app/(rnd)/dashboard/page.tsx frontend/app/(rnd)/layout.tsx frontend/app/layout.tsx docs/ui/design-system.md`
- Delete the newly created Logo component: `rm frontend/components/ui/Logo.tsx`
- Ensure all files are restored to their pristine git state.
