# NutriScope Web UI Kit — RND/Admin Console

High-fidelity recreation of the NutriScope web app (Next.js source in
`frontend/`), with the brand uplift applied: **forest-green nav** (was near-black
`zinc-950`), warm neutrals, Plus Jakarta Sans, and the redesigned **split-screen
login**.

## Screens (interactive — open `index.html`)
1. **Login** (`LoginScreen.jsx`) — split landing: forest brand panel with
   fresh-produce imagery + value props on the left, sign-in card on the right.
   Sign in to enter the app.
2. **Dashboard** (`DashboardScreen.jsx`) — RND overview: welcome hero, KPI tiles
   (active patients, cost/head in ₱, meals, out-of-stock), upcoming follow-ups,
   announcements.
3. **Food Service** (`FoodServiceScreen.jsx`) — inventory table with search,
   type filters, stock status badges, and the Menu/Budget/Procurement tab bar.
4. **App shell** (`AppShell.jsx`) — forest sidebar (collapsible groups) + top bar
   with module title, alerts bell, and user card.

## Composition
Screens compose DS primitives from the bundle:
`Button, Input, Card, KpiCard, Badge, StatusBadge, Tabs, Avatar, Logo`.
Icons are Lucide (CDN). Imagery is hotlinked from Pexels.

## Flow
Login → Dashboard. Use the sidebar to switch between **Dashboard** and
**Food Service**. Nav groups (Nutrition Care, Food Service) expand/collapse.
