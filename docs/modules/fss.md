# FSS Module — Current Role and Workflow

Verified against current Expo navigation, rendered screens, and Laravel role gates on **2026-08-15**.

## Role Purpose

FSS executes daily food-service work prepared by RND:

- view active/saved menu cycles and food profiles;
- confirm actual purchase values and receive PO vendor groups with receipt/proof evidence; OR number is optional;
- record actual served population and meal-service completion;
- log daily ward meals and seven accomplishment duties;
- view own archived accomplishment reports;
- read announcements, notifications, current SOP, and SOP history.

FSS does not author menus, shopping lists, POs, suppliers, budgets, food catalogs, recipes, or clinical/NCP records.

## Platform Gate

FSS signs in through the mobile app. Laravel rejects FSS web login. It also rejects RND/Admin login from the FSS app. An active account with role `FSS` is required.

## Current Mobile Navigation

There are **five** bottom tabs, in this order:

1. **Home**
2. **Menu**
3. **Meal Prep**
4. **Accomplish**
5. **Purchase**

This replaces older documentation showing four tabs or an Inventory tab. Header actions on every tab open:

- megaphone: Announcements and SOP;
- bell: Notifications;
- account: Settings, Help, and Profile.

Help is deliberately not a sixth bottom tab. Open **Settings → Help & Support → Help** to search Shared and FSS-only guidance.

## Recommended Daily Sequence

```mermaid
flowchart LR
    A["Home: review queues"] --> B["Menu: review approved plan"]
    B --> C["Purchase: receive vendor groups"]
    C --> D["Meal Prep: serve and record actual population"]
    D --> E["Accomplish: record duties and meals"]
    E --> F["Own weekly report"]
```

## Home

Current content:

- meals to log today;
- pending PO count linking to Purchase;
- active menu-cycle card linking to Menu;
- per-PO waiting labels such as **Needs receipts** and **Needs served population**;
- today's service rows;
- role-visible announcement feed.

If no active cycle exists, Home tells FSS to contact RND.

## Menu

FSS can browse cycles and open meal slots. Recipe/item profiles show scaled ingredients, quantities, cost, cost/head, and preparation notes. Planning fields remain read-only.

FSS can set/backfill actual served population for a cycle date. This contributes to suggested-food PO completion and the final food purchase cost per served patient-day.

## Meal Prep

Current rendered page contains today's meal-service workflow:

- planned service rows and food profiles;
- actual total patient population;
- served/prepped status;
- completion action;
- shortfall confirmation when required.

The source file still contains an older `AccomplishmentSection` function, but it is **not rendered** by the current Meal Prep screen. Daily accomplishment entry belongs to the separate **Accomplish** tab.

## Accomplish

FSS records:

- service date (today);
- ward;
- meals distributed/population;
- off-duty/absent state;
- seven duty flags:
  1. helped prepare food;
  2. stored food supplies properly;
  3. collected ward diet lists;
  4. apportioned and distributed meals;
  5. cleaned and returned utensils/equipment;
  6. worked as assistant cook;
  7. checked kitchen/cold-storage cleanliness.

Off duty stores zero meals and renders as **X** in the weekly report. The tab also shows today's logged-meal total and links to **My reports**.

### Weekly Accomplishment Archive

- Week is Monday through Sunday.
- The current FSS user needs an entry for each of the seven days.
- Off-duty entries count as daily completion.
- The archived report is frozen against later source edits.
- FSS sees only their own archived accomplishment reports.
- RND/Admin may view accomplishment reports within their allowed report scope.

## Purchase

FSS sees existing POs and vendor groups. For an open PO, FSS can:

- review the original calculated vendor line values;
- confirm or correct actual quantity (including decimals) and actual unit price;
- optionally save `or_number`;
- upload receipt images;
- upload proof-of-purchase images;
- add captions;
- view or delete attachments while unlocked;
- explicitly mark the vendor received once requirements are complete.

Receipt upload alone does not change status. A vendor can be marked received only after supplier assignment, reviewed actual values, at least one receipt, and at least one proof-of-purchase image. OR number is not required.

FSS cannot change:

- planned vendor items and calculated values;
- supplier;
- lifecycle status.

Completed/archived POs lock execution edits.

### PO Completion

- Suggested food PO: every vendor must be explicitly received with actuals, receipt, and proof; every covered service date also needs actual served population.
- Manual food and supplies PO: every vendor must meet the same receiving evidence; served population is not required.

## Announcements and SOP

The megaphone page shows:

- current SOP;
- paginated SOP version history;
- FSS/All announcements;
- announcement details and images.

FSS is read-only. RND/Admin revise SOP and publish announcements.

## Notifications

FSS notifications support pagination, unread badge/count, mark-read/open behavior, mark-all-read, and navigation to supported targets such as announcements or procurement.

## Reports

FSS report access is limited server-side to `accomplishment_report`. The mobile report viewer lists only the signed-in FSS user's reports and renders frozen weekly accomplishment data.

## Settings and Profile

Settings supports:

- comfortable/compact display density;
- reduced motion;
- mark all notifications read;
- Help link under Help & Support;
- Profile link;
- sign out.

Profile supports first/last name, sign-in email, contact number, recovery email setup/change/verification, and password change. Role and status are read-only.

First-login accounts must replace the temporary password and add a recovery email. Setup can be deferred, but the header reminder remains until complete.

## Data Scope and Safety

- FSS diet-list/accomplishment reads are scoped to the signed-in FSS user.
- FSS report access is own accomplishment reports only.
- FSS has no patient/NCP clinical route.
- Server role middleware enforces write restrictions even if a client is modified.
- Purchase-order activity uses structured audit data, not raw properties or file contents.

## Removed or Not Current

- Inventory bottom tab
- stock add/deduct controls
- Suppliers tab
- Budget tab
- analytics/Insights tab
- shopping-list or PO authoring
- editing planned PO structure
- FSS web console login

## Related User Documents

- [FAQ](../FAQ.md)
- [Role How-To Guide](../ROLE-HOW-TO.md)
- [Storyboards](../STORYBOARD.md)
- [FSS Mobile Flowchart](Flowcharts/FSS%20Mobile%20Execution%20Flow.md)
- [Food Service Flowchart](Flowcharts/Food%20Service%20Operations.md)

## Current Code Evidence

- `mobile/app/(tabs)/_layout.tsx`
- `mobile/app/(tabs)/index.tsx`
- `mobile/app/(tabs)/menu.tsx`
- `mobile/app/(tabs)/prep.tsx`
- `mobile/app/(tabs)/accomplishments.tsx`
- `mobile/app/(tabs)/procurement.tsx`
- `mobile/components/AppHeader.tsx`
- `mobile/app/help.tsx`
- `mobile/components/help/**`
- `mobile/lib/helpContent.ts`
- `mobile/app/announcements.tsx`
- `mobile/app/reports.tsx`
- `backend/routes/api.php`
