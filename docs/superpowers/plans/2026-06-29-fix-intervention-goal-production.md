# Fix Intervention Goal Production Failure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore the deployed RND intervention goal flow so a new NCP record can load an empty intervention state, create the intervention row, persist the selected goal/prescription, and unblock meal-plan work.

**Architecture:** The browser calls Next.js API routes under `frontend/app/api`, and those routes proxy to Laravel using `LARAVEL_API_URL`. Several intervention, monitoring, meal-plan item, and meal-plan-template routes incorrectly read `NEXT_PUBLIC_API_URL` and fall back to `http://localhost:8000`, which is wrong inside the production frontend container. Laravel also needs `GET /intervention` to return `data: null` for a new NCP record instead of throwing when no intervention exists.

**Tech Stack:** Next.js App Router API routes, Laravel 13.11, Sanctum bearer tokens via `nutriscope_token` cookie, Docker Compose production deployment.

---

## Diagnosis Summary

**Confirmed evidence:**
- Browser errors include 500s for `GET/POST /api/rnd/ncp-records/{id}/intervention` and `GET /api/rnd/meal-plan-templates`.
- Claude observed that intervention 500s were not showing in backend nginx/Laravel logs. That points at the Next.js proxy layer, not necessarily Laravel.
- Direct backend curl to `http://localhost/api/rnd/ncp-records/7/intervention` with a bearer token created an intervention successfully.
- Frontend goal flow calls `createIntervention(ncpId, {})` before PATCHing the selected goal.
- Existing backend test `test_empty_intervention_does_not_activate_ncp` expects empty POST creation to return 201.
- Production Docker sets `LARAVEL_API_URL=http://backend/api`, not `NEXT_PUBLIC_API_URL`.

**Likely root cause:**
- Primary: the affected Next API routes use `process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000'`, so production frontend server-side fetches can target the wrong host. This explains browser 500s with no matching backend intervention log and also explains meal-plan-template 500s.
- Secondary: Laravel `InterventionController::show()` previously used `firstOrFail()` for new records. That should stay fixed to return `{"data": null}` because a missing intervention is a valid empty state.

**Do not do yet:**
- Do not run `migrate:fresh` on production.
- Do not keep toggling `APP_DEBUG=true` as the fix.
- Do not assume GitHub Actions success means the frontend proxy is correct; inspect container env and route file contents.

## File Map

- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/route.ts`
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/autofill/route.ts`
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/recommendations/route.ts`
- Modify: `frontend/app/api/rnd/meal-plan-templates/route.ts`
- Modify: `frontend/app/api/rnd/meal-plan-templates/[templateId]/route.ts`
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/from-template/route.ts`
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/[mealPlanId]/route.ts`
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/[mealPlanId]/items/route.ts`
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/[mealPlanId]/save-template/route.ts`
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/monitoring-plan/route.ts`
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/monitorings/route.ts`
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/monitorings/[monitoringId]/route.ts`
- Verify/format: `backend/app/Http/Controllers/RND/InterventionController.php`
- Test: `backend/tests/Feature/NcpInterventionTest.php`

### Task 1: Prove The Deployed Failure Layer

**Files:**
- Inspect on VPS: deployed containers only

- [ ] **Step 1: Run Claude's read-only deployed controller check**

Run on VPS:

```bash
docker exec nutriscope_backend grep -A 12 "public function show" app/Http/Controllers/RND/InterventionController.php
```

Expected good output:

```php
public function show(NcpRecord $ncpRecord): JsonResponse
{
    $intervention = $ncpRecord->intervention()->first();

    if (! $intervention) {
        return response()->json(['data' => null]);
    }

    return (new InterventionResource($intervention))->response();
}
```

- [ ] **Step 2: Check frontend container env**

Run on VPS:

```bash
docker exec nutriscope_frontend env | grep -E "LARAVEL_API_URL|NEXT_PUBLIC_API_URL"
```

Expected output:

```bash
LARAVEL_API_URL=http://backend/api
```

If `NEXT_PUBLIC_API_URL` is absent, the current intervention route code is using its fallback unless code is changed.

- [ ] **Step 3: Check deployed frontend route source**

Run on VPS:

```bash
docker exec nutriscope_frontend grep -R "NEXT_PUBLIC_API_URL" -n /app/server.js /app/.next 2>/dev/null | head -20
```

Expected before fix: hits for intervention/template routes.

Expected after fix: no hits for these routes, or routes now reference `LARAVEL_API_URL`.

- [ ] **Step 4: Test backend exact empty POST, not just goal payload**

Run on VPS, replacing token:

```bash
docker exec nutriscope_backend curl -s -X POST http://localhost/api/rnd/ncp-records/8/intervention \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN_HERE" \
  -d '{}' | python3 -m json.tool
```

Expected if backend is healthy:

```json
{
  "data": {
    "id": 8,
    "ncp_record_id": 8
  }
}
```

If this returns 201 but browser still returns 500, fix the frontend proxy first.

### Task 2: Replace Wrong Proxy Env Usage

**Files:**
- Modify: all files listed in File Map that currently contain `const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';`
- Prefer: `frontend/lib/laravelProxy.ts`

- [ ] **Step 1: Replace per-route API constants**

Use this pattern:

```ts
import { proxy } from "@/lib/laravelProxy";
```

For `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/route.ts`, replace the custom proxy with:

```ts
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  return proxy(`/rnd/ncp-records/${ncpRecordId}/intervention`);
}

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const body = await req.json().catch(() => ({}));
  return proxy(`/rnd/ncp-records/${ncpRecordId}/intervention`, {
    method: "POST",
    body,
  });
}

export async function PATCH(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const body = await req.json().catch(() => ({}));
  return proxy(`/rnd/ncp-records/${ncpRecordId}/intervention`, {
    method: "PATCH",
    body,
  });
}
```

- [ ] **Step 2: Apply same proxy pattern to autofill**

Use:

```ts
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const body = await req.json().catch(() => ({}));
  return proxy(`/rnd/ncp-records/${ncpRecordId}/intervention/autofill`, {
    method: "POST",
    body,
  });
}
```

- [ ] **Step 3: Apply same proxy pattern to recommendations**

Use:

```ts
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  return proxy(`/rnd/ncp-records/${ncpRecordId}/intervention/recommendations`);
}
```

- [ ] **Step 4: Replace remaining `NEXT_PUBLIC_API_URL` API routes**

Run:

```bash
rg -n "NEXT_PUBLIC_API_URL" frontend/app/api
```

Expected after edits:

```bash
# no output
```

### Task 3: Keep Laravel Empty-State Fix And Add Regression Test

**Files:**
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Modify: `backend/tests/Feature/NcpInterventionTest.php`

- [ ] **Step 1: Format `show()` cleanly**

Use:

```php
public function show(NcpRecord $ncpRecord): JsonResponse
{
    $intervention = $ncpRecord->intervention()->first();

    if (! $intervention) {
        return response()->json(['data' => null]);
    }

    return (new InterventionResource($intervention))->response();
}
```

- [ ] **Step 2: Add test for empty intervention show**

Add to `NcpInterventionTest`:

```php
public function test_show_returns_null_when_intervention_missing(): void
{
    $rnd = $this->rnd();
    $patient = $this->patient();
    $ncp = $this->ncpRecord($patient, $rnd);

    $this->actingAs($rnd, 'sanctum')
        ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention")
        ->assertOk()
        ->assertJsonPath('data', null);
}
```

- [ ] **Step 3: Run focused backend test**

Run:

```bash
cd backend
php artisan test --filter=NcpInterventionTest
```

Expected:

```bash
PASS  Tests\Feature\NcpInterventionTest
```

If it hangs locally, run it in the backend container or ensure the local test DB is reachable before trusting the result.

### Task 4: Verify Build And Deploy

**Files:**
- Verify: `.github/workflows/deploy.yml`
- Verify: `docker-compose.prod.yml`
- Verify: `frontend/Dockerfile`
- Verify: `backend/Dockerfile`

- [ ] **Step 1: Type-check frontend**

Run:

```bash
cd frontend
npx tsc --noEmit
```

Expected:

```bash
# exits 0
```

- [ ] **Step 2: Push and let GitHub Actions deploy**

Run:

```bash
git add frontend backend docs/superpowers/plans/2026-06-29-fix-intervention-goal-production.md
git commit -m "fix: route RND proxies through Laravel API env"
git push origin main
```

Expected:

```bash
# GitHub Actions deploy completes successfully
```

- [ ] **Step 3: Confirm deployed frontend no longer contains bad env usage**

Run on VPS:

```bash
docker exec nutriscope_frontend env | grep -E "LARAVEL_API_URL|NEXT_PUBLIC_API_URL"
docker exec nutriscope_frontend grep -R "NEXT_PUBLIC_API_URL" -n /app/server.js /app/.next 2>/dev/null | head -20
```

Expected:

```bash
LARAVEL_API_URL=http://backend/api
# no intervention/template route hits for NEXT_PUBLIC_API_URL
```

### Task 5: Production Smoke Test

**Files:**
- Browser: `https://www.nutriscope.live`
- Inspect: Docker logs

- [ ] **Step 1: Create a fresh NCP record through UI**

Use the deployed website:

```text
Patient -> Start NCP cycle -> Assessment -> Diagnosis -> Intervention -> Set Goal
```

Expected:

```text
Goal card shows selected goal.
Nutrition prescription fields populate or show actionable missing-field validation.
No POST /api/rnd/ncp-records/{id}/intervention 500 in browser console.
```

- [ ] **Step 2: Confirm backend receives the request**

Run on VPS immediately after clicking Set Goal:

```bash
docker logs nutriscope_backend --tail 100 | grep "intervention"
docker logs nutriscope_frontend --tail 100
```

Expected:

```text
No uncaught fetch failed errors in frontend logs.
No Laravel exception for intervention store/show.
```

- [ ] **Step 3: Verify meal-plan templates no longer 500**

Open the intervention page meal-plan section.

Expected:

```text
GET /api/rnd/meal-plan-templates does not return 500.
```

## Should You Run Claude's Last Command?

Yes. This command is read-only and useful:

```bash
docker exec nutriscope_backend grep -A 10 "public function show" app/Http/Controllers/RND/InterventionController.php
```

But do not stop there. Also run the frontend env/source checks above, because the stronger root cause for the deployed browser failure is the inconsistent Next.js proxy env variable.

## Rollback Plan

- If the proxy change causes broader API failures, revert only the frontend proxy edits and redeploy.
- Keep the Laravel `show()` null response unless a test proves the frontend expects 404; current UI already expects `null`.
- Keep `APP_DEBUG=false` and `LOG_LEVEL=error` in production after diagnosis.
