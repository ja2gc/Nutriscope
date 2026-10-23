# NutriScope overview

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

NutriScope supports clinical nutrition care, meal planning, food-service operations and administration.

- RND uses the web console for NCP, clinical food data, operational planning and reports.
- FSS uses the native food-service app for its execution workflows.
- Admin uses the web console for accounts, audit oversight and system controls.
- Laravel owns shared business rules; Next.js and Expo are its web/mobile consumers.

AI assists selected PES wording and monitoring narration. It does not replace deterministic calculations or professional review. Uploaded screening/lab documents are attachments; the current deployment does not declare PaddleOCR/OMR extraction services.

For user tasks use [FAQ](FAQ.md) and [Role How-To](ROLE-HOW-TO.md). For maintenance use [DEVELOPERS.md](../DEVELOPERS.md); for all documentation use the [index](README.md).
