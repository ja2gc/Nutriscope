# FSS Mobile — Current Execution Flow

Verified against current Expo Router screens on **2026-07-20**.

## Access and Navigation

```mermaid
flowchart TD
    A["Launch FSS mobile app"] --> B{"Saved valid token?"}
    B -->|"No"| C["FSS sign-in"]
    B -->|"Yes"| D["Authenticated app"]
    C --> E{"First-login setup required?"}
    E -->|"Yes"| F["Set new password and recovery email, or defer"]
    E -->|"No"| D
    F --> D

    D --> H1["Home"]
    D --> H2["Menu"]
    D --> H3["Meal Prep"]
    D --> H4["Accomplish"]
    D --> H5["Purchase"]

    D --> X1["Header: Announcements and SOP"]
    D --> X2["Header: Notifications"]
    D --> X3["Header: Settings, Help, and Profile"]
```

Bottom navigation is exactly five tabs: Home, Menu, Meal Prep, Accomplish, Purchase.

## Daily Execution

```mermaid
flowchart TD
    A["Home"] --> A1["Review meals to log today"]
    A --> A2["Review pending POs and waiting reasons"]
    A --> A3{"Active menu cycle exists?"}
    A3 -->|"No"| A4["Contact RND"]
    A3 -->|"Yes"| B["Menu"]

    B --> B1["Open current day and meal slot"]
    B1 --> B2["Read recipe/item ingredients, cost, prep notes"]
    B2 --> C["Purchase receiving when deliveries arrive"]
    C --> D["Meal Prep"]
    D --> D1["Review today's service"]
    D1 --> D2["Enter actual total population"]
    D2 --> D3{"Shortfall warning?"}
    D3 -->|"Yes"| D4["Confirm accurate exception or cancel"]
    D3 -->|"No"| D5["Mark served/complete"]
    D4 --> D5
    D5 --> E["Accomplish"]
    E --> E1{"Off duty?"}
    E1 -->|"Yes"| E2["Save X with zero meals"]
    E1 -->|"No"| E3["Enter ward and distributed meals"]
    E3 --> E4["Select seven completed-duty flags"]
    E4 --> E5["Save accomplishment"]
    E2 --> E5
```

## Purchase Receiving

```mermaid
flowchart TD
    A["Purchase tab"] --> B["Open PO"]
    B --> C["Open vendor group"]
    C --> D["Review calculated vendor lines"]
    D --> E["Confirm actual quantity and unit price"]
    E --> F["Upload receipt and proof"]
    F --> G["Optionally enter OR number"]
    G --> H["Explicitly mark vendor received"]
    H --> I{"PO completed or archived?"}
    I -->|"Yes"| J["Edits locked"]
    I -->|"No"| K["Continue remaining vendor/date requirements"]
```

FSS can correct actual quantities and prices while open, but cannot change planned structure or supplier. Receipt and proof do not change status until **Mark vendor received** is used. OR number is optional.

## Accomplishment Report Lifecycle

```mermaid
flowchart LR
    A["Daily Accomplish entry"] --> B{"One entry for every Monday-Sunday day?"}
    B -->|"No"| C["Weekly report remains incomplete"]
    B -->|"Yes"| D["Archive frozen accomplishment report"]
    D --> E["FSS opens own report from My reports"]
    D --> F["RND/Admin may view within allowed report scope"]
```

Off-duty entries count and render as X. FSS report reads are owner-scoped.

## Communication and Account

```mermaid
flowchart TD
    A["Header megaphone"] --> B["Current SOP"]
    A --> C["SOP History"]
    A --> D["FSS/All announcements"]
    E["Header bell"] --> F["Unread notifications"]
    F --> G["Open target or mark read"]
    H["Header account"] --> I["Settings"]
    I --> J["Density and reduced motion"]
    I --> K["Mark all notifications read"]
    I --> O["Help and Support: Help"]
    O --> P["Search Shared and FSS-only answers"]
    I --> L["Profile"]
    L --> M["Name, sign-in/recovery email, contact, password"]
    I --> N["Sign out"]
```

## Current Corrections to Older Flow

- Added separate Accomplish tab.
- Removed Inventory tab and all stock add/deduct actions.
- Menu is a first-class bottom tab, not only a Prep link.
- Purchase label is current bottom-tab label; screen title remains Procurement.
- Accomplishment entry is rendered on Accomplish, not Meal Prep.
- Actual values, receipt, proof, and an explicit receiving action are required; OR is optional.
- Help is reached from Settings and does not add a sixth bottom tab.

## Related Documents

- [FSS Module](../fss.md)
- [Food Service Flow](Food%20Service%20Operations.md)
- [FAQ](../../FAQ.md)
- [Role How-To](../../ROLE-HOW-TO.md)
- [Storyboards](../../STORYBOARD.md)
