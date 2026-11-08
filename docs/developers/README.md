# Developer documentation

Document version: 1.0 | Issued: 5 November 2026

Choose the business area first. Each area links to its backend owners, web/mobile consumers, tests and data meaning; platform guides hold cross-cutting implementation details.

| Group | Read for |
|---|---|
| [Nutrition Care Process](ncp/README.md) | Patients/cycles, screening, assessment, diagnosis, intervention, monitoring, clinical recipes/meal plans and appointments |
| [Food service](food-service/README.md) | Operational catalog/recipes, menus/templates, shopping, procurement, receiving, service execution, budgets and FSS mobile |
| [Shared features](shared/README.md) | Accounts/profile/recovery, help, announcements, notifications, SOP, role dashboards, reports and audit |
| [Platform architecture](platform/architecture.md) | Backend/web boundaries, contracts, storage/persistence, configuration and change tracing |
| [Recommendations](recommendations/README.md) | Proposed slow-connection improvements and future offline workflows, including scope, authentication, synchronization, conflicts and acceptance criteria |
| [Data semantics](platform/data-semantics.md) | What fields mean, units, statuses, snapshots, derived values and how to trace their consumers |
| [Operations](operations/README.md) | Accounts/integrations/env, jobs, files/reports, hosting, backup and ownership transfer |
| [Local setup](platform/local-setup.md), [testing](platform/testing.md) | Developer installation and safe verification |
| [Initial data and seeding](../operations/initial-data.md) | Production Admin/reference initialization, optional local/demo data, clinical owner selection and automated-test commands |

[Root task map](../../DEVELOPERS.md) routes common changes. This is the only developer-docs tree. It organizes existing responsibilities; it does not introduce new application modules or claim exhaustive field-by-field coverage.

The recommendations guide distinguishes inspected current behavior from proposed future work.

When maintaining a feature, update the affected domain guide and field-semantics notes only for verified changes. Follow links to user/domain references instead of duplicating them.
