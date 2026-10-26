# Clinical AI review implementation plan

Goal: improve evidence-grounded PES drafts and monitoring narration without repeated paid review of unchanged evidence.

Approved design: the conversation's six-part plan, including separate 10-request/600-second allowances per RND and feature, name removal, relevant referral/medication context, concise P/E/S justifications in existing notes, and reopening only for relevant evidence changes or related deletion. No diagnosis-count tooltip, visible citations, passage highlighting, vector database, training, or automatic retries.

## Tasks

- [x] Add a shared, versioned context library with backend selection and synthetic privacy/selection tests. Limit clinical families to current intervention goals; no inferred medical diagnoses.
- [x] Update diagnosis evidence, eligibility, schema and note grounding. Preserve existing field limits, patient evidence references and clinical guardrails. Add focused tests before implementation.
- [x] Replace generation quotas with independent feature allowances; preserve administrator token caps and unrelated endpoints. Verify tenth/eleventh requests and expiry.
- [x] Make review outcomes explicit and bounded. Review all eligible candidates once, cache valid empty results, distinguish unusable output, invalidate relevant scopes, and reopen related deleted drafts/diagnoses. Preserve duplicate prevention.
- [x] Add monitoring context and unchanged-input draft reuse. Preserve chronology and stale-result checks; keep output as progress assessment without treatment advice.
- [x] Update web contracts, notes, allowance expiry and errors. Verify tests, TypeScript, lint and build; update existing AI/data guides and affected storyboard wording.

## Verification

Use a newly created disposable MySQL database and schema-scoped account, isolated cached configuration that bypasses dotenv loading, fake HTTP/mail and private scratch storage. Run affected PHPUnit/Vitest suites first, then required broader checks. Record failures honestly; never use patient data or paid generation for automated checks.

## Decisions

- Reuse the existing services, draft metadata and notes; no new database schema.
- Keep three suggestions per response separate from request allowances.
- Generated justification notes retain the existing 100-character limit; manual fields remain 125 characters.
- Failed/rejected requests never cause automatic retries or a false clinical 'normal' result.
- Existing daily-counter rows remain historical; stop consuming them rather than rewriting migration history.

## Verification results — 2026-10-24

Isolation attribution correction (2026-10-25): the earlier drive-letter cached-config path did not resolve under Laravel on Windows. Earlier backend counts below came from the repository-configured test database, not the intended disposable schema, and are not evidence of isolated execution. Normal dotenv loading was not bypassed in those initial runs; no environment values were printed. The classification pass below records the corrected guard and final isolated results.

- Final affected backend sweep: **167 tests / 1,803 assertions passed**, covering context/privacy, eligibility, PES output/notes, review outcomes/invalidation/deletion, medical-label and medication-causality rejection, independent allowances, monitoring reuse/concurrency/chronology and administrator token caps. Synthetic HTTP only.
- Full frontend: **217 files / 806 tests passed**. After Help wording changes, affected Help/service/page/form checks were rerun: **27 passed**. TypeScript and ESLint passed.
- Production builds passed. The final build used a clean source copy excluding dotenv files and webpack; it retains the existing middleware deprecation warning. The first build's preload did not prevent Next's local dotenv loading; it was removed and the clean-copy verification replaced it. No environment contents were printed.
- PHP Pint, route inspection/cache compilation, documentation links/fences and Git whitespace checks passed. Auth/role middleware remains on both generation endpoints; overlapping AI generation throttles were removed.
- Synthetic local browser checks at desktop and 320 CSS pixels verified diagnosis notes/actions, counters, monitoring generation/regeneration and preservation of existing text. No visit/diagnosis was saved during browser checks. Screenshots are in ignored `output/playwright/`; viewport reset and temporary servers stopped. This is local UI verification, not deployed acceptance.
- Wider backend sweep: **1,936 tests; 1,912 passed; 19 assertion failures and 5 SQLite-driver errors** at that point. Task-related name access and old monitoring-contract assertions were corrected and covered by the final green sweep. SQLite cases subsequently passed (**5 tests / 99 assertions**) with the bundled driver enabled for testing. Follow-up Unit suite: **473 tests; 465 passed; 8 unchanged deployment/documentation assertions failed**. Other wider failures concern unchanged report formatting. The repository-wide suite is not claimed green.
- Schema-admin tests creating/dropping additional databases were excluded; test-account privileges were not broadened. No production migration, paid provider generation, commit, push or deployment occurred. Existing unrelated storyboard/mobile changes remain intact.

## Approved review corrections — 2026-10-25

- [x] Ground selected problems in the corresponding nutrition findings; reject unsupported custom problems and preserve valid intake/custom drafts.
- [x] Include only relevant prescription guides for recalled nutrients, keeping recall estimates separate from entered readings.
- [x] Preserve behavioral drafts/reviews when uncited clinical medication context changes; retain relevant/cited invalidation, including citations across multiple custom drafts sharing a candidate.
- [x] Preserve clinical numeric ranges while removing known identifiers and actual phone/email contacts.
- [x] Keep confirmed conditions when another finding is negated; use potassium guidance that does not assume high versus low.
- [x] Remove domain-based reordering and duplicate review metadata. Run regression checks and refresh affected guides.

Ruling: use existing services and conservative evidence checks, not another AI call or a second clinical rule engine. Reuse the isolated test configuration/schema, preserve unrelated edits, and do not commit/push/deploy. Prior wider-suite deployment/docs/report failures remain outside this correction scope.

Correction verification: regression failures reproduced before fixes; affected sweep **179 tests / 1,847 assertions passed** using synthetic HTTP (`corrections-verified.xml` in ignored scratch storage). Its initial Windows cache-path setup was later found not to resolve; see the isolation correction above and final classification verification. Pint, affected documentation links/fences and Git whitespace checks passed. Provider prompts remained bounded at 1,623 diagnosis characters and 1,457 monitoring characters; the library still has 12 shared cards. No frontend code changed in that correction pass. No paid provider call, production migration, commit, push or deployment occurred.

## Approved classification alignment — 2026-10-25

Design: BMI classification is derived from measured BMI using the existing Asia-Pacific adult bands: Underweight <18.5, Normal <23, Overweight <25, Obese Class I <30, Obese Class II >=30. %IBW stays a separate measurement. Neither a BMI class nor a screening label establishes malnutrition or its severity. Existing PES evidence checks and RND review remain. No new schema or classification service; reuse Assessment, current frontend calculation helpers and report owners.

Rulings: user approved the six-part proposal and clarified that PES review already exists; execute inline without another approval gate. No subagent/worktree, commit, push or deployment authorized. Reuse the existing AI adult boundary (18 years) consistently for BMI classification; census age buckets remain unchanged. Return no adult class for missing age/measurements, pregnancy/lactation or edema, preserving the existing conservative AI exclusions. Keep legacy screening-derived nutritional_status storage for compatibility/history, exclude it and numeric risk from AI, and expose a distinct derived bmi_classification. No historical diagnosis or completed report snapshot is rewritten.

- [x] Add regression tests, then an Assessment classification method and derived API field. Verify BMI boundaries, invalid/missing data and population/fluid exclusions independently of %IBW and screening labels.
- [x] Align existing frontend BMI calculation/assessment summaries and monitoring displays; remove unsupported automatic malnutrition labels and the >=35 severity subdivision. Keep original measurements and form requirements.
- [x] Send compact diagnosis anthropometrics with explicit Asia-Pacific classification; omit screening status/risk and redundant height. Include raw weights only for relevant weight-loss candidates. Align overweight eligibility and validation at 23, and keep malnutrition checks independent. Bump review/prompt versions and test fake provider payloads/cache behavior.
- [x] Include derived current BMI classification in monitoring context while preserving previous/current measurements and target comparisons; no risk payload or extra provider call.
- [x] Reuse the Assessment classifier for current census counts, refresh current classification labels/basis version, preserve historical snapshots, and inspect a synthetic rendered PDF.
- [x] Run affected backend/frontend tests, required broader checks, Pint/type/lint/build and local UI verification. Update existing guides, Help/storyboard where wording changes; self-review privacy, unsupported cases, history and token bounds.

Review focus: BMI 23 must pass both eligibility and validation; low %IBW must not relabel a normal BMI; low BMI/high screening risk must not establish malnutrition; unknown/pediatric/maternal/edema contexts must not receive an adult class; payload trimming must preserve relevant weight-loss evidence and monitoring chronology.

Pediatric clarification: the initial adult-only design withheld automatic WHO growth assessment. The user subsequently approved the bounded lookup below, superseding that limitation and the briefly discussed Asia-Pacific-for-all-ages fallback. The MUAC screen is limited to known ages 6–59 months with screening observations, preserving measurements; sources are WHO 2023 identification guidance and September 2026 implementation guidance. BMI displays two decimals so 22.99 is not shown as 23.0 beside Normal.

## Approved WHO lookup and delivery — 2026-10-25

Use the existing model/frontend calculation helper with offline official WHO expanded-table thresholds for each sex and completed month 61–228. These are the actual published bounds: no invented month-60 row, interpolation or runtime download. Month 60 has a numeric BMI without classification; Asia-Pacific applies from month 229. Ages 0–4 omit BMI in new assessment/monitoring writes, API, AI and new reports. Raw measurements and historical stored values remain. Pediatric adult-panel IBW/BMR/TEE estimates and fixed monitoring IBW/BMI targets are withheld; existing prescription formulas are unchanged. Published 0.001 thresholds, source URL, retrieval date and workbook hashes are retained in identical backend/frontend JSON copies because their Docker builds are separate. This is a category lookup, not an exact z-score engine or malnutrition diagnosis.

The BMI tooltip reuses InfoHint beside the existing label, with a fixed-height header to preserve value alignment. It briefly explains references, supported ages, omitted calculations and clinical exclusions. No added service, dependency, schema, AI call or context card is required. Catalog v6 and monitoring prompt v5 invalidate prior behavior. Tests cover thresholds, sex, unsupported ages, adult transition, source-copy parity, under-five history preservation and safe pediatric AI/monitoring behavior.

Delivery authorization now includes committing/pushing task changes to the repository release target main and testing the deployed RND workflow with the supplied account. Preserve unrelated storyboard/mobile changes. Automated tests use fake HTTP only; live AI smoke testing is limited to one diagnosis generation and one monitoring generation, investigating further only if these expose a failure. Do not record credentials or patient information in source/docs.

Local completion evidence: final isolated affected PHPUnit run passed 285 tests / 2,419 assertions; frontend passed 217 files / 810 tests plus 65 legacy Node checks. Pint, TypeScript, ESLint and a dotenv-free production build passed. Nine affected documentation link/fence checks and Git whitespace checks passed. Actual synthetic census/NCP PDFs were regenerated and visually inspected. Browser inspection at desktop and 320 pixels confirmed tooltip fit and aligned BMI/IBW values; the viewport was reset and no clinical form saved. Self-review and Ponytail review retained the existing owners and two small static data copies, with no new dependency, schema, growth engine or provider request. These are local checks; deployed acceptance is recorded separately after push.

## Approved deletion/retry correction — 2026-10-26

Release 3b02d5bbe25b292ae0c978cb3a8ca6ae00ab28c9 was pushed to main and its deployment workflow completed successfully. The user reported repeated generation rejection and a disabled Generate after deleting two old diagnoses. Regression checks reproduced a closed current Other candidate when deleted legacy provenance no longer matched the catalog, partial rejection without deliberate retry, and the disabled exhausted button.

Reuse the existing review metadata: match deleted problem components against current cards and reopen the domain's Other candidate only when legacy/missing provenance cannot identify a current problem. Preserve unrelated reviews, valid drafts, duplicate checks and clinical validation. Partial as well as entirely rejected responses permit deliberate retry; no automatic refill or provider retry. Log static per-draft rejection reasons without clinical values. Catalog v7 invalidates reviews completed under the prior behavior, including deletions already performed before this fix.

Generate remains clickable when evidence is exhausted. Its request returns a warning without reserving allowance or calling the provider; missing assessment, active generation, unavailable service and the ten-request limit remain guarded. Clear stale warnings when relevant review becomes available. Existing manual diagnosis/intervention/monitoring behavior stays unchanged. Use the existing disposable schema/config for sequential fake-provider tests. Preserve the concurrent session's unrelated Git changes and stage only this correction. After verification, push through the existing release and complete one diagnosis/one monitoring live smoke check; investigate further only when these reveal a failure.

Correction verification: both backend regressions failed before implementation; the exhausted-button browser-component check also failed before its fix. Final isolated affected backend suite passed 286 tests / 2,437 assertions. The clean release snapshot plus this correction passed all 217 frontend files / 811 tests with two workers and a 20-second timeout for host contention; affected source checks passed separately. Source TypeScript and ESLint passed in that snapshot. Generated Next page-validator artifacts were excluded from the source-only type check because the baseline already exports MenuTemplateDirectory from its page; that unchanged issue and concurrent food-service test/type failures were not modified. The dotenv-free webpack production build passed. Pint, documentation links/fences and task whitespace checks passed. User identified Maria Santos as the fictional live QA target; her pre-fix page reproduced disabled Generate with no supported evidence and 0/10 allowance. No new live provider call or clinical save has occurred during this correction yet.

## Producer correction found in live QA — 2026-10-26

The deletion/retry correction 0ec623bc deployed successfully. Maria's first live diagnosis request still rejected all drafts, while Retry remained available. A single backend diagnostic retry with process-only logging revealed missing custom problem text, text over the existing limits, and unsupported rewriting. The first diagnostic setup stopped before a provider call because its logger method was unavailable; the corrected retry used the existing service and cached configuration. No assessment, saved diagnosis, visit, production file or persistent configuration was changed. An uncommitted in-memory code preview was rejected by automatic approval review and was not executed; verification and release use the normal pipeline instead.

Require the problem label in the native schema, add supported simple regex bounds for core text/notes, and request short copied source phrases for E/S. Do not weaken clinical validation, raise output-token limits, alter models or add retries. Retain static aggregate rejection reasons at production's error level. Native schema-contract regression failed before implementation; the final affected AI/PES/monitoring regression run passed 150 tests / 1,730 assertions. Diagnosis prompt remains 1,879 characters and max_tokens remains 1,000. Catalog v8 invalidates the previous format. The separate monitoring live check passed on its first request with a 518-character assessment; no visit was saved. Keep the concurrent session's already-pushed db129aa7 work separate from this correction.

Deployment d065bad4 completed and both running image revisions matched. The real diagnosis request exposed a provider schema incompatibility before any draft: HTTP 400, "Cannot apply a range quantifier to this regex." One diagnostic request confirmed this static error through the existing deployed service. Remove the rejected patterns, retain length descriptions, required p and short-copy instructions, and preserve all server validation. The regression rejecting provider-incompatible constraints failed before this correction; the isolated AI service/eligibility/context sweep then passed 39 tests / 590 assertions. Pint, nine document link/fence checks and task whitespace checks passed. No automatic retry, clinical save, schema migration or source replacement in production is involved.
