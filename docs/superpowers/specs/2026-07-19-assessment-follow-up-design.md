# Assessment Follow-up, Diagnosis, and SOP UX Design

**Date:** 2026-07-19

**Status:** Approved through the owner's written corrections and explicit "yes".

## Scope

Primary scope remains the NCP Assessment page and shared patient header. The owner also explicitly added two narrow adjacent fixes: AI diagnosis editing/preview behavior and SOP history presentation. No Laravel schema, controller, validation, or clinical calculation changes are required.

## Assessment field behavior

- Every Assessment textarea keeps its existing `rows` value as the minimum height.
- Textareas measure their own `scrollHeight` after load and after input, then grow vertically to reveal all content.
- Textareas use `overflow-hidden` and no resize handle, so fields and sections never become nested scroll containers.
- Form grids top-align children. A long field grows independently; neighboring fields retain their natural height.
- Only the page/tab canvas scrolls. Mobile keeps one column and no horizontal content overflow.

## Assessment page corrections

- Place the single `Save Assessment` action immediately above the Assessment tab card. Remove cycle/auto-merge copy.
- Retain local form state and existing explicit `saveAssessment` call. Field edits do not call persistence. Upload remains a separate explicit upload action.
- Move Lab Results attachments after the lab grid. Referral attachments remain after referral fields, creating the same bottom-placement pattern.
- Remove the standalone Nutritional Status/Suggested Goal banner. Show the existing Asia-Pacific BMI classification inside the BMI metric card. Reuse the current nutrition calculation classification rather than introducing new cut-points.
- Make summary output clinically selective: key anthropometrics, key intake/GI findings, abnormal labs (or a short all-entered-normal statement), limited clinical context, and at most three risk factors. Source fields remain complete and editable.
- Add a collapsed `How automatic scoring works` disclosure showing the exact current factors and thresholds. This explains current logic without altering it.

## Patient header

The shared card shows only available clinical context from this allow-list:

- patient name;
- physician;
- risk score;
- allergies, dietary restrictions, and food dislikes;
- selected intervention goal;
- referral medical diagnosis;
- text-only `Change Patient` action.

Remove step labels, ward, system/cycle IDs, arbitrary diagnosis-count badges, and all icons. Values wrap on small screens; no value is hidden in a dark popover.

## Save behavior audit

- Assessment: one explicit `Save Assessment`; unsaved field edits remain frontend state.
- Diagnosis: one explicit `Save Diagnosis`/`Update Diagnosis`; AI `Edit` loads a draft and does not persist until that save action.
- Intervention: prescription, education, counseling, and encounter forms already persist only through their explicit save buttons. Goal selection is an explicit confirmation that currently persists; label it `Save Goal` so the mutation is not ambiguous. Do not redesign intervention persistence in this assessment-focused change.
- Monitoring: `Save Visit` is the explicit creation action.

## AI diagnosis

- `Edit then Accept` becomes `Edit`.
- Editing an AI suggestion hydrates matching problem, etiology, and sign checkboxes using the existing diagnosis component matcher. Unmatched clinical detail stays in notes.
- Edited AI content remains a local builder draft until `Save Diagnosis` is clicked.
- Replace dark preview panels with light neutral surfaces, clear P/E/S labels, readable warm text, and existing accessible focus states.

## SOP history

- Main actions read `History` and `Edit` (or `Set SOP` when empty); modal heading reads `Edit SOP`.
- History remains newest-first and paginated.
- Each entry is a native collapsible disclosure labeled automatically by its completion date/time. Title, author, role, body, and Current status appear only when expanded.
- No custom history naming field is added.

## Accessibility and responsive rules

- Preserve visible labels, 16 px field text, keyboard focus, and 44 px primary targets.
- Avoid nested scroll regions and horizontal page overflow.
- Native `details/summary` supplies keyboard-operable collapse state for risk and SOP history.
- Test at 375/390 px mobile and 1440 px desktop.

## Explicit exclusions

- No new clinical thresholds, risk-score rules, migrations, or API endpoints.
- No autosave.
- No global NCP persistence rewrite.
- No change to attachment upload semantics.
- No unrelated NCP, announcements, audit, or profile redesign.
