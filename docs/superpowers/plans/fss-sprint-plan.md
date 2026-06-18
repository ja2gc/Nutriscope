# FSS Mobile App — Sprint Plan & Architecture

> Target: React Native (Expo) Mobile App for Food Service Staff (FSS).
> Role Scope: Operational Execution (CRUD Inventory, Procurement, Suppliers, Meal-Prep, Cleaning Logs. Read-Only: Menu Cycles, Budgets, Food Service Recipes). Enforced by route-group split: RND-only writes nested under `role:RND` inside the `/api/fss/*` group; FSS gets 403 on those writes.

## 1. App Architecture & Navigation

**Tech Stack:** React Native (Expo), NativeWind (Tailwind for React Native), React Query (API caching/offline state), Expo SecureStore (Sanctum token storage).

**Navigation Flow:**
- **Auth Stack:** Login Screen.
- **Main App (Bottom Tab Navigator):**
  1. **Dashboard** (Home)
  2. **Prep & Clean** (Daily Execution)
  3. **Inventory** (Stock Management)
  4. **Procurement** (Receiving & Proofs)
- **Modals/Stacks:** camera/upload (should be the same design with how rnd upload receipts, so FSS and RND use the same upload component to upload receipts) (for receipts), Settings/Profile.

---

## 2. Page Checklists & Tasks

### Tab 1: Dashboard
**Layout:** Vertical scroll. Top greeting, KPI cards grid, followed by Actionable Queues and Announcements feed.
- [ ] **UI:** Build KPI Cards (Meals left to prep, Supplies left to clean).
- [ ] **UI:** Build Alerts Section (POs awaiting receipt).
- [ ] **UI:** Build Announcements Carousel (filtered by `visibility=FSS|All`).
- [ ] **Task:** Wire React Query hooks to fetch daily summary from backend.
- [ ] **Task:** Implement Pull-to-Refresh.

### Tab 2: Prep & Clean (Execution)
**Layout:** Top material top-tab navigator (Meal Prep | Cleaning Log).
- [ ] **UI (Meal Prep):** List of today's meals from the *Active Menu Cycle*. Checkboxes to mark as prepped.
- [ ] **Task:** Connect `POST /menu-cycles/{id}/complete-day` API. Handle shortfall errors (stock too low) with clear alert modals.
- [ ] **Note (Bulk vs Trays):** The FSS app focuses on bulk cooking. Individual patient tray tickets are generated via PDF reports by the RND. Do not build tray-level UI here.
- [ ] **UI (Cleaning Log):** Daily checklist of supplies/equipment to clean.
- [ ] **Task:** Build backend data capture for cleaning log.

### Tab 3: Inventory & Suppliers
**Layout:** Top material top-tab navigator (Ingredients | Supplies | Suppliers).
- [ ] **UI (Ingredients & Supplies):** `FlashList` for high-performance scrolling. Show stock level, unit, and status badge (Red//Green).
- [ ] **UI (Suppliers):** List of vendors with contact info.
- [ ] **Task:** Implement inline stock adjustment modal (Add/Deduct stock).
- [ ] **Task:** Implement Search/Filter bar for quick lookups.

### Tab 4: Procurement (Receiving)
**Layout:** SectionList grouped by PO Status (Ordered, Received).
- [ ] **UI:** List view of POs. Detail view showing items and expected quantities.
- [ ] **UI:** Camera/Upload interface to attach Receipts and Proofs of Purchase.
- [ ] **Task:** Wire `POST /purchase-orders/{id}/attachments` with multipart/form-data for image uploads.
- [ ] **Task:** Wire "Mark as Received" button to trigger inventory restock logic on backend.
