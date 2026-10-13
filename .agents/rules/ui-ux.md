# Web and Mobile UI/UX

Read for any frontend or mobile screen, navigation, form, or visible copy change. Apply these rules within the existing product workflow and component system; do not redesign unrelated screens.

## Do

- Start with the user's role, immediate task, and next valid action. Put the most useful status and action first; group related details and reveal secondary information in a detail view or disclosure when needed.
- Keep navigation and repeated actions in familiar, consistent locations. Prefer the existing page, tab, and list-to-detail pattern before adding a destination or another layer of tabs.
- Use clear, short, task-based labels. Show human-readable dates, progress, and statuses; explain errors with a recovery action. Keep internal IDs, flags, and debug terms out of normal UI.
- Inspect and reuse existing components, styles, hooks, formatters, validation, and API helpers before adding anything. On web, check `frontend/components/ui/` (including `DatePicker`, `CharacterCountTextarea`, `EmptyState`, `StatusBadge`, and form controls). On mobile, check existing screens and `mobile/components/`. Adapt an existing component when its responsibility truly fits; keep platform-specific behavior on its platform.
- Design responsive web layouts around content, starting narrow and changing layout where content needs room. Reflow ordinary content at 320 CSS px and at 200% text size; reserve horizontal scrolling for content that genuinely needs two dimensions, such as a dense data table.
- On mobile, respect safe areas, system bars, keyboard, text scaling, and both compact and larger screens. Keep key actions reachable and tappable; aim for at least 44 × 44 pt on iOS and 48 × 48 dp on Android.
- Give controls visible labels and accessible names. Preserve keyboard access and visible focus on web. Show required fields, character limits, loading, success, empty, and error states where the user acts; retain entered values after a validation error. Communicate status in text, not color alone.
- Verify the actual screen with realistic content, long labels, empty and error states, narrow and wide viewports, enlarged text, keyboard navigation, and touch/mobile interaction as relevant to the change.

## Don't

- Do not crowd one screen with every field, action, report, and explanation; prioritize the current task and allow a clear path to detail.
- Do not add a new tab, modal, component, helper, dependency, or parallel flow solely to avoid using an existing fitting pattern.
- Do not hide required actions behind gesture-only controls, use icon-only actions without accessible labels, or rely on a disabled button as the only explanation of what is missing.
- Do not use fixed widths or clipping that lose content on small screens or with enlarged text. Do not substitute placeholder text for a field label.
- Do not add instructional copy that merely repeats a visible label or obvious consequence.

## Primary guidance

- [W3C WCAG 2.2: reflow, focus, target size, forms, and status](https://www.w3.org/WAI/WCAG22/Understanding/)
- [W3C form labels and validation](https://www.w3.org/WAI/tutorials/forms/)
- [web.dev responsive design basics](https://web.dev/articles/responsive-web-design-basics)
- [Apple Human Interface Guidelines: layout](https://developer.apple.com/design/human-interface-guidelines/layout)
- [Apple Human Interface Guidelines: buttons](https://developer.apple.com/design/human-interface-guidelines/buttons)
- [Android adaptive navigation and layouts](https://developer.android.com/design/ui/mobile/guides/layout-and-content/layout-and-nav-patterns)
- [Android accessibility and touch targets](https://developer.android.com/guide/topics/ui/accessibility/apps)
