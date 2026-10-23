# VPS memory and maintenance implementation plan

**Goal:** Stop application builds competing with live services and make routine
resource and image maintenance part of the existing deployment.

**Architecture:** GitHub Actions builds the existing Dockerfiles, stamps each
image with the source revision, and transfers a compressed Docker image archive
over fingerprint-verified SSH. A small Bash deployment script loads the images,
runs the existing release, checks health, and removes only old dangling images
created by this pipeline. No registry, new provider, daemon restart, or monitoring
service is required.

**Execution:** Implement inline as requested. Preserve unrelated working changes.
Do not push, deploy, prune production, restart Docker, inspect secrets or data,
or change Actions secrets as part of local implementation.

**Evidence:** On 2026-10-23 the verified 2 GiB production host had roughly
726–800 MiB resident in dockerd, around 1 GiB swapped out, and 34.3 GB of build
cache on disk. Application containers were substantially smaller. A daemon leak
was not proven. The inspected workflow builds both images on production.

## Choices

- Chosen: existing Actions runner plus direct SSH image transfer; simplest fit
  without a registry or new provider.
- Registry: useful for larger release pipelines, but adds access and retention
  configuration unnecessary here.
- Continue VPS builds with cleanup: fewer workflow changes, but preserves build
  pressure on the 2 GiB production host.

## Task 1 — deployment and cleanup

Files: `.github/workflows/deploy.yml`, `ops/deploy.sh`,
`ops/tests/deploy.test.sh`, `.gitignore`, `frontend/.dockerignore`.

- [x] Write executable shell regression tests with fake Docker/curl boundaries:
  healthy deploy, image revision mismatch, release failure, health failure,
  repeated same-revision deploy, invalid revision, and missing archive.
- [x] Run tests before implementation; observed missing deployment script failure.
- [x] Build in Actions; label images `io.nutriscope.app=true` and
  `org.opencontainers.image.revision=$GITHUB_SHA`; save/gzip both images.
- [x] Require `DROPLET_HOST_FINGERPRINT` for transfer and command execution.
  Transfer to `~/Nutriscope/.deploy/images.tar.gz`, serialize deploy jobs, reject
  stale source revisions, and verify checkout equals the built SHA.
- [x] Script: validate SHA and checkout, inspect host/container metrics, preserve
  running images as rollback before load, verify loaded labels, use `--no-build`
  and `--pull never`, run release, start services, verify both local HTTP services
  and running image IDs, then prune only dangling labeled images older than 168h.
  Never use image prune `--all`, system prune, builder prune, volume removal,
  automatic DB rollback, or forced image removal.
- [x] Remove the fixed transfer archive on success/failure. Ignore `.deploy/`.
  Exclude all frontend `.env*` except examples from build context.
- [x] Run `bash ops/tests/deploy.test.sh` and Bash syntax checks; 12 scenarios pass.

## Task 2 — bounded runtime settings

Files: `docker-compose.prod.yml`, `backend/Dockerfile`,
`backend/apache-prefork.conf`.

- [x] Use Docker's `local` logging driver, 10m per file and 3 files per container.
  Route Laravel logs to its existing stderr channel so Docker rotation covers it.
- [x] Add explicit frontend image name and `pull_policy: never` for app images.
- [x] Add memory ceilings: HTTP 768m, worker/release 512m, scheduler 192m,
  frontend 384m, MySQL 768m, Redis 128m. Keep default swap behavior; do not disable
  the OOM killer. These are initial isolation ceilings, not measured load capacity;
  their sum exceeds physical RAM, so monitor actual concurrent demand.
- [x] Replace stock Apache prefork pool with StartServers/MinSpareServers 2,
  MaxSpareServers/MaxRequestWorkers 4, MaxConnectionsPerChild 500. Keep PHP's
  existing 256M limit and hourly queue recycling.
- [x] Validate merged Compose using `--no-env-resolution` and an explicit example
  interpolation file, never reading real env files. Run isolated Apache config
  and concurrency checks without the application, database, or production access.

## Task 3 — documentation and final verification

Files: `DEVELOPERS.md`, `docs/developers/operations/README.md`,
`docs/operations/vps-setup.md`, `docs/operations/platform-requirements.md`.

- [x] Update current deployment owners, exact commands, required fingerprint,
  image/log retention, migration procedure, capacity limits, and rollback limits.
  Refresh verified source revision, preserving unrelated staged edits.
- [x] Document approved one-time legacy cache review separately from automatic
  maintenance. No production cleanup or Docker restart during implementation.
- [x] Run shell regressions, Compose validation, isolated Docker smoke checks,
  workflow validation, documentation links, and diff whitespace checks.
- [x] Self-review failure ordering, rollback tag retention, repeated deployments,
  SHA checks, no server builds, no sensitive build context, protected volumes,
  and no accidental production actions. Report unrun checks and live rollout needs.

## Review focus

Failed or stale releases must never trigger cleanup. Redeploying the current
revision must not overwrite the previous rollback image. Cleanup must leave
running and tagged images, all volumes, data, and backups alone. Loaded image
revision and running image IDs must match the built release. Hard memory limits
need workload acceptance after rollout; a short smoke test is not capacity proof.

## Verification record

- 12 shell deployment scenarios pass, including wrong checkout/image revision,
  corrupt archive, release/startup/HTTP/running-image failures, repeated revision,
  invalid input and missing archive. ShellCheck passes after correcting negated
  test assertions to explicitly exit on an unexpected match.
- Actionlint and Docker build checks for both Dockerfiles pass. Merged Compose
  validates without real environment files; loopback ports and private upload
  mounts remain intact.
- Isolated Apache smoke: 32 synthetic PHP requests at concurrency eight, zero
  failed requests, four child workers, 768 MiB ceiling and local logging verified.
- Fresh isolated MySQL 8 startup: healthy under 768 MiB, peak 527065088 bytes,
  zero OOM events. This is not a production dataset/concurrency test.
- Final review is by the implementer; no subagents or worktrees were authorized.
- No full application image build, GitHub-hosted run, SSH transfer, production
  rollout, workload capacity test, provider alert creation, legacy cache removal,
  or Docker restart is claimed. Configure the verified host fingerprint in Actions
  and approve production rollout separately.
