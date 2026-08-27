# FSS Documentation and Role Storyboards Design

## Goal

Record the complete PWA-to-native consolidation, reconcile current user-facing documentation with the shipped Expo FSS application, and organize role-specific recording scripts outside the repository.

## Sources of Truth

- Current Expo Router screens and shared mobile components for visible FSS behavior.
- Current Laravel routes, controllers, requests, policies, and report services for permissions and persistence.
- Current Next.js routes and middleware for RND/Admin web access and the public APK handoff.
- Commits `39bc159` through `e5b432b` for the completed consolidation and release history.
- Live APK metadata only for deployed version, checksum, and stable download URLs.

Historical implementation plans remain unchanged. They describe decisions at their original date and are not current operating instructions.

## Deliverables

1. A repository implementation report covering architecture removal, native workflow restoration, backend hardening, web handoff, verification, EAS build, production publication, and CI changes.
2. Corrections to current FAQ, role how-to, system storyboard, FSS module, current flowcharts, and screenshot guide where they conflict with the shipped application.
3. `C:\Users\jared\Documents\Storyboarding\RND Food Service Operations Video Storyboard.md`, moved from its current Documents location and corrected only where its FSS handoff is outdated.
4. `C:\Users\jared\Documents\Storyboarding\FSS Mobile App Video Storyboard.md`, a standalone recording checklist and narration script based only on rendered native screens.

## FSS Recording Structure

The FSS script starts with demo-data prerequisites rather than recreating RND planning. Its main scenes cover installation/sign-in, first-login setup, Home, Announcement/SOP, Menu and food details, Meal Prep population, Purchase receiving, Accomplish Daily Log, My Reports/PDF, notifications/account/help/settings/update checking, and sign-out. Each scene contains exact on-screen actions, narration, expected result, and privacy-safe recording guidance.

## Safety and Maintenance

- Use demo data and hide credentials, tokens, recovery codes, private notifications, and real patient information.
- Do not claim that Menu records population or that Meal Prep has a completion action.
- Do not claim FSS has a web operational client.
- Do not change application code during this documentation task.
- Preserve the unrelated `.codex/config.toml` modification.

## Verification

- Search current documentation for obsolete PWA, FSS-web, service-completion, Menu-population, and five-tab statements.
- Confirm every FSS action label against current mobile source.
- Check Markdown links and fenced code blocks.
- Confirm the external storyboard directory contains both expected files and the old loose file no longer exists.
- Inspect the task-only Git diff, push `main`, and verify the remote reference.
