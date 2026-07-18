# NutriScope Assessment UI/UX Design

**Date:** 2026-07-18

**Scope:** Assessment step tabs and shared active-patient header only. Diagnosis, Intervention, and Monitoring stay unchanged except for the shared header's presentation.

**Decision:** Compact through grouping, responsive columns, and progressive disclosure. Keep current font sizes and clinical behavior. Add deterministic, user-triggered summary drafting that writes to the existing editable `rnd_summary` field.

## Current-state evidence

Primary UI is one client component: `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`. Shared patient card is `frontend/app/(rnd)/ncp/_components/NcpPatientHeader.tsx`.

Current primitives use visible labels plus 38 px text/select controls, default 3-row textareas, 16 px grid gaps, and 20 px tab padding. Patient header uses 28 px vertical padding around three stacked identity lines, producing an approximately 86 px desktop card before page gaps. These are CSS-derived measurements from current DOM/classes; a second headless-browser measurement was blocked by workspace approval spend cap, so no unsupported pixel-perfect runtime claim is made.

| Tab | Current structure and controls | CSS-derived content height | Main offenders |
|---|---:|---:|---|
| A. Dietary | 11 clinical fields; 8 text/narrative controls | about 730 px before tab padding/nav | Six textarea rows, full-width narratives, sequential two-column flow, separate GI block |
| B. Anthropometrics | 6 metric cards, status banner, 8 inputs | about 500 px before tab padding/nav | Metric strip takes two rows at desktop; inputs take three rows |
| C. Client History | 10-11 fields depending on edema | about 560-640 px before tab padding/nav | Medical/social narratives, allergy chip wrap, mostly two-column layout |
| D. Biochemical | 17 lab cards plus always-expanded documents panel | about 950 px before tab padding/nav | Six rows of cards; reference notes always visible; documents consume a side column |
| E. Referral/Screening | 13 demographic/referral fields, 15-19 condition checks, 8 intake/history checks, documents | about 1,230 px before tab padding/nav | Three distinct tasks rendered together; largest unavoidable scroll burden |
| F. RND Summary | 2 duplicate weight fields, 8-row summary, 7-factor risk panel | about 740 px before tab padding/nav | Summary and risk panel stacked; weight fields duplicated inside risk panel |

At a 1366×768 clinical workstation, global page chrome adds roughly 220 px before the save bar: breadcrumb, gaps, current patient card, tab navigation, tab padding, and save bar. Therefore Biochemical and Referral are severe offenders; Dietary and Summary are next; Client is moderate; Anthropometrics is closest to the goal.

Current summary persistence already exists end-to-end:

- `rnd_summary` is part of the frontend `Assessment` contract and current save payload.
- Laravel model fillable/resource/Form Requests already include it.
- MySQL migration stores it as nullable `text`.

No schema, API, controller, AI service, or background job is needed.

## UX principles used

- Preserve visible labels, logical DOM order, keyboard order, and clear focus states. USWDS recommends fieldsets for thematically related controls and warns against changing control order with CSS ([USWDS Form guidance](https://designsystem.digital.gov/components/form/)).
- Keep controls comfortably operable. WCAG 2.2 requires at least 24×24 CSS px or sufficient spacing; 44×44 remains the enhanced target for important controls ([W3C target-size guidance](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum), [WCAG 2.2](https://www.w3.org/TR/WCAG22/)).
- Retain visible keyboard focus and predictable focus order ([W3C WCAG 2.2 understanding index](https://www.w3.org/WAI/WCAG22/Understanding/)).
- Add character counters only when a real, clinically approved limit exists; counters should explain an actual maximum, not invent one ([GOV.UK character-count guidance](https://design-system.service.gov.uk/components/character-count/)).
- Use 4/8 px spacing rhythm, on-blur validation, and progressive disclosure from UI/UX Pro Max. Do not reduce font size.

## Proposed layout by tab

### A. Dietary History

Use three light fieldsets, each with a short legend and responsive grids:

1. **Diet and intake:** Present Diet and Dietary Intake remain primary narratives; energy status, method, appetite, and restrictions sit beside them at `xl` width.
2. **Modifiers:** Supplements, Knowledge/Beliefs, and Nutrient-Drug Interaction share one row where space permits.
3. **GI/tolerance:** Chewing/swallowing, constipation, and diarrhea remain three equal columns.

Narrative controls start at two rows, keep the same text size, remain vertically resizable, and preserve all content. No hard character/word limits are added: current backend has no domain-approved limit, and truncating clinical narratives risks lost detail. Expected desktop body height: about 340-400 px.

### B. Anthropometrics

- Change calculated metrics from 3 columns to 6 columns at `xl`, retaining 3 columns at medium widths and 2 on small screens.
- Keep value typography unchanged; reduce only card padding and redundant whitespace.
- Keep nutritional status as one compact full-width banner.
- Change measurement inputs to 4 columns at `xl`, producing two rows instead of three.

Expected desktop body height: about 310-340 px. All formulas, required fields, bounds, and calculated values remain unchanged.

### C. Client History

Create three fieldsets:

1. **Clinical and social context:** Medical History, Social History, Religion/Dietary Practices.
2. **Calculation context:** PAL, stress factor, pregnancy/lactation, edema, conditional dry weight.
3. **Food safety and preferences:** allergies, dislikes, medications.

Use 3 columns at `xl`; keep conditional dry weight behavior. Allergy pills gain `aria-pressed` and usable target height without changing hard-filter logic. Narrative controls start at 2-3 rows and remain resizable. Expected body height: about 380-450 px.

### D. Biochemical / Labs

- Give labs full tab width; stop reserving a permanent side column for documents.
- Use a 4-column lab grid at `xl`, 3 at large, 2 at small/tablet, 1 on mobile.
- Keep label, input, unit, reference range, and abnormal flag visible.
- Move verbose interpretive notes into native expandable `details` per lab. This is progressive disclosure, keyboard operable, and avoids hiding the actual normal range.
- Put supporting documents in one compact expandable region above the grid. Upload/list behavior remains identical.

Expected default desktop body height: about 560-650 px. Expanded reference notes or documents may intentionally add scroll.

### E. Referral / Screening

Split the single dense surface into an internal three-option section switcher:

- **Referral details:** demographic/referral fields, screening type, and expandable documents. Use 3 columns at `xl`; diagnosis stays wider.
- **Clinical conditions:** current adult/pediatric checklist, 3 columns at `xl`, 2 at medium.
- **Intake / weight history:** current checklist, 2-3 columns where labels remain readable.

Only one sub-section displays at a time. This reduces cognitive load and page height without deleting fields or changing checkbox state. Use semantic tab roles and keyboard-reachable buttons. Expected visible body: about 350-520 px depending on sub-section and adult/pediatric choice.

Risk: hidden sections can reduce overview. Mitigation: explicit labels, selected-state styling, stable position, and no automatic section changes.

### F. RND Summary

- Remove the duplicate weight-loss fields above the summary; they already exist in the risk panel.
- At `xl`, place summary editor and risk panel side-by-side. Stack on smaller screens.
- Put generation status and Generate/Regenerate action in a compact toolbar directly above the existing editable summary textarea.
- Keep risk scoring rules, automatic/manual switch, and saved values unchanged.

Expected desktop body: about 400-500 px.

## Slim patient header

Desktop becomes one compact row:

- heart icon;
- patient name;
- small step label;
- system and cycle IDs inline;
- Ward, Diagnosis, allergy, and risk badges;
- 44 px Change Patient action at right.

Use `px-4 py-2.5`, not smaller text. Diagnosis may truncate visually at a safe max width but exposes full text via `title`; patient name and identifiers remain visible. Mobile wraps naturally into two rows. Expected desktop height falls from about 86 px to 60-66 px.

Because this is the existing shared component, appearance changes on all NCP steps; no step logic or data changes.

## Auto-generated summary design

### Generation mechanism

Add a pure frontend function that accepts current Assessment values plus already-derived page context. It emits only non-empty categories, separated by one blank line:

1. **Anthropometrics:** weight, usual/dry weight when relevant, height, BMI, IBW percentage, weight direction/percentage/period, MUAC/classification, WHR/risk, nutritional status.
2. **Dietary / GI:** present diet, intake status and method, dietary intake notes, appetite, restrictions, supplements, GI/tolerance notes, knowledge/beliefs, nutrient-drug interaction.
3. **Biochemical:** entered numeric labs; abnormal values called out with LOW/HIGH using existing sex-aware ranges. If entered labs are all in range, say so and list available results concisely.
4. **Clinical context:** medical/social history, functional status, PAL, stress factor, pregnancy/lactation, edema/dry weight, allergies, dislikes, medications.
5. **Nutrition risk:** current automatic/manual risk label, points, and selected factors.

Generation is deterministic and local: no AI hallucination, API latency, PHI transmission, or new backend surface. Text values are trimmed and whitespace-collapsed, not silently truncated. Category is omitted when it has no meaningful content.

Referral screening checkboxes are excluded because they currently live only in local UI state and are not part of the saved Assessment contract. Including them would create a persisted summary from non-persisted source data.

### Update mechanism

Recommended: **manual Generate/Regenerate**, not per-keystroke or on-blur generation.

- Generate replaces `rnd_summary` with a current deterministic draft.
- Textarea remains freely editable immediately afterward.
- Store a canonical signature of source inputs in frontend state.
- Later source-field changes do not overwrite text; they show `Assessment changed — regenerate to refresh`.
- Manual summary edits show `Edited after generation`; source freshness and manual editing are distinct states.
- Regeneration stores previous text and exposes one-click Undo, avoiding accidental loss of clinician edits.
- If current inputs cannot produce any category, do not alter existing summary; show an inline accessible message asking for assessment data.

Tradeoff: manual generation can become stale. Stale badge and explicit Regenerate action solve this without keystroke jank or surprise overwrites. Debounced/live generation was rejected because it can distract, repeatedly rewrite clinician edits, and perform needless work on long notes.

### Partial and invalid input

- Empty strings, nulls, zero-as-empty where clinically appropriate, and non-finite numeric values are skipped.
- Paired calculations appear only when prerequisites exist; no dangling `BMI`, `%`, units, or punctuation.
- Conditional facts appear only when true/relevant (for example, dry weight only with edema).
- Lab status uses existing ranges and only valid numeric entries.
- Empty categories disappear; fully empty draft returns no summary and leaves current text untouched.
- Generated draft is labeled as a draft requiring RND review before save.

### Persistence and placement

Generated or edited text continues through existing Assessment save flow as `rnd_summary`. Generation metadata/signature remains frontend-only; no migration is justified. On initial load, current assessment values become freshness baseline. Any later source edit marks an existing summary potentially stale.

Summary stays in Tab F. It does not become a persistent side panel or add content to every tab, avoiding new scroll and distraction.

## Tradeoffs and exclusions

- **No hard text limits:** protects clinical detail; page density comes from initial textarea rows and responsive grouping. Longer text can still expand or scroll inside its control.
- **No global compact-mode redesign:** assessment-only styles; shared header is the sole intentional cross-step visual effect.
- **No AI service:** deterministic text is safer, faster, cheaper, and sufficient for structured inputs; prose may be less elegant but remains auditable.
- **No saved generation metadata:** avoids schema/API churn. Reload cannot prove whether saved text was generated or manual; freshness baseline handles later edits safely.
- **No referral-checkbox persistence change:** existing behavior is outside UI scope.
- **No sticky nested scroll containers:** avoids keyboard/focus and hidden-content problems. Main document remains normal scroll.
- **No font-size reduction:** compactness comes from layout, padding, and progressive disclosure.

## Implementation order

1. Add pure summary builder and tests for full, partial, empty, abnormal-lab, and safe-format cases.
2. Slim shared patient header; run shared-header contract tests.
3. Add assessment fieldset/layout primitives and compact Dietary, Anthropometric, and Client tabs.
4. Compact Biochemical labs and documents with progressive disclosure.
5. Split Referral/Screening into internal sections.
6. Integrate summary generation state, stale/edit/undo behavior, and side-by-side Summary/Risk layout.
7. Run focused tests, full frontend tests, TypeScript, lint on changed files, build, and source-level scope audit.
