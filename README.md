# NutriScope

Verified against committed source: `14a954e0a1167d781cf007a61c610dff297f952a` (2026-10-24).

NutriScope supports clinical nutrition care, meal planning and food-service operations. Laravel provides the shared API; Next.js provides the web console; Expo provides the food-service mobile app.

- **Maintaining or changing the system:** [Developer map](DEVELOPERS.md).
- **Installing locally:** [Local setup](docs/developers/platform/local-setup.md).
- **Hosting, accounts, DNS, HTTPS and turnover:** [Operations entry point](docs/developers/operations/README.md), [replacement VPS setup](docs/operations/vps-setup.md), [ownership and IP/domain cutover](docs/operations/turnover.md).
- **Configuring the five GitHub deployment secrets:** [Purpose, exact value sources and setup](docs/developers/operations/environment.md#github-actions-repository-secrets-obtain-enter-and-replace).
- **Building and updating the Android app:** [APK/EAS release guide](docs/mobile-apk-release.md).
- **Using the system:** [FAQ](docs/FAQ.md), [role instructions](docs/ROLE-HOW-TO.md).
- **Demonstrating the system:** [Storyboards](docs/STORYBOARD.md).
- **Other references:** [Documentation index](docs/README.md).

Use committed lockfiles. Never use demo seeding, destructive migrations or production credentials for testing.
