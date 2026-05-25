Here's the complete `DEVELOPER_GUIDE.md`:

```markdown
# NutriScope — Developer Guide

NutriScope is a hospital-based clinical nutrition management system built for Romana Pangan District Hospital. Read the `/docs` folder first before doing anything. It gives full context on what we're building.

---

## Prerequisites (Install These First)

Before cloning or running anything, make sure you have these installed.

### Required

**PHP 8.3+**
https://www.php.net/downloads.php
Verify: `php --version`

**Composer**
https://getcomposer.org/download/
Verify: `composer --version`

**Node.js 20+**
https://nodejs.org/en/download
Verify: `node --version`

**Docker Desktop**
https://www.docker.com/products/docker-desktop/
Required for MySQL and Redis containers. Must be open and running before `docker-compose up -d`.
Verify: `docker --version`

**Git**
https://git-scm.com/downloads
Verify: `git --version`

### For Development Agent

**Antigravity IDE**
https://antigravity.dev
This is the AI coding agent we use for all development. After installing, open the NutriScope project folder inside it.

**Superpowers Framework**
Run once after cloning to set up the workflow framework:
```bash
npx antigravity-superpowers init
```

### Optional but Recommended

**TablePlus or DBeaver** — view and manage MySQL database visually
- TablePlus: https://tableplus.com
- DBeaver: https://dbeaver.io

**Postman or Bruno** — test Laravel API endpoints directly
- Postman: https://www.postman.com
- Bruno: https://www.usebruno.com

---

## Initial Setup

### 1. Clone the repo
```bash
git clone <repo-url>
cd Nutriscope
```

### 2. Start Docker (MySQL + Redis)
Make sure Docker Desktop is open and running, then:
```bash
docker-compose up -d
```

### 3. Backend setup
```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

### 4. Frontend setup
```bash
cd frontend
npm install
```

Create `.env.local` inside `frontend/`:
```
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api
LARAVEL_API_URL=http://127.0.0.1:8000/api
```

Then run:
```bash
npm run dev
```

### 5. Open in browser
```
http://localhost:3000
```

---

## Seeded Accounts

See `backend/database/seeders/AdminUserSeeder.php` for full list.

| Role  | Email                  | Password        |
|-------|------------------------|-----------------|
| Admin | admin@nutriscope.local | nutriscope2024! |
| RND   | rnd@nutriscope.local   | nutriscope2024! |
| FSS   | fss@nutriscope.local   | nutriscope2024! |

---

## Development Workflow (Superpowers)

Every task must follow this exact order. Do not skip steps.

### Standard Flow
```
1. /superpowers-brainstorm   → only if task is unclear or complex
2. /superpowers-write-plan   → always before touching any code
3. APPROVED                  → review the plan, then type APPROVED
4. /superpowers-execute-plan → agent builds step by step with tests
5. /superpowers-review       → check for blockers, bugs, security
6. /superpowers-finish       → wrap up, commit, update docs
```

### When to Use Each Command

| Command | When |
|---------|------|
| `/superpowers-brainstorm` | Unclear requirements, new feature design |
| `/superpowers-write-plan` | Before every implementation |
| `/superpowers-execute-plan` | After plan is approved |
| `/superpowers-review` | After execution, before finishing |
| `/superpowers-finish` | End of every task |
| `/superpowers-debug` | Something is broken |
| `/superpowers-tdd` | Backend features needing tests first |

### Rules
- Never mark a milestone done without tests passing
- Backend = PHPUnit tests written + `php artisan test` all green
- Frontend = confirmed renders in browser
- Agent must go through Superpowers workflow before marking `[x]`

---

## Superpowers Skill Reference

Paste these into any AI chat when planning outside Antigravity so the AI understands the workflow.

**Full workflow order:**
```
brainstorm → write-plan → APPROVED → execute-plan → review → finish
```

**superpowers-workflow** (paste this always):
```
Default workflow for every task:
1. Brainstorm: clarify goal, constraints, risks, acceptance criteria
2. Write a plan: small steps (2-10 min each) with files + verification
3. Implement: smallest correct change, tests-first when possible
4. Review: correctness, edge cases, security, style, maintainability
5. Finish: run verification, summarize changes, commit, update docs
```

**superpowers-brainstorm:**
```
Use before any creative or unclear work.
Explores intent, requirements, design before implementation.
Hard gate: no code until design is approved.
```

**superpowers-write-plan:**
```
Use before any multi-file or behavior-changing task.
Output: Goal, Assumptions, Plan (small steps with files + verify), Risks, Rollback.
```

**superpowers-tdd:**
```
Use for backend features.
Red → green → refactor.
Write test first, implement minimal change to pass, then refactor.
Run php artisan test. All must pass.
```

**superpowers-review:**
```
Use after execution, before finishing.
Severity: Blocker / Major / Minor / Nit
Checks: correctness, edge cases, tests, security, performance, readability.
```

**superpowers-debug:**
```
Use when something is broken.
Reproduce → isolate → hypothesize → instrument → fix → regression test.
```

**superpowers-finish:**
```
Use at end of every task.
Runs verification, summarizes changes, notes follow-ups, commits, updates milestones.
```

---

## UI/UX Standards (ui-ux-pro-max skill)

Skill location: `.agents/skills/.agent/skills/ui-ux-pro-max/SKILL.md`

Always apply this skill when building or modifying any frontend UI.

### Brand
- **"Nutri"** = Emerald Green `#059669` / Tailwind `emerald-600`
- **"Scope"** = Tangerine Orange `#EA580C` / Tailwind `orange-600`
- Dark sidebar (`bg-zinc-950`), bright content canvas
- Modern clinical SaaS — not legacy, not generic AI

### Rules
- No generic AI icons
- Use clinical icons: `Compass`, `HeartHandshake`, `Salad`, `CookingPot`, `TrendingUp`
- Professional but visually engaging
- 4px/8px grid spacing strictly
- All design tokens in `frontend/app/globals.css`
- Design system docs in `docs/ui/design-system.md`
- Use native Tailwind utility classes, not custom `@theme` variables

---

## Planning with AI Chat (Outside Antigravity)

When you need to plan or figure out next steps without burning agent credits, use an AI chat (Claude, ChatGPT, Gemini).

### How to Feed Context

Always paste these into the chat first:

1. Contents of `docs/milestones.md`
2. Contents of `docs/overview.md`
3. Contents of `docs/architecture/stack.md`
4. Current status — tell the AI what milestone you just finished

Example opener:
```
Here is our project context:
[paste milestones.md]
[paste overview.md]
[paste stack.md]

Current status: Just finished Milestone 1 frontend.
Starting Milestone 2. What should my next Antigravity prompt be?
```

---

## When You Run Out of Credits

### Switch model first
Antigravity lets you change model in settings. Try switching before making a new account.

### Continue after switching
```
/superpowers-execute-plan
Continue from last checkpoint. Read artifacts/superpowers/execution.md.
```

### If you accidentally cancelled or rejected changes
```
/superpowers-execute-plan
Restart from Step X. Read artifacts/superpowers/execution.md and plan.md.
```

### When all models on your account are exhausted
1. Create a new Antigravity account
2. Open the same NutriScope project folder
3. Run `/superpowers-reload` to load skills
4. Continue with the prompt above

No context is lost — everything is saved to disk in `artifacts/superpowers/`.

---

## Token Efficiency Tips

- Keep prompts short — agent reads `docs/` automatically
- Never repeat what is already in `docs/` in your prompt
- Use cheap models (Flash, Haiku) for planning
- Use strong models (Pro, Sonnet) for coding
- Skip brainstorm for simple obvious tasks, go straight to write-plan
- Always brainstorm first for risky tasks (auth, database, migrations)

---

## Context for Agents

Always tell the agent to read docs before starting:
```
Read docs/ folder and .agents/rules/ for full project context.
```

Key docs:
- `docs/milestones.md` — current progress and task list
- `docs/architecture/` — system design and folder structure
- `docs/ui/design-system.md` — UI/UX standards
- `artifacts/superpowers/` — previous plans and execution logs

---

## Useful Terminal Commands

```bash
# Start everything
docker-compose up -d
cd backend && php artisan serve
cd frontend && npm run dev

# Reset database
php artisan migrate:fresh --seed

# Run backend tests
php artisan test

# Check routes
php artisan route:list

# Check seeded users
php artisan tinker
User::all()

# Commit and push work
git add .
git commit -m "feat: <milestone name>"
git push
```

---

## Milestone Tracker

See `docs/milestones.md` for full list of milestones and current status.

Never mark a milestone `[x]` unless:
1. Superpowers workflow was followed
2. Tests pass (`php artisan test` for backend)
3. Renders correctly in browser (frontend)
4. `/superpowers-review` found no blockers
5. `/superpowers-finish` was run
```