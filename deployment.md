# NutriScope deployment and turnover

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

This is the entry point for setup and ownership transfer. Follow the focused guides in order:

1. [Developer map](DEVELOPERS.md) — locate apps and the owner of each behavior.
2. [Accounts and turnover](docs/operations/turnover.md) — institution ownership, DNS inventory, existing-data cutover and recovery.
3. [Environment map](docs/developers/operations/environment.md) — variable names, required secrets and Compose precedence.
4. [Integration accounts](docs/developers/operations/integrations.md) — USDA, Anthropic, Resend, Cloudflare R2 and conditional Render usage.
5. [Replacement VPS](docs/operations/vps-setup.md) — Ubuntu/Docker setup, current roles, host Nginx, DNS, certificates, release and verification.
6. [EAS Cloud / Android updates](docs/mobile-apk-release.md) — project ownership, signing, build profiles, publication and stable download URLs.
7. [Backup and recovery](docs/operations/backup-recovery.md) — what to preserve and what must be independently tested.

The repository declares Laravel, Next.js, MySQL, Redis, worker/scheduler and release roles. It does not declare an active PaddleOCR/OMR container, Nginx container or Certbot container. The old root deployment instructions have been replaced from current source; do not use nginx/init-ssl.sh for the documented host-Nginx topology.

Runbook commands are for a future authorized operator. Confirm the destination, effective settings and recovery prerequisites before executing them.
