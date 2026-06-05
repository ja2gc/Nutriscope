# NutriScope UI/UX Architecture & Design System

## 1. System UI Architecture

### 1.1 Core Philosophy
NutriScope is a clinical and operational system, not a consumer application. The design must be data-first, high-density, and structured to support rapid workflow execution. The interface serves as a professional medical instrument, emphasizing clarity over decoration.

### 1.2 Shared Layout System
- Sidebar Navigation: Persistent, left-aligned, collapsible vertical sidebar containing role-specific modules. Active states highlighted with primary color. Order exactly matches the frontend folder architecture.
- Global Top Bar: Contextual header containing the current module title, user profile, and global notifications bell (with unread count badge).
- Content Canvas: Centered main area with maximum width constraints (`max-w-7xl` for tables, `max-w-5xl` for forms) to preserve readability on ultrawide monitors. Breadcrumb trails provide deep navigational context.
- Contextual Panels: Slide-out side drawers used for secondary actions (e.g., AI review, OCR verification, patient history peeks) to maintain primary workflow context.

### 1.3 Design System Tokens
- Typography: Clean, modern sans-serif (Inter with Plus Jakarta Sans styling). High-contrast weights for clinical reading. Monospace fonts utilized exclusively for patient IDs, biochemical lab parameters, and numbers.
- Color Palette:
  - Brand Primary Green ("Nutri"): Clinical Emerald Green (`#059669` / `brand-green-600`) for success metrics, primary buttons, active link outlines, and core actions.
  - Brand Secondary Orange ("Scope"): Tangerine Orange (`#EA580C` / `brand-orange-600`) for warning indicators, alert badges, and select brand visual accents.
  - Shell Backgrounds: Premium dark contrast Sidebar (`#09090b` / `zinc-950`), warm off-white main content Canvas Background (`#fafafa` / `zinc-50`), and pure white cards with soft zinc borders.
  - Corner Radii: Standardized 8px modern rounded borders (`rounded-lg` / `rounded-xl`) across cards, inputs, and action buttons.
  - Semantic Status: Red (Critical/Low/Allergy), Orange/Amber (Warning/Expiring), Green (Normal/Completed), Zinc/Slate (Draft/Inactive).
- Iconography System: Pure SVG iconography rejecting generic AI indicators. Built using highly descriptive specific indicators (`Compass` for dashboards, `CookingPot` for recipes, `HeartHandshake` for NCP care, `Salad` for food oversight, `TrendingUp` for reports).
- Copy & Writing Tone: Warm, humanized, and highly encouraging professional copywriting, avoiding bureaucratic, intimidating, or military intranet phrasing.
- Spacing Framework: Dense, strict 4px/8px baseline grid to allow maximum data visibility without visual clutter.

## 2. User Flow Mapping

### 2.1 RND (Clinical Nutrition) End-to-End Workflow
1. Authentication: Login → Land on RND Dashboard.
2. Clinical Workflow (NCP): Navigate to NCP Patients → Add Patient or Select Active. In Assessment, take pictures of Referral Nutrition Screening Forms and Biochemical labs to extract specific key-value data and auto-fill templates. Dietary, Anthropometric, and Clinical data are entered manually (no OCR). Proceed to Diagnosis (AI assisted) → Intervention (Meal Plan) → Monitoring.
3. Food Service Oversight: Navigate to Food Service → Check Inventory alerts → Review Budget → Create Procurement lists → Build Menu Cycles for FSS to execute.
4. Communication & Schedule: Check Notifications → Review Calendar for patient follow-ups or reassessments. (Announcements are managed on the Dashboard).

### 2.2 FSS (Food Service) End-to-End Workflow
1. Authentication: Login (Mobile/Tablet) → Land on FSS Dashboard showing "Today's Readiness".
2. Inventory Execution: Navigate to Inventory → Update physical counts → Mark items low (notifies RND Procurement).
3. Meal Preparation: Navigate to Menu Cycle (view recipes/ingredients) → Execute prep → Check off meals in Meal Prep Log.

### 2.3 Admin End-to-End Workflow
1. Authentication: Login → Land on Admin Dashboard.
2. System Administration: Navigate to Users (manage access) → Review Reports (global stats) → Publish Announcements.
3. Compliance Tracking: Navigate to Audit Logs to monitor system-wide actions.

### 2.4 Cross-Module Interactions
- RND → FSS: RND creates Menu Cycles. FSS receives them as read-only prep guides.
- FSS → RND: FSS updates Inventory and flags low stock. RND sees this in the Food Service -> Inventory and Procurement modules. FSS updates Meal Prep Log, which RND can monitor.
- Admin ↔ All: Admin manages Roles/Settings. Both Admin and RND can broadcast Announcements to all roles.

## 3. Page Architecture

### 3.1 RND Module Navigation & Purpose
- Dashboard (`/dashboard`):
  - Purpose: High-level daily overview. View KPIs, quick-jump to active NCPs needing attention, review budget burn. Read and post announcements (broadcast to FSS/Admin/All) directly from the dashboard.
- Recipes (`/recipes`):
  - Purpose: Central database for food items and composite recipes.
  - Actions: Search USDA database, build recipes, auto-calculate macros and costs per recipe.
- NCP (`/ncp`):
  - Patients (`/ncp/patients`): Master list. Add Patient, search, filter by risk/status.
  - Assessment (`/ncp/assessment`): Take pictures of Referral Nutrition Screening Forms and Biochemical labs to extract specific key-value data and auto-fill templates. Manually enter Dietary, Anthropometric, and Clinical data (no OCR).
  - Diagnosis (`/ncp/diagnosis`): Build PES statements, trigger AI Review, accept/reject suggestions.
  - Intervention (`/ncp/intervention`): Set nutrient goals, trigger algorithm to generate Meal Plan(can be manual/AI generated/be modified), review plan.
  - Monitoring (`/ncp/monitoring`): Log new lab values/weight, view trend lines, receive AI recommendations.
- Food Service (`/food-service`):
  - Inventory (`/food-service/inventory`): View current stock levels, expiry dates, and usage rates. Receive FSS low-stock alerts.
  - Menu Cycle (`/food-service/menu-cycle`): Build weekly menu cycles based on recipes and available inventory. Cost per person and budget alerts visible here.
  - Budget (`/food-service/budget`): Track planned vs. actual spending, review daily logs, identify variances.
  - Procurement (`/food-service/procurement`): Manage purchasing. View suggested lists (from inventory usage/menu cycles), upload receipts, mark as purchased to automatically update Inventory.
- Reports (`/reports`):
  - Purpose: Generate and download PDF reports (NCP, Budget, Inventory, Menu) via DomPDF.
- Calendar (`/calendar`):
  - Purpose: View system-generated follow-ups (reassessments, monitoring rechecks, budget deadlines) and manual events.
- Notifications (`/notifications`):
  - Purpose: View all system alerts, FSS inventory flags, and AI completion notices.
- Settings (`/settings`):
  - Purpose: Manage personal preferences.

### 3.2 FSS Module Navigation & Purpose (Mobile/Tablet)
- Dashboard (`/dashboard`):
  - Purpose: Immediate operational status for the kitchen floor (Today's meals + readiness, inventory alerts). Includes read-only view of announcements posted by Admin or RND.
- Inventory (`/inventory`):
  - Purpose: Stock tracking. View/update stock, color-coded alerts (red=low, yellow=expiring). Flag low items to notify RND.
- Menu Cycle (`/menu-cycle`):
  - Purpose: Read-only view of planned meals. Weekly calendar view. Tap day → ingredients + prep instructions. Color alerts (missing/changed/ready).
- Meal Prep Log (`/meal-prep-log`):
  - Purpose: Task execution checklist for today's required meals. Updates status visible to RND and Admin.
- Notifications (`/notifications`):
  - Purpose: Receive RND/Admin alerts.
- Settings (`/settings`):
  - Purpose: Personal app preferences.

### 3.3 Admin Module Navigation & Purpose
- Dashboard (`/dashboard`):
  - Purpose: System KPIs, charts (admissions/NCP completion/budget/inventory), activity feed. Read, create, pin, and delete announcements directly from the dashboard.
- Users (`/users`):
  - Purpose: Create/edit/deactivate users, assign roles, reset passwords.
- Reports (`/reports`):
  - Purpose: System-wide analytics and cross-role reporting.
- Audit Logs (`/audit-logs`):
  - Purpose: Full trail of system actions. Filter by model, user. View old/new value JSON diffs.
- Settings (`/settings`):
  - Purpose: Configure hospital info, budget thresholds, notification rules, system variables.

## 4. Data Display Rules

### 4.1 UI Component Consistency
- Tables: Use zebra striping for scannability in dense lists. Enforce sticky headers for tables that require vertical scrolling. Right-align numeric columns (currency, macros, quantities). Use inline badges for status columns.
- Cards: Utilize simple 1px solid borders. Do not use drop shadows unless indicating a hovered or elevated interactive state. Use for discrete entities (e.g., Recipe summary cards).
- Forms: Labels must be placed above inputs to support scanning. Group related fields into visual blocks with subtle background shading or clear divider lines. Always mark required fields explicitly. Ensure frontend validation exactly mirrors backend Form Requests.

### 4.2 Clinical Data Visualization (NCP / ADIME)
- Patient Header: Must persistently display the patient's name, ward, primary medical diagnosis, and critical alerts (allergies, restrictions as prominent red/amber badges) across all NCP tabs.
- Assessment Grids: Utilize 2-column or 3-column layouts to condense data (e.g., Anthropometric data alongside Biochemical labs).
- Lab Values: Must use monospace fonts. Any out-of-range value must be immediately flagged with bold text and a semantic color indicator (e.g., Red).
- PES Statements: Rendered as visually distinct blocks (e.g., gray background cards) that explicitly separate the Problem, Etiology, and Symptoms to ensure clinical clarity.

### 4.3 Information Hierarchy
1. Critical Alerts: Patient allergies, critical out-of-range lab values, low stock, missing ingredients (Red/Amber) command highest visual weight.
2. Primary Actions: Core workflow triggers like "Generate Meal Plan" or "Approve AI Review" (Primary Brand Color).
3. Core Data: Patient names, diagnosis names, recipe titles (Large font, high contrast text).
4. Metadata: Timestamps, audit logs, secondary stats (Smaller font, muted text).

## 5. UI System Constraints

### 5.1 Strict Operational Constraints
- No Decorative Patterns: Absolutely no gradients, glassmorphism, floating blobs, illustrations, or celebratory animations. The interface is a tool, not a toy.
- High-Density, Readable Layouts: Maximize the amount of information on screen without creating clutter. Rely on typography (weight, size, color) and rigid grid alignment to establish hierarchy rather than excessive whitespace padding.
- Workflow Optimization: Minimize clicks for repetitive tasks. Support keyboard navigation and bulk actions within tables. Ensure all truncated data is accessible via fast, native tooltips.
- Absolute Consistency: Implementation must be uniform. Inline styles or arbitrary CSS outside the defined system patterns are prohibited. A form or button in the Admin module must behave and look identical to its counterpart in the RND module.

---

## 6. Interaction Design Principles

These principles govern ALL interactive patterns across every module. Implementation must follow them without exception.

### 6.1 Progressive Disclosure

Never present all options to the user at once. Reveal complexity only when the user has indicated they need it.

**Rules:**
- Show the primary decision first. Show dependent decisions only after the primary is made.
- A secondary control (e.g., Stage selector) must not be visible until its parent decision (e.g., Goal selection) is made.
- Optional data (micronutrients, barriers, counseling strategies) is hidden behind an explicit toggle. The toggle label describes what will appear: "Show Micronutrients", "Add Barriers", not generic "More Options".
- Completed multi-step sections collapse to a summary badge. The user can re-expand to edit.

**Example — Intervention Goal Selector:**
```
Step 1: Goal card grid (all goals visible)
  → User selects one card → card highlights
Step 2: IF goal has stages → Stage dropdown appears inline below selected card
  → No stage dropdown shown before a goal is picked
  → No stage dropdown shown for goals without stages (diabetic_control, custom)
Step 3: Confirm → prescription auto-fills → selector collapses to summary badge
```

**Example — Micronutrient Display:**
```
Default: micronutrient rows hidden
"Display Micros" button → checkbox popover appears
  → All micros listed with checkboxes (unchecked by default)
  → Goal-relevant micros are pre-checked automatically (e.g., renal_diet pre-checks potassium, phosphorus, sodium)
  → RND checks/unchecks freely
  → Checked micros appear as editable rows in the prescription form
  → State stored in interventions.displayed_nutrients (json)
```

### 6.2 Conditional Reveal

When one field controls the visibility of another, the reveal must be:
- **Instant** — no loading spinner for field appearance
- **Animated** — `transition-all duration-150` for height expansion, never jarring snap
- **Reversible** — changing the parent choice collapses the child and resets its value
- **Labeled** — the revealed field has a clear label explaining why it appeared

Implementation: use CSS max-height transition or Radix UI Collapsible. Never use `display:none` toggled by JS without transition.

### 6.3 Collapsible Completed Sections

For long multi-section pages (e.g., Intervention Tab 1):
- After a section is saved/confirmed, it collapses to a one-line summary chip showing key values
- An "Edit" link on the chip re-expands the section
- This keeps the page scannable without removing data

```
[✓ Renal Diet — Stage 4 — 1800 kcal · P 54g · C 270g · F 50g]  [Edit]
```

### 6.4 Sticky Clinical Summary Bar

For any page where the user is building toward a target (Intervention Tab 1, Meal Plan):
- A sticky bar is fixed at the top of the content canvas (below the tab strip)
- Shows: current accumulated value vs target value for each active macro
- Color coding: Green = within 10% of target, Amber = within 20%, Red = >20% off or below floor
- Updates in real time as meal plan items are added/removed
- Never hides behind scrolled content — `position: sticky; top: 0; z-index: 10`

### 6.5 Feedback on Every Action

Every user-triggered state change must produce immediate feedback:
- Button loading state (spinner replaces icon, text dims) while awaiting API
- Toast notification (bottom-right, 3s auto-dismiss) on success or error
- Inline field-level error messages (red, below field) for validation failures
- No silent failures — if an API call fails, show an error state, never silently reset

### 6.6 Keyboard and Focus

- All modals trap focus inside while open. Escape closes. First focusable element receives focus on open.
- All dropdowns and selects are keyboard-navigable (arrow keys, Enter to select, Escape to close).
- Tab order must follow visual reading order (left-to-right, top-to-bottom).
- Focus ring: `focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:outline-none` on all interactive elements.

---

## 7. Component Pattern Library

Canonical implementations for recurring UI patterns. Every developer must use these — never invent alternatives.

### 7.1 Goal Selector Modal

**Trigger:** "Set Intervention Goal" button or empty state CTA on Intervention Tab 1.
**Container:** Full-screen modal overlay (`fixed inset-0 z-50 bg-black/40 backdrop-blur-sm`). Inner panel: `max-w-2xl`, `rounded-2xl`, `bg-white`, `shadow-2xl`.
**Goal grid:** 3-column card grid. Each card: icon + goal name + 1-line description. Selected state: `border-emerald-600 bg-emerald-50 ring-2 ring-emerald-500/20`.
**Stage reveal:** After goal selection, a `<select>` or shadcn `<Select>` component slides in below the selected card using `transition-all duration-150`. Label: "Disease Stage / Severity". Never shown before goal selection.
**Goals with stages:** `renal_diet`, `cardiac_diet`, `weight_loss`, `weight_gain`, `high_protein`, `fluid_restriction`, `liver_disease`, `malnutrition`.
**Goals without stages:** `diabetic_control`, `custom` — stage row hidden entirely.
**Confirm:** Primary button "Apply Goal". Disabled until goal is selected.
**Cancel:** Secondary or X button. Resets selections.

### 7.2 Micronutrient Display Toggle

**Trigger:** "Display Micros" button (secondary style, small, with `FlaskConical` icon) in the Nutrition Prescription section.
**Popover:** shadcn `<Popover>` component. Content: scrollable checklist of all 19+ tracked micronutrients.
**Auto-flagging:** When `goal_type` is set, relevant micros are pre-checked:

| goal_type | Pre-checked micros |
|---|---|
| `renal_diet` | Potassium, Phosphorus, Sodium, Fluid |
| `diabetic_control` | (none — carb targets handled in macro section) |
| `cardiac_diet` | Sodium, Cholesterol |
| `weight_loss` | Fiber |
| `weight_gain` | (none) |
| `high_protein` | (none) |
| `fluid_restriction` | Fluid |
| `liver_disease` | Sodium |
| `malnutrition` | (none — energy/protein priority) |
| `custom` | (none — RND selects manually) |

**Persistence:** Checked state written to `interventions.displayed_nutrients` (json array of micro names). Loaded on page mount.
**Display:** Each checked micro appears as an editable number input row in the Prescription section, below the macro inputs. Label, input, unit, and (if applicable) limit type (max/min).

### 7.3 Prescription Number Input Row

Used for macros and micros in the Nutrition Prescription form.

```
[Label]          [______] [unit]   [min/max badge if applicable]
Energy           [1800  ] kcal
Protein          [  54  ] g
Potassium        [ 2000 ] mg       [max]
```

- Input: right-aligned number, monospace font, `w-24`
- Unit: muted zinc text, `text-xs`
- Limit badge: `rounded-full text-[9px] font-bold px-1.5` — amber for "max", blue for "min"
- Out-of-range value: red border + red text on the input
- All inputs editable by RND regardless of auto-filled value

### 7.4 Sticky Macro Tracker Bar

Position: `sticky top-0 z-10` inside the tab content area, below the tab strip.
Background: `bg-emerald-50 border-b border-emerald-100` — visually distinct but not disruptive.
Content: current vs target for each displayed nutrient (macros always shown; micros shown if checked).

```
Energy  1420 / 1800 kcal  ●  Protein  42 / 54g  ●  Carbs  190 / 270g  ●  Fat  38 / 50g
```

Color logic per nutrient pill:
- Green (`text-emerald-700`): current within ±10% of target
- Amber (`text-amber-600`): current within ±20% of target
- Red (`text-red-600`): current off by >20% OR below absolute floor

### 7.5 Recommend / Avoid Panel

Two-column layout within the page (not a modal). Appears below Nutrition Prescription, above Meal Plan.
Left column: "Recommend" — green left border cards. Each card: food/nutrient name + reason chip.
Right column: "Avoid" — red left border cards. Same structure.
Source: algorithm-driven only (RecommendService). Never AI-generated content here.
Empty state per column: muted text "No specific recommendations for this goal."
If no goal set: entire panel hidden behind placeholder "Set an intervention goal to see recommendations."

### 7.6 Section Collapse / Summary Chip

After confirming a multi-step section (Goal Selector, Prescription):
```tsx
<div className="flex items-center justify-between px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl">
  <div className="flex items-center gap-2">
    <CheckCircle2 className="h-4 w-4 text-emerald-600" />
    <span className="text-xs font-bold text-emerald-800">{summary}</span>
  </div>
  <button className="text-xs font-semibold text-emerald-700 hover:underline">Edit</button>
</div>
```

### 7.7 Empty / Placeholder States

Consistent structure for all empty states:
```tsx
<div className="bg-zinc-50 border border-zinc-200 rounded-2xl p-10 text-center">
  <Icon className="h-8 w-8 text-zinc-300 mx-auto mb-3" />
  <p className="text-sm font-bold text-zinc-700">[Primary message]</p>
  <p className="text-xs text-zinc-400 mt-1">[Secondary guidance]</p>
  <Button className="mt-5">CTA if applicable</Button>
</div>
```

### 7.8 Toast Notifications

Use a toast library (e.g., `sonner` or shadcn toast). Standard durations:
- Success: 3 seconds, auto-dismiss
- Error: 6 seconds, manual dismiss required
- Info: 4 seconds, auto-dismiss

Position: bottom-right (`fixed bottom-4 right-4`). Stack vertically if multiple.

---

## 8. Accessibility Standards

All components must meet WCAG 2.1 AA. Built on Radix UI primitives (via shadcn/ui) which handle most accessibility automatically.

### 8.1 Semantic HTML

- Use `<button>` for actions, `<a>` for navigation. Never `<div onClick>`.
- Use `<table>` for tabular data (lab values, macro tables). Never CSS grids pretending to be tables.
- Use `<label>` explicitly associated with every `<input>` via `htmlFor` / `id`.
- Use landmark roles: `<main>`, `<nav>`, `<header>`, `<section aria-label>`.

### 8.2 Color Contrast

- All text on white/zinc-50 backgrounds: minimum 4.5:1 contrast ratio (AA).
- Emerald-600 (`#059669`) on white passes AA for large text; pair with dark text for small labels.
- Status colors (red/amber/green) must never be the ONLY indicator — always pair with text or icon.

### 8.3 Focus Management

- Modals: on open, focus moves to first interactive element. On close, focus returns to trigger element.
- Dropdowns: Escape closes and returns focus to trigger.
- Keyboard shortcut: `Tab` navigates forward, `Shift+Tab` backward, through all interactive elements in logical order.

### 8.4 Screen Reader Support

- Icons without visible text label must have `aria-label` or `title` on the parent button.
- Loading states: `aria-busy="true"` on the loading container.
- Dynamic content updates: use `aria-live="polite"` for non-critical updates (toast success), `aria-live="assertive"` for errors.
- Status badges and colored indicators: include `aria-label` describing the status in words.

---

## 9. Component Stack Reference

### 9.1 Approved Libraries

| Purpose | Library | Installation |
|---|---|---|
| UI primitives | shadcn/ui (Radix UI) | `npx shadcn@latest add [component]` |
| Styling | Tailwind CSS v4 | Already installed |
| Icons | Lucide React | Already installed |
| Forms | react-hook-form + zod | Already installed |
| Toast notifications | sonner | `pnpm add sonner` |
| Date picker | shadcn Calendar + Popover | `npx shadcn@latest add calendar popover` |

### 9.2 shadcn/ui Components in Use

Install on demand. Add to `frontend/components/ui/`. Current approved list:

```bash
npx shadcn@latest add button card dialog select checkbox popover
npx shadcn@latest add form input label badge tabs separator
npx shadcn@latest add collapsible tooltip sheet
```

Never install a new UI library without updating this list.

### 9.3 Tailwind Class Conventions

- Interactive base: `cursor-pointer transition-colors`
- Focus ring: `focus-visible:ring-2 focus-visible:ring-emerald-500/20 focus-visible:outline-none`
- Disabled: `disabled:opacity-50 disabled:cursor-not-allowed`
- Card: `bg-white border border-zinc-200 rounded-2xl shadow-sm`
- Section header: `text-xs font-bold text-zinc-500 uppercase tracking-wider`
- Primary action: `bg-emerald-600 hover:bg-emerald-700 text-white`
- Danger action: `text-red-600 hover:bg-red-50`
- Muted text: `text-zinc-400 text-xs`
- Monospace data: `font-mono text-zinc-900`