# NutriScope FSS Mobile UI Kit

Improved, on-brand recreation of the Food Service Supervisor mobile app
(Expo / React Native source in `mobile/`). Shown in a phone frame; open
`index.html` and tap the bottom tabs.

## What changed vs. the shipped app
- **Warm green-tinted surfaces** instead of cold `gray-50`.
- **Brand accents** — the off-brand purple announcements (`#7c3aed`) are replaced
  with NutriScope green / lime / orange.
- Softer 16px cards with subtle shadows; lively KPI chips with icon tiles.
- Green active state on the bottom tab bar and filter chips.

## Screens (`MobileApp.jsx`)
- **Dashboard** — KPI chips (meals to log, POs awaiting, out of stock), today's
  service list with prepped/shortfall pills, on-brand announcements feed.
- **Inventory** — search, type filter chips, stats strip, stock rows with status
  pills (mirrors the real adjust-stock list).
- **Menu** — day selector, cost/head + kcal tiles, per-meal breakdown in ₱.
- **Prep** — placeholder.

Icons are Lucide (CDN); the brand mark is `assets/mark.svg`. Colors come from the
DS tokens via `styles.css`.
