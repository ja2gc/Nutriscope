# UI/UX for Web and Mobile App

Read for any frontend or mobile screen, navigation, form, or visible-copy change. Apply the shared section and the matching platform section. Preserve the existing product workflow and visual language unless the task explicitly changes them.

## Shared principles — every screen

### Do

- Identify the user's role, current task, and next valid action. Keep the primary action and essential choices visible; group other decisions and reveal secondary detail when needed.
- Keep navigation, labels, and repeated actions consistent. Prefer an existing page and list-to-detail flow before adding a destination, tab, or modal.
- Use alignment, spacing, and containment to group related information and separate unrelated content. Show the dates, status, and context needed for a decision without making users remember another screen.
- Use short, task-based labels. Explain an error with a recovery action near the affected task.
- Inspect existing components, styles, hooks, formatters, validation, and API helpers before adding any. Reuse a fitting component; extend shared code only when its responsibility remains clear.
- Show loading, empty, success, error, and read-only states promptly where they affect decisions. Keep entered values after validation errors and make state changes visible.
- Make important controls easy to select and separate adjacent actions, especially destructive ones. Give controls accessible names and make status understandable without color alone. Check the rendered screen with realistic content, long labels, and enlarged text.

### Don't

- Do not crowd one screen with every field, report, explanation, and action. Keep a clear route to details.
- Do not expose internal IDs, flags, debug labels, or instructions that merely repeat a visible control.
- Do not create duplicate components, helpers, forms, or workflows because an existing one needs a small adaptation.
- Do not hide required actions behind gestures or unlabeled icons, or use a disabled control as the only explanation of what is missing.

## Responsive web — desktop and mobile browsers

### Do

- Start at a narrow viewport and change layout when the **content** needs room, rather than targeting named devices. Let columns, forms, actions, and text reflow as the viewport changes.
- Preserve ordinary content and actions at 320 CSS px and with text enlarged to 200%. A genuinely dense data table may scroll horizontally within its own region.
- Keep navigation order and action-to-content relationships clear across breakpoints. Give inputs persistent visible labels, associated errors, keyboard access, and visible focus.
- Reuse `frontend/components/ui/` controls, including `DatePicker`, `CharacterCountTextarea`, `EmptyState`, `StatusBadge`, and existing form controls.
- Check narrow and wide rendered pages, browser zoom/text scaling, keyboard-only use, touch use, and loading/empty/error states.

### Don't

- Do not shrink a desktop table or action row until text clips or controls overlap; reflow or contain a necessary table scroll.
- Do not rely on hover, color, placeholder text, or an icon alone to communicate an action or status.
- Do not use fixed-width panels or sticky controls that obscure focused content on small screens.

## Mobile app — Expo/React Native

### Do

- Use the existing app navigation and screen patterns. On a compact phone, show a focused list or detail at a time; let an item open its days/details with a clear way back. Adapt the layout for larger screens.
- Respect safe areas, system bars, orientation, the on-screen keyboard, and text scaling. Keep key actions reachable when content grows or the keyboard opens.
- Make touch targets at least 48 × 48 dp on Android and aim for at least 44 × 44 pt on iOS, including icon buttons. Separate nearby controls and show press feedback.
- Use accessible labels, roles, and states. Provide a visible alternative to gesture-only actions and communicate progress/status in text.
- Reuse `mobile/components/`, existing date/navigation helpers, query/loading patterns, and report screens. Keep native behavior in the app; do not import a web component because its name matches.
- Verify the real Android emulator screen with normal and enlarged text, long lists, empty/error states, keyboard open, and task-specific actions. Follow `mobile-testing.md`.

### Don't

- Do not add a top-level destination for detail that fits an existing task area or pile every period, day, and action onto one screen.
- Do not place controls under system bars, behind the keyboard, or outside the reachable scroll area.
- Do not rely on tiny icons, swipe-only actions, or truncated text to explain a workflow state.
