# Admin — Current Operations Flow

Verified against current Admin web pages and Laravel Admin/report guards on **2026-07-20**.

## Admin Navigation and Responsibilities

```mermaid
flowchart TD
    A["Admin web login"] --> B["Admin Dashboard"]
    B --> C["Manage Users"]
    B --> D["Audit Logs"]
    B --> E["AI Usage and Token Caps"]
    B --> F["Announcements and SOP"]
    B --> G["Allowed Reports"]
    B --> H["Read-only Budget"]
    B --> I["Settings and Branding"]
    B --> J["Notifications and Profile"]
    B --> K["Help: Shared and Admin-only guidance"]
```

## Account Lifecycle

```mermaid
flowchart TD
    A["Manage Users"] --> B{"Operation"}
    B -->|"Create"| C["Name, sign-in email, role, status, temporary password"]
    C --> D["Account requires password change and recovery email"]
    D --> E["User signs in on role-correct platform"]
    E --> F{"Complete now?"}
    F -->|"Yes"| G["Save password and recovery email"]
    F -->|"Defer"| H["Workspace opens with persistent reminder"]
    H --> G

    B -->|"Role/status/password change"| I["Save authorized change"]
    I --> J["Revoke user sessions"]
    J --> K["Write structured audit event"]

    B -->|"Reset password"| L["Verify request and set new password"]
    L --> J
    B -->|"Suspend"| M["Deactivate and revoke sessions"]
    M --> K
```

## Audit Oversight

```mermaid
flowchart TD
    A["Audit Logs"] --> B["Choose module and filters"]
    B --> C["Paginated structured event list"]
    C --> D["Open safe event details"]
    D --> E["Open correlated history when available"]
    C --> F["Export only when authorized"]
    A --> G["Review/update approved retention"]
    D --> H["No raw properties, file contents, AI prompts, or clinical value diffs"]
```

## Reports and Clinical Boundary

```mermaid
flowchart TD
    A["Admin Reports"] --> B{"Report type"}
    B -->|"Program Project Activity"| C["Allowed"]
    B -->|"Menu Calendar"| C
    B -->|"Procurement Pack"| C
    B -->|"Accomplishment Report"| C
    B -->|"Aggregate Demographic Census"| C
    B -->|"Patient Menu Plan"| D["Blocked server-side"]
    B -->|"NCP Summary"| D
    C --> E["Live preview"]
    E --> F["Archive approved frozen copy"]
    F --> G["View/download/activity"]
```

## Dashboard, AI, Budget, and Settings

```mermaid
flowchart TD
    A["Dashboard"] --> B["User totals by role"]
    A --> C["Aggregate patients in care"]
    A --> D["AI calls, tokens, estimated cost"]
    A --> E["Audit event volume and recent activity"]
    D --> F["Set daily/monthly caps; blank means unlimited"]
    G["Budget"] --> H["Read fiscal-year summary, ledger, activity"]
    H --> I["No Admin setup/manual adjustment"]
    J["Settings"] --> K["Hospital/report branding and logos"]
    J --> L["Food-service budget per head/day"]
    J --> M["Local display/notification preferences"]
```

## Explicit Safety Boundary

Admin performs system oversight, not clinical care. No Admin patient/NCP navigation exists. Aggregate patient counts and aggregate Demographic Census do not grant access to patient-specific Assessment, Diagnosis, Intervention, Monitoring, Patient Menu Plan, or NCP Summary.

## Related Documents

- [Admin Module](../admin.md)
- [FAQ](../../FAQ.md)
- [Role How-To](../../ROLE-HOW-TO.md)
- [Storyboards](../../STORYBOARD.md)
