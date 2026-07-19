# Food Service — Current Cross-Role Operations

Verified against current RND web, FSS mobile, and Laravel food-service routes on **2026-07-19**.

## Food Planning and Execution

```mermaid
flowchart TD
    subgraph RND["RND Web — planning owner"]
        A["Inventory reference catalog"] --> B["Foods and recipes"]
        B --> C["Create or template Menu Cycle"]
        C --> D["Set week, meal slots, estimated population/servings"]
        D --> E["Review scaled ingredients, cost, cost/head, prep notes"]
        E --> F["Save and activate cycle"]
        F --> G["Generate food shopping list for date span"]
        G --> H{"Every date covered with items and population?"}
        H -->|"No"| I["Show exact missing dates; correct cycle"]
        I --> G
        H -->|"Yes"| J["Review quantities, unit cost, vendors"]
        J --> K["Convert to one PO with vendor groups"]
    end

    subgraph SYSTEM["NutriScope"]
        K --> L["Freeze PO structure and menu-day snapshots"]
        L --> M["PO status: open_execution"]
    end

    subgraph FSS["FSS Mobile — execution owner"]
        M --> N["View PO and vendor groups"]
        N --> O["Save OR number"]
        O --> P["Upload receipt and optional proof"]
        P --> Q["Receipt marks vendor group received"]
        F --> R["View active menu read-only"]
        R --> S["Prepare/serve today's meals"]
        S --> T["Record actual served population"]
        T --> U["Save daily accomplishment"]
    end

    Q --> V{"All receipts present?"}
    T --> W{"All covered food-service dates have population?"}
    V -->|"No"| M
    W -->|"No"| M
    V -->|"Yes"| X
    W -->|"Yes"| X["Complete food PO"]
    X --> Y["Calculate actual cost/head/day and budget event"]
    Y --> Z["RND operational reports and closeout"]
```

## Supplies Procurement

```mermaid
flowchart LR
    A["RND creates manual Supplies List"] --> B["Add catalog supplies, quantity, cost, vendor"]
    B --> C["Convert to supplies PO with vendor groups"]
    C --> D["FSS saves OR and uploads receipts/proof"]
    D --> E{"All vendor receipts present?"}
    E -->|"No"| D
    E -->|"Yes"| F["Complete supplies PO"]
```

Supplies completion does not require served population.

## Menu and Population Data

```mermaid
flowchart LR
    A["RND estimated population"] --> B["Menu scaling and shopping-list estimate"]
    B --> C["Estimated budget/head/day"]
    D["FSS actual served population"] --> E["PO completion check"]
    D --> F["Actual budget/head/day"]
```

## Ownership Matrix

| Operation | RND | FSS | Admin |
|---|---:|---:|---:|
| Maintain reference Inventory | Edit | No mobile surface | No |
| Maintain food-service Foods/recipes | Edit | View through menu profile | No |
| Create/activate Menu Cycle | Yes | View only | No |
| Record actual served population | Yes | Yes | No |
| Generate/edit shopping list | Yes | No | No |
| Convert to PO | Yes | No | No |
| Save vendor OR number | Yes | Yes | No |
| Upload receipt/proof | Yes | Yes | No |
| Edit PO price/structure | Authorized RND only while open | No | No |
| Budget setup/manual adjustment | Yes | No | Read-only oversight |
| Budget-per-head/day setting | Yes | No | Yes |
| Operational reports | Full RND catalog | Own accomplishment only | Allowed aggregate/operational catalog |

## Current Corrections to Older Diagrams

- Inventory is a reference catalog, not live stock quantity.
- FSS has no Inventory tab or stock add/deduct workflow.
- FSS does not edit vendor line items or manually mark vendor received.
- Receipt upload is the receiving event.
- Food and supplies use separate lists/PO tracks.
- Food list generation blocks the whole request when any date is incomplete.
- `/food-service/foods` is implemented.

## Related Documents

- [RND Module](../rnd.md)
- [FSS Module](../fss.md)
- [FAQ](../../FAQ.md)
- [Role How-To](../../ROLE-HOW-TO.md)
- [Storyboards](../../STORYBOARD.md)
