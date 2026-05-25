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
- Typography: Clean, sans-serif (Inter or Roboto). High-contrast weights for data readability. Tabular lining and monospace for lab values, IDs, and financial data to ensure vertical alignment.
- Color Palette:
  - Primary: Trustworthy clinical blue/teal (Actionable).
  - Backgrounds: Pure white for content cards, very light gray for canvas background to separate sections.
  - Semantic Status: Red (Critical/Low/Allergy), Amber/Yellow (Warning/Expiring/Pending), Green (Normal/Ready/Completed), Slate/Gray (Draft/Inactive).
- Spacing Framework: Dense, strict 4px/8px baseline grid to allow maximum data visibility without clutter.

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

Apply UI UX Pro max skill