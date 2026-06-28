# NutriScope Design System

> Fresh, clinical, alive. The design language for NutriScope — a hospital
> clinical-nutrition & food-service platform.

---

## 1. What NutriScope is

**NutriScope** is a clinical & operational care platform used inside a hospital
to run the full **Nutrition Care Process (NCP)** and the kitchen/**Food Service**
operation that feeds patients. It is built for three audiences:

| Role | Surface | What they do |
|------|---------|--------------|
| **Registered Nutritionist-Dietitian (RND)** | Web app | Run the NCP cycle per patient: **Assessment → Diagnosis → Intervention → Monitoring**, manage the food library, review reports. |
| **Admin** | Web app (dark/forest chrome) | Manage users (RBAC), publish announcements, audit logs, hospital-wide settings & reports. |
| **Food Service Supervisor (FSS)** | Mobile app (Expo) | Daily kitchen ops on the floor: dashboard, menu cycle, prep, inventory adjustments, procurement. |

The product is **metric-dense** (cost-per-head in **₱ Philippine pesos**, stock
counts, lab values, census) and **workflow-driven**. It is a tool people use for
hours a day — so the system is calm, legible, and fast, but **lively and green**,
never the cold clinical gray of typical hospital software.

### Sources this system was built from
- **`frontend/`** — Next.js 15 (App Router) + Tailwind v4 web app. Source of the
  component library (`components/ui/*`), `theme.ts` token map, login, dashboard,
  food-service & NCP screens, and the `Logo` brand mark.
- **`mobile/`** — Expo / React Native (NativeWind) FSS app. Source of the mobile
  dashboard, inventory, menu, prep, procurement screens and `BrandLogo`.
- Both codebases were read directly (not screenshots). Icons are **Lucide**
  (`lucide-react` on web, `lucide-react-native` on mobile).

### What changed vs. the shipped product (this system is an evolution)
The client asked for a brand that feels **green, lively, and warm — not dark and
gloomy**, and flagged the nav + wordmark color as "a bit off." So this system:
- Replaces the near-black `zinc-950` sidebar with a **deep forest-green** nav.
- Swaps cold `zinc` neutrals for **warm, faintly green-tinted** neutrals + a
  bright off-white page background.
- Adds a **lime "sprout"** tertiary accent for liveliness.
- Adopts **Plus Jakarta Sans** as the universal type (the shipped app used the
  system sans stack) + **JetBrains Mono** for the heavy tabular data.
- Redesigns **login as a split landing** (brand/imagery half + form half).
Everything else (green/orange brand, leaf+scope mark, rounded-2xl cards, Lucide
icons, ₱ currency, NCP/Food-Service IA) is faithful to the codebase.

---

## 2. Content fundamentals (voice & copy)

**Voice:** professional, precise, reassuring — a competent clinical colleague.
Calm authority, never playful or salesy.

- **Person:** address the user as **you** ("access your workspace"); the system
  refers to itself impersonally ("Activity Logs Active"). Avoid "we".
- **Casing:** **Title Case** for buttons, nav items, page/module titles
  ("Sign In", "Food Service & Kitchen Operations"). **UPPERCASE + wide tracking**
  for overlines, micro-labels, badges, KPI labels, role tags
  ("ACTIVE SESSION", "CLINICAL & OPERATIONAL CARE CONSOLE").
- **Sentence style:** short, direct, complete sentences with terminal periods in
  helper text ("Enter your credentials below to access your workspace.").
- **Domain register:** real clinical/ops vocabulary — Assessment, Diagnosis,
  Intervention, Monitoring, NCP cycle, cost-per-head, restock, shortfall,
  census, procurement, PO (purchase order). Never dumb it down.
- **Numbers:** always tabular; currency is **₱** with 2 decimals
  (`₱1,240.00`); patient IDs are `NS-00042`; relative time on mobile
  ("2h ago", "just now").
- **Status language** pairs a word with a color, never color alone
  ("In stock" / "No stock", "Prepped", "Shortfall", "3 days overdue").
- **Emoji:** **none.** This is a clinical tool. Meaning is carried by Lucide
  icons, status dots, and text.
- **Tone examples:**
  - Empty state: "No service entries for today."
  - Error: "Could not load dashboard. Check your connection and try again."
  - Footer trust line: "Secure Connection • Activity Logs Active"

---

## 3. Visual foundations

**Color** — Green is the hero (health, vitality), Orange is the warm accent
(appetite, the "Scope" half), Lime is the lively sprout highlight. Neutrals are
warm and faintly green-tinted; the page background is a bright off-white
(`#f8faf6`) so the product reads alive. Dark surfaces (nav, admin bar) are
**deep forest green**, not black. Status colors: green=ok, amber=warn, red=danger,
sky=info. See `tokens/colors.css`.

**Type** — Plus Jakarta Sans everywhere; JetBrains Mono for tabular figures.
Headings use tight tracking (`-0.02em`); overlines/labels use UPPERCASE with wide
tracking (`0.08em`). Display scale tops out at 48px (login hero). Body is 15px.
See `tokens/typography.css`.

**Spacing** — 4px base step. Card inset is 20px (`--space-5`). Generous but not
airy; dashboards stay dense and scannable.

**Backgrounds** — flat warm off-white surfaces, not gradients. **Photography**
(food/produce, fresh & green, bright & warm-lit) appears only in *marketing /
login / hero* contexts — never behind data. Imagery is sourced from Pexels and
hotlinked (see Caveats). A subtle **leaf/sprout watermark** or a soft green
radial wash is the most decoration a data surface ever gets.

**Corners & cards** — the signature surface is a **white card, 1px subtle border
(`--border-subtle`), 16px radius (`--radius-xl`), soft low shadow (`--shadow-sm`)**.
Controls (buttons, inputs) use a 10px radius. Pills/badges are fully rounded.

**Shadows** — soft, low-spread, green-neutral tinted. Cards rest on `--shadow-sm`;
popovers/modals lift to `--shadow-lg`. Primary CTAs may carry a faint green glow
(`--shadow-brand`). Never heavy or black drop-shadows.

**Borders** — hairline `1px` in `--border-subtle`. Forest surfaces use
`--forest-line`. Active nav items take a 2px green/lime left-accent.

**Motion** — quick and confident: `--dur-base` 200ms with `--ease-out`. Fades +
small translate/scale for entrances; submenu accordions animate max-height.
No bounce, no long durations. Respect `prefers-reduced-motion`.

**Hover / press** — hover lightens surfaces (`--surface-hover`) or deepens brand
(green-600 → 700); icon buttons get a soft neutral wash. Press deepens one more
step (active:green-700) and/or a 1px nudge — never a big scale.

**Transparency & blur** — sparingly: modal scrims are `black/50`; the forest nav
may use faint `white/5–10` overlays for active states. Glass/blur is not part of
the language.

---

## 4. Iconography

- **System:** **Lucide** — the product's real icon set on both web
  (`lucide-react`) and mobile (`lucide-react-native`). 1.5–2px stroke, rounded
  joins, line (not filled) style. Use it for everything.
- **In this design system**, load Lucide from CDN
  (`https://unpkg.com/lucide@latest`) and call `lucide.createIcons()`, matching
  the product. Default size 18–22px, `currentColor` so they inherit text color.
- **Common glyphs in product:** Compass (dashboard), HeartHandshake (Nutrition
  Care), Salad / CookingPot (Food Service), Package (inventory), ShoppingCart
  (procurement), CalendarDays (menu), BarChart3 (prep/reports), Bell (alerts),
  Megaphone (announcements), Users, Sliders (settings), LogOut.
- **Brand mark:** `assets/mark.svg` — a **leaf** (Nutri, green) fused with a
  **diagnostic-scope crosshair** (Scope, orange). Do not redraw it; reference the
  asset. The wordmark sets **Nutri** in green-600 and **Scope** in orange-600,
  Plus Jakarta Sans 800, tight tracking.
- **Status dots:** 6px filled circles in semantic colors, always beside a label.
- **No emoji. No unicode-glyph icons.** (The `✕` close used in the mobile code is
  acceptable for a dismiss affordance; prefer Lucide `X` where possible.)

---

## 5. Index / manifest

**Root**
- `styles.css` — global entry (import this). `@import`s the tokens below.
- `tokens/colors.css`, `tokens/typography.css`, `tokens/spacing.css`,
  `tokens/fonts.css` — the foundation.
- `assets/mark.svg` — brand mark.
- `SKILL.md` — Agent-Skill manifest for downloading/using this system.

**Foundations** (Design System tab — groups: Colors, Type, Spacing, Brand)
- `guidelines/*.card.html` — specimen cards.

**Components** (`components/`, group: Components)
- `core/` — Button, IconButton, Input, Badge, StatusBadge, Card, KpiCard,
  Tabs, Avatar, Toggle/Filter chips.

**UI kits**
- `ui_kits/web/` — RND/Admin web app: split-screen **Login**, **Dashboard**,
  **Food Service** screens.
- `ui_kits/mobile/` — improved FSS mobile app: Dashboard, Inventory, Menu.

See each kit's `README.md` for screen list & interactions.

---

## 6. Caveats
- **Fonts** are loaded from Google Fonts CDN (Plus Jakarta Sans + JetBrains Mono),
  not bundled as local files (binary download wasn't possible in this
  environment). If you need offline/self-hosted fonts, drop the `.woff2` files in
  `tokens/` and swap the `@import` for `@font-face`.
- **Imagery** is hotlinked from Pexels CDN (free license). For production, copy
  the chosen photos into `assets/` and reference locally.
- The brand evolution (forest nav, warm neutrals, Plus Jakarta Sans, lime accent,
  split login) is an intentional uplift requested by the client — confirm before
  pushing these tokens back into the live codebase.
