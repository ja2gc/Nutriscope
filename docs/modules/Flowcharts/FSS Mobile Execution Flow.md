# FSS Mobile — Current Execution Flow

Verified against current Expo Router screens on **2026-08-21**.

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
    D --> H2["Announcement: Announcements tab + SOP tab"]
    D --> H3["Menu"]
    D --> H4["Meal Prep"]
    D --> H5["Accomplish"]
    D --> H6["Purchase"]

    D --> X1["Header bell: Notifications"]
    D --> X2["Header profile: Side menu"]
```

Bottom navigation has six tabs: Home, Announcement, Menu, Meal Prep, Accomplish, Purchase.

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
    D --> D1["Review selected planned service date"]
    D1 --> D2["Record or update actual population served"]
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
    C --> C2{"Purchase used another vendor?"}
    C2 -->|"Whole group"| C3["Change vendor for all"]
    C2 -->|"One item"| C4["Change vendor on item row"]
    C2 -->|"No"| D
    C3 --> D["Review planned values; expand calculation details only if needed"]
    C4 --> D
    D --> E["Confirm actual quantity and unit price"]
    E --> F["Upload receipt and proof"]
    F --> G["Optionally enter OR number"]
    G --> H["Explicitly mark vendor received"]
    H --> I{"PO completed or archived?"}
    I -->|"Yes"| J["Edits locked"]
    I -->|"No"| K["Continue remaining vendor/date requirements"]
```

FSS can correct actual quantities/prices and the actual vendor while the group is open and has no evidence. **Change vendor for all** applies to the group; row-level **Change vendor** moves only that item. Planned quantities, units, and calculated need stay frozen. Receipt and proof do not change status until **Mark vendor received** is used. OR number is optional.

## Accomplishment Report Lifecycle

```mermaid
flowchart LR
    A["Daily Log: today or a past date"] --> B{"One entry for every Monday-Sunday day?"}
    B -->|"No"| C["Weekly report remains incomplete"]
    B -->|"Yes"| D["Archive frozen accomplishment report"]
    D --> E["FSS views own report and opens or saves PDF"]
    D --> F["RND/Admin may view within allowed report scope"]
```

Off-duty entries count and render as X. FSS report reads are owner-scoped.

## Communication and Account

```mermaid
flowchart TD
    A["Announcement bottom tab"] --> B["Announcements internal tab"]
    A --> C["SOP internal tab"]
    C --> D["Current SOP and SOP History"]
    E["Header bell"] --> F["Unread notifications"]
    F --> G["Open target or mark read"]
    H["Header profile"] --> I["Side menu"]
    I --> J["Profile"]
    I --> K["Notifications"]
    I --> O["Help"]
    O --> P["Search Shared and FSS-only answers"]
    I --> L["Settings"]
    I --> M["Check for updates"]
    I --> N["Sign out"]
```

## Current Corrections to Older Flow

- Added separate Accomplish tab.
- Removed Inventory tab and all stock add/deduct actions.
- Menu is a first-class bottom tab, not only a Prep link.
- Purchase label is current bottom-tab label; screen title remains Procurement.
- Accomplishment entry is rendered on Accomplish, not Meal Prep.
- Actual values, receipt, proof, and an explicit receiving action are required; OR is optional.
- Announcement is the second bottom tab; Help remains in the profile side menu and Settings.

## Related Documents

- [FSS Module](../fss.md)
- [Food Service Flow](Food%20Service%20Operations.md)
- [FAQ](../../FAQ.md)
- [Role How-To](../../ROLE-HOW-TO.md)
- [Storyboards](../../STORYBOARD.md)
