## Goal
Update `docs/ui/design-system.md` to completely and accurately document the implemented design system from Milestone 1. The documentation must serve as a high-fidelity reference guide for future clinical frontend modules (Milestones 2-6), specifying exact Tailwind CSS classes, color codes, component standards, and copy style rules to guarantee absolute visual and operational consistency.

## Assumptions
- The design system documentation is located at [design-system.md](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/docs/ui/design-system.md).
- The current implementation uses native, built-in Tailwind CSS utility classes (zinc, emerald, orange) instead of hot-reload-dependent custom variables.

## Plan

1. **Document Brand Assets & Logo Layout**
   - **Files**: `docs/ui/design-system.md`
   - **Change**: 
     - Add detailed guidelines for the custom `<Logo />` component.
     - Document raw SVG specifications (leaf path values, orange concentric lens circles, crosshair ticks) to preserve brand asset integrity.
     - Detail split wordmark color applications: "Nutri" in `text-emerald-600` (Emerald Green) and "Scope" in `text-orange-600` (Tangerine Orange) / `text-zinc-100` (in dark contexts).
   - **Verify**: View edited file to ensure brand assets are correctly documented.

2. **Document Sidebar & Layout Canvas Styling**
   - **Files**: `docs/ui/design-system.md`
   - **Change**: 
     - Outline standard canvas base styles (`bg-zinc-50 text-zinc-900`).
     - Detail dark Sidebar shell properties (`bg-zinc-950 border-r border-zinc-900`).
     - Specify active navigation states (`bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600`) and hover states (`text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200`).
     - Outline exact non-boilerplate icon classes (using Lucide `Compass`, `CookingPot`, `HeartHandshake`, `Salad`, `TrendingUp`, `CalendarDays`, `BellDot`, `Sliders`).
   - **Verify**: Check layout specifications in markdown file.

3. **Document Form Inputs & Reusable Action Buttons**
   - **Files**: `docs/ui/design-system.md`
   - **Change**: 
     - Document exact classes for forms: Labels in semibold zinc-600 (not uppercase). Inputs styled as `rounded-lg border-zinc-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all`.
     - Document button classes: Primary (`bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800`), Secondary (`bg-white border-zinc-250 hover:bg-zinc-50 text-zinc-700`), and Danger states with rounded-lg structures and custom transition speeds.
   - **Verify**: Read the form components section of the document.

4. **Document Copywriting & Session Context Rules**
   - **Files**: `docs/ui/design-system.md`
   - **Change**: 
     - Define dynamic top bar module title casing rules.
     - Document dynamic, auth-aware sidebar footer schemas (`Active Session: [Name] ([Role])` using standard zinc highlights).
     - Standardize clinical status colors: High Risk (`bg-red-50 text-red-700 border-red-100`), Warning/Pending (`bg-orange-50 text-orange-700 border-orange-100`), Normal/Success (`bg-emerald-50 text-emerald-700 border-emerald-100`).
   - **Verify**: Confirm all copy schemas are clearly tabulated.

## Risks & mitigations
- **Risk**: Inconsistent styling in future milestones due to obscure or ambiguous documentation guidelines.
- **Mitigation**: Write highly granular, copy-pasteable class tables for all key visual sections (Layout, Buttons, Inputs, Indicators).

## Rollback plan
- Discard documentation changes:
  `git checkout -- docs/ui/design-system.md`
