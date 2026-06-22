# Biochemical + Screening Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax. TDD: failing test first, watch it fail, minimal fix, watch it pass.

**Goal:** Fix the manual biochemical + screening-form feature: stop discarding lab edits on save, fix broken file upload retrieval, and clean up dead reference files — without breaking diagnosis/monitoring/report flows that consume biochemical data.

**Architecture:** Backend Laravel 13 (PHPUnit) + Next.js frontend (vitest). Biochemical data persists via `Assessment hasOne BiochemicalData`; abnormal-lab flagging feeds the AI diagnosis prompt; NCP summary report eager-loads `assessment.biochemicalData` (already renders all 19 fields — verified, no change needed).

**Tech Stack:** Laravel 13.11 / PHP 8.4 / MySQL, PHPUnit 12; Next.js + React + vitest.

---

## Bugs found (review summary)

1. **CRITICAL — lab edits discarded on save** (`page.tsx`). Inputs write live edits into `assessment.biochemical_data` via `updateField`, but `handleSave` overwrites `biochemical_data` with `mappedBiochemicalData` built from the stale `labValues` state (only set on load, never on input). Net: user lab edits are lost.
2. **CRITICAL — uploaded files unviewable** (`ScreeningDocumentController::file`). Default disk root is `storage/app/private` (Laravel 11+), but `file()` resolves relative paths to `storage_path('app/'.$path)` (missing `/private`) → 404. Files upload but preview/download fails — the reported "upload not working".
3. **MINOR — abg type mismatch.** Backend validates `biochemical_data.abg` as `string`; frontend sends it `Number()`-coerced → 422 if abg entered. `bp`/`abg` must stay strings.
4. **MINOR — perf** (`MonitoringSummaryService`). `->pluck()->take(3)` loads all diagnoses then slices; use `->limit(3)`.
5. **CLEANUP** — stray reference files in repo root: `old_assessment_page.txt`, `old_assessment_page_utf8.txt`, `old_page_utf8.tsx`, `temp_old_page.tsx`.

Out of scope (verified OK): NCP summary blade already renders all new biochem fields; `auth()->id()` is safe (AI routes behind `auth:sanctum`); cross-runtime lab-range duplication (PHP vs TS) cannot be shared.

---

### Task 1: Fix attachment file retrieval (bug #2) — backend TDD

**Files:**
- Test: `backend/tests/Feature/AttachmentFeatureTest.php`
- Modify: `backend/app/Http/Controllers/RND/ScreeningDocumentController.php`

- [ ] **Step 1: Write the failing test** — append to `AttachmentFeatureTest`:

```php
public function test_uploaded_attachment_file_can_be_retrieved(): void
{
    Storage::fake('local');
    $rnd = $this->rnd();
    $ncp = $this->ncp($rnd);

    $this->actingAs($rnd, 'sanctum')->postJson(
        "/api/rnd/ncp-records/{$ncp->id}/attachments",
        ['file' => UploadedFile::fake()->create('labs.pdf', 10, 'application/pdf')]
    )->assertStatus(201);

    $doc = ScreeningDocument::firstOrFail();

    $this->actingAs($rnd, 'sanctum')
        ->get("/api/rnd/screening-documents/{$doc->id}/file")
        ->assertOk();
}
```

- [ ] **Step 2: Run, verify RED**

Run: `cd backend && php artisan test --filter test_uploaded_attachment_file_can_be_retrieved`
Expected: FAIL — 404 (file() resolves to wrong path, not the faked disk).

- [ ] **Step 3: Fix `file()` to use the Storage facade**

```php
public function file(ScreeningDocument $screeningDocument)
{
    $path = $screeningDocument->file_path;

    // Primary: disk-relative path on the configured disk (current upload format).
    if (Storage::exists($path)) {
        return Storage::response($path);
    }

    // Fallback: legacy absolute paths stored before A8.
    abort_unless(is_file($path), 404, 'File not found.');

    return response()->file($path);
}
```

Remove the now-unused `BinaryFileResponse` return type hint on the method signature (return type becomes implicit/`mixed`). Keep the `Storage` import.

- [ ] **Step 4: Run, verify GREEN**

Run: `cd backend && php artisan test --filter AttachmentFeatureTest`
Expected: PASS (all attachment tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/RND/ScreeningDocumentController.php backend/tests/Feature/AttachmentFeatureTest.php
git commit -m "fix(rnd): resolve attachment files via Storage facade so uploads are viewable"
```

---

### Task 2: Fix lab-value save disconnect + abg coercion (bugs #1, #3) — frontend

**Files:**
- Create: `frontend/services/biochemical.ts` (pure coercion helper — unit testable)
- Test: `frontend/services/biochemical.test.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`

- [ ] **Step 1: Write the failing test** — `frontend/services/biochemical.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { coerceBiochemicalValue } from "./biochemical";

describe("coerceBiochemicalValue", () => {
  it("returns null for empty string", () => {
    expect(coerceBiochemicalValue("albumin", "")).toBeNull();
  });
  it("returns a number for numeric lab fields", () => {
    expect(coerceBiochemicalValue("albumin", "3.2")).toBe(3.2);
  });
  it("keeps bp and abg as strings", () => {
    expect(coerceBiochemicalValue("bp", "120/80")).toBe("120/80");
    expect(coerceBiochemicalValue("abg", "7.35")).toBe("7.35");
  });
  it("returns null for non-numeric input on numeric fields", () => {
    expect(coerceBiochemicalValue("glucose", "abc")).toBeNull();
  });
});
```

- [ ] **Step 2: Run, verify RED**

Run: `cd frontend && npx vitest run services/biochemical.test.ts`
Expected: FAIL — module `./biochemical` not found.

- [ ] **Step 3: Create the helper** — `frontend/services/biochemical.ts`:

```ts
// String fields stored verbatim; everything else is a numeric lab value.
const STRING_BIOCHEMICAL_KEYS = new Set(["bp", "abg"]);

export function coerceBiochemicalValue(
  key: string,
  raw: string,
): number | string | null {
  if (raw === "") return null;
  if (STRING_BIOCHEMICAL_KEYS.has(key)) return raw;
  const n = Number(raw);
  return Number.isNaN(n) ? null : n;
}
```

- [ ] **Step 4: Run, verify GREEN**

Run: `cd frontend && npx vitest run services/biochemical.test.ts`
Expected: PASS.

- [ ] **Step 5: Wire the helper into `page.tsx` and delete dead state**

In `page.tsx`:
1. Import: `import { coerceBiochemicalValue } from "@/services/biochemical";`
2. Delete the `labValues` state declaration (`const [labValues, setLabValues] = useState...`).
3. Delete both `setLabValues(...)` blocks in `loadData` (the "Initialize lab values" and "Initialize blank lab values" branches).
4. In `handleSave`, delete the `mappedBiochemicalData` block and remove the `biochemical_data: mappedBiochemicalData,` line from `toSave` (the `...assessment` spread already carries the live-edited `biochemical_data`).
5. In `renderBiochemicalTab`, change the input `onChange` to use the helper and keep string fields as strings:

```tsx
onChange={e => updateField("biochemical_data", {
  ...assessment.biochemical_data,
  [field.key]: coerceBiochemicalValue(field.key, e.target.value),
})}
```

- [ ] **Step 6: Typecheck + lint**

Run: `cd frontend && npx tsc --noEmit && npx eslint "app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx" services/biochemical.ts`
Expected: no errors (no unused `labValues`/`mappedBiochemicalData`).

- [ ] **Step 7: Commit**

```bash
git add "frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx" frontend/services/biochemical.ts frontend/services/biochemical.test.ts
git commit -m "fix(rnd): persist edited lab values on save; keep bp/abg as strings"
```

---

### Task 3: MonitoringSummaryService — limit at query (bug #4) — backend

**Files:**
- Modify: `backend/app/Services/MonitoringSummaryService.php`

- [ ] **Step 1: Change the query** — replace:

```php
$activeDiagnoses = $ncpRecord->diagnoses()
    ->orderBy('id')
    ->pluck('pes_statement')
    ->take(3)
    ->all();
```

with:

```php
$activeDiagnoses = $ncpRecord->diagnoses()
    ->orderBy('id')
    ->limit(3)
    ->pluck('pes_statement')
    ->all();
```

- [ ] **Step 2: Run the monitoring/summary tests**

Run: `cd backend && php artisan test --filter Monitoring`
Expected: PASS (no behavior change for ≤3 diagnoses; fewer rows fetched).

- [ ] **Step 3: Commit**

```bash
git add backend/app/Services/MonitoringSummaryService.php
git commit -m "perf(rnd): limit active-diagnoses query instead of slicing in PHP"
```

---

### Task 4: Remove stray reference files (cleanup #5)

**Files:**
- Delete: `old_assessment_page.txt`, `old_assessment_page_utf8.txt`, `old_page_utf8.tsx`, `temp_old_page.tsx` (repo root)

- [ ] **Step 1: Remove**

```bash
rm -f old_assessment_page.txt old_assessment_page_utf8.txt old_page_utf8.tsx temp_old_page.tsx
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "chore: remove old reference page snapshots"
```

---

### Task 5: Full regression — verify nothing downstream broke

- [ ] **Step 1: Backend suite**

Run: `cd backend && php artisan test`
Expected: all pass (Assessment, NcpAssessment, NcpDiagnosis, Attachment, Monitoring).

- [ ] **Step 2: Frontend unit tests**

Run: `cd frontend && npx vitest run`
Expected: all pass.

- [ ] **Step 3: Confirm report flow unaffected**

NCP summary blade (`reports/ncp-summary.blade.php`) already renders all 19 biochem fields via eager-loaded `assessment.biochemicalData`; abnormal flagging enriches the AI prompt in `AiDiagnosisController`. No code change — confirm by reading, no action.
