# Documentation index

Verified against committed source: `14a954e0a1167d781cf007a61c610dff297f952a` (2026-10-24).

Start with [DEVELOPERS.md](../DEVELOPERS.md) for task-to-source routing. The guides below describe current responsibilities; source and authorized runtime evidence outrank older narrative.

| Need | Documentation |
|---|---|
| Developer tasks, modules and rules | [Developer map](../DEVELOPERS.md), [AGENTS](../AGENTS.md) |
| Accounts, keys, deployment and ownership | [Operations](developers/operations/README.md), [VPS setup](operations/vps-setup.md), [turnover](operations/turnover.md), [five Actions secrets](developers/operations/environment.md#github-actions-repository-secrets-obtain-enter-and-replace) |
| Local setup and checks | [Setup](developers/platform/local-setup.md), [testing](developers/platform/testing.md) |
| EAS Cloud, signing and APK updates | [Android releases](mobile-apk-release.md) |
| User-facing help and role tasks | [FAQ](FAQ.md), [Role How-To](ROLE-HOW-TO.md) |
| Current presentation sequences | [Storyboard index](STORYBOARD.md) → repository-root Storyboarding/ |
| Detailed role/domain behavior | [RND](modules/rnd.md), [FSS](modules/fss.md), [Admin](modules/admin.md) |
| Deeper flow diagrams | [Flowchart index](module-workflow-flowchart.md), [Flowcharts](modules/Flowcharts) |
| Clinical rationale | [intervention goals](logic/intervention-goals.md), [meal algorithm](logic/meal-algorithm.md), [recommend/avoid](logic/recommend-avoid.md) |
| Schema and security references | [Schema](database-schema.md), [Security](security/security.md) |
| Audit/search/compatibility design rationale | [Architecture references](architecture) |
| Food-service maintenance detail | [Domain map](developers/food-service/README.md), [detailed invariants](developers/food-service/reference.md) |
| Forms and visual reference material | [Nutriscope Forms](<Nutriscope Forms>), [Design System](<Design System/readme.md>) |

Clinical research, forms, user help, domain diagrams and visual guidelines serve different purposes from developer navigation; retain them without copying their contents into every guide. Confirm a specific claim in code/tests before changing it; reference dates identify the source context.
