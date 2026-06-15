# RND Role — Architectural & Code Review

**Date:** 2026-06-15
**Scope:** RND clinical workflows (NCP), Food Service planning, Backend API endpoints, Frontend architecture, Clinical Localization, Algorithms, and System Rules Compliance.

## 1. System Rule Violations (CRITICAL)

*   **Hardcoded Clinical Rules:** 
    *   *Flaw:* In `InterventionController.php`, the `mapGoalTypeToConditions()` method uses a hardcoded `match` statement (`'renal_diet' => ['CKD']`). 
    *   *Rule Broken:* Project hard rules explicitly state: *"clinical_rules drives all food-disease logic; never hardcode rules."* 
    *   *Fix Required:* The controller must dynamically query the `clinical_rules` database table to find conditions associated with a goal type.
*   **Synchronous AI Calls:**
    *   *Flaw:* `AiDiagnosisController` calls `$this->aiService->suggestDiagnoses()` synchronously. The HTTP request hangs waiting for the Claude API to respond.
    *   *Rule Broken:* Project hard rules explicitly state: *"Use background jobs for OCR, AI calls, and report generation."*
    *   *Fix Required:* AI requests must be pushed to a queue. The controller should return a 202 Accepted, and the frontend should poll or listen for completion.
*   **Synchronous Report Generation:**
    *   *Flaw:* `ReportController::run()` intentionally uses `GenerateReport::dispatchSync($report)` to render reports inline.
    *   *Rule Broken:* Project hard rules explicitly state: *"Use background jobs for OCR, AI calls, and report generation."*
    *   *Nuance (verified 2026-06-15):* `ReportController::run()` uses `dispatchSync` **intentionally** — a documented comment states the PDF must exist the moment the request returns, and only when `$reports->supports($type)`; unsupported types already `dispatch()` async. So this is a deliberate UX trade-off, not an oversight.
    *   *Fix Required (only if it becomes a problem):* If heavy reports start blocking PHP-FPM workers, switch to `dispatch` + the existing `status(queued/generating/completed)` column and have the frontend poll. Don't flip it blindly — that changes the "PDF ready on return" contract the UI depends on.

## 2. Laravel Boost & Security Guidelines Review

*   **Configuration Security (`env()` vs `config()`):**
    *   *Status:* **PASSED.** A codebase scan confirms zero usage of `env()` outside of config files. The backend strictly relies on `config('...')`, ensuring `php artisan config:cache` won't break the application in production.
*   **Database Query Safety:**
    *   *Status:* **PASSED.** Eloquent ORM and Query Builders are used exclusively across the RND controllers. No raw SQL strings were found, neutralizing SQL injection vectors.
*   **Testing Over Tinkering:**
    *   *Flaw:* The RND backend logic (`NutritionPrescriptionService` and `RiskScoreCalculator`) relies heavily on complex clinical logic, but lacks comprehensive test coverage validating it against the `prescription-targets.json` golden cases. The Laravel Boost guidelines mandate: *"Prefer tests with factories instead of manual tinkering."*
    *   *Fix Required:* Implement automated Pest/PHPUnit tests utilizing the golden cases defined in `prescription-targets.json` to prove the engine works without manual DB tinkering.
*   **Form Request Validation:**
    *   *Status:* **PASSED.** The RND controllers (`AssessmentController`, `NcpRecordController`) properly inject Form Requests (`StoreAssessmentRequest`, etc.) instead of inline `$request->validate()`, keeping controllers thin and preventing Mass Assignment vulnerabilities.

## 3. Clinical Logic & Localization Flaws (CRITICAL)

*   **Engine Desync with `prescription-targets.json`:**
    *   *Flaw:* The `docs/logic/prescription-targets.json` file is perfectly localized for the Philippines. It dictates Asia-Pacific BMI cut-points, Asian GLIM limits, and PDRI baselines (like free sugars `< 10%`). **However, the PHP `NutritionPrescriptionService` fails to fully implement this JSON spec.**
    *   *Missing Baselines:* The PHP engine's default fallback completely ignores the `free_sugar_max_pct_energy: 0.1` rule defined in the JSON `baseline_pdri`. 
    *   *Vulnerable Staging:* The PHP engine blindly accepts the `stage` string (e.g., `class_1`) without independently validating it against the patient's actual BMI using the Asia-Pacific `bmi_range` thresholds defined in the JSON. If the frontend sends the wrong string, the backend engine calculates the wrong deficit, breaking the Single Source of Truth architecture.

## 4. Algorithms Review (Meal Plan, Procurement)

*   **Meal Plan Generator — Missing AI Fallback:**
    *   *Flaw:* The `meal-algorithm.md` states: "AI fallback (Sonnet) — ONLY if <5 recipes match." However, `MealPlanService.php` simply returns `['insufficient_recipes' => true]` when the recipe count is low. A codebase search reveals that the controller never intercepts this to call the AI service. The AI fallback feature is completely missing.
*   **Suggested Procurement List — Ignores Inventory (CRITICAL):**
    *   *Flaw:* The `ProcurementService::suggestedItems()` algorithm calculates the procurement list by simply scaling the menu cycle usage mathematically (`qty = usage * span/cycle`). It completely ignores current inventory levels (`quantity_on_hand`). If the hospital needs 100kg of rice and has 90kg in the warehouse, the system will suggest buying 100kg instead of 10kg, leading to massive over-purchasing and waste.

## 5. AI Tokens & Dynamic Data Generation

*   **AI Token Usage Logging — CLAIM RETRACTED (verified 2026-06-15):**
    *   *Correction:* `AIService` **does** write the token payload — `AiUsageLog::create([...])` with `tokens_input`/`tokens_output` at lines 53 & 115, after Claude calls. `RND\AiDiagnosisController` routes through `AIService`, so diagnosis calls are logged. The original "never inserted into" finding was wrong; the Admin KPI chart has real data.
    *   *Residual check:* Confirm no AI call site bypasses `AIService`, and that the planned background-job refactor (§ sync AI calls) keeps the `AiUsageLog` write inside the job rather than dropping it.

## 6. Cache & Loading Speed

*   **Missing Redis Caching:**
    *   *Flaw:* A codebase scan for `Cache::` shows it is heavily utilized in FSS endpoints but entirely absent from the clinical NCP controllers. `rnd.md` specifies that `monitorings/ai-review` should be "cached per visit-pair" to save costs and loading speed. This caching is currently missing.
    *   *Fix Required:* Implement `Cache::remember()` in `MonitoringController` using Redis to store AI-generated reviews.
*   **N+1 Query Risks:**
    *   *Flaw:* While `NcpRecordController` performs adequate relational checks for single records, endpoints returning lists of patients (like the RND Dashboard) do not explicitly eager-load (`with('assessment', 'intervention')`). This will cause the dashboard to slow down significantly as patient volume grows.

## 7. Security & Data Privacy

*   **Single-Tenant Model (Accepted Risk):**
    *   *Flaw:* Route-model binding (`NcpRecord $ncpRecord`) simply resolves the record by ID. The `StoreInterventionRequest` and `RoleMiddleware` only check if the user is an `RND`. They do not check if the RND is assigned to that specific patient. 
    *   *Status:* As documented in the `2026-06-14-system-review.md`, this is an accepted decision because the hospital operates as a single trusted care team. However, if multi-clinic capability is ever required, this will cause massive PHI leaks.
*   **PHI in AI Prompts:**
    *   *Status:* Must ensure that when calling `AiService::suggestDiagnoses`, explicit PII (Names, Contact Info) is stripped from the payload, sending only clinical data.

## 8. Food Service (RND Side)

*   **Cost Freezing Mechanism:**
    *   *Status:* Excellent logic. `rnd.md` dictates that "Menu cycle costing freezes on activation." This ensures historical accuracy for reports even if vendor prices fluctuate.
*   **Role Bleed:**
    *   *Flaw:* Shared `/api/fss/*` routes allow both RND and FSS to perform CRUD. This is dangerous if FSS modifies an RND's menu cycle.
    *   *Status:* Addressed by the upcoming `fss-admin-implementation-plan` which adds `abort(403)` read-only guards for FSS.
