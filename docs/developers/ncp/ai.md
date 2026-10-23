# AI features

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: maintain assisted PES wording and monitoring narration. AI does not own deterministic clinical calculations.

## Change the model or provider

1. Read [AIService](../../../backend/app/Services/AIService.php), especially draftPes and narrateMonitoring. Both call Anthropic Messages; diagnosis also requires structured JSON output, and requests include thinking settings. Choose an accessible model supporting these parameters; a model-name change is not proof of compatibility.
2. For this production Compose deployment, change ANTHROPIC_MODEL in [docker-compose.prod.yml](../../../docker-compose.prod.yml), under the shared backend environment. It overrides the dotenv setting for web, worker, scheduler and release. Rebuild/recreate through the approved deployment process. Native Laravel/non-Compose deployments read ANTHROPIC_MODEL via [services.php](../../../backend/config/services.php); rebuild Laravel config cache and restart long-running processes after changes.
3. Keep ANTHROPIC_API_KEY server-side. Create the receiving institution's provider account/project, arrange billing and create its own API key. [Official model IDs](https://platform.claude.com/docs/en/models/overview) and provider requirements change; check them before selection.
4. Update the input/output cost rates through the existing Admin AI usage settings when pricing changes. [AiUsageLimit](../../../backend/app/Models/AiUsageLimit.php) stores these rates; [AiUsageLog](../../../backend/app/Models/AiUsageLog.php) snapshots estimated cost for new usage records. Review fallback estimates in [aiTokenCost.ts](../../../frontend/lib/aiTokenCost.ts) and its consumers/tests too. Preserve historical estimates; they are not provider invoices.
5. Use synthetic regression fixtures for output/schema validation and failure behavior. Changing to a different provider requires a deliberate adapter/contract change; an OpenAI key cannot simply replace the Anthropic key.

## Change prompts, evidence or limits

[PesSuggestionService](../../../backend/app/Services/Diagnosis/PesSuggestionService.php) and neighboring Diagnosis services gate evidence, validate candidates and retain drafts. [MonitoringController](../../../backend/app/Http/Controllers/RND/MonitoringController.php) owns monitoring review flow. Preserve evidence attribution, response bounds and editable-review behavior.

[Admin limits](../../../backend/app/Http/Controllers/Admin/AiUsageLimitController.php) and [usage client](../../../frontend/services/aiUsageLimitService.ts) expose limits; AIService checks recorded usage before requests. These thresholds do not reserve spending for concurrent calls.

Tests: [AiServiceTest](../../../backend/tests/Feature/AiServiceTest.php), [AiUsageLimitTest](../../../backend/tests/Feature/AiUsageLimitTest.php), [MonitoringAiReviewTest](../../../backend/tests/Feature/MonitoringAiReviewTest.php). Preserve HTTP fakes; never use real patient data or paid calls in automated tests.

Related: [integrations](../operations/integrations.md), [environment](../operations/environment.md), [clinical](README.md), [testing](../platform/testing.md). Confirm provider model access, budget and synthetic output compatibility before changing it.
