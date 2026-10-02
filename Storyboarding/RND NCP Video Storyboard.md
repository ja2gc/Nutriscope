# RND Nutrition Care Process Video Storyboard

Verified against the current RND web workflow and rendered clinical reports on **2026-09-28**.

Use this as the recording checklist and narration script for a future **Help → Guides** video. Record in a disposable demo database. Do not show real patient information.

## How to Use This Script

1. Complete **Recording Setup** before filming.
2. Record scenes in order and confirm each **Expected result**.
3. Follow **On-screen actions** while recording and use **Narration** as the spoken explanation.
4. Keep patient identifiers, contact details, clinical notes, and uploaded files fictional.

**Overall sequence:** Open patient → schedule or start visit → continue ADIME work → finish visit → manage attendance → protect or discontinue cycle → review past records and reports.

## Recording Setup

- Use one RND demo account. Use seeded fictional Maria Santos for the active diabetes workflow and Roberto Reyes for separate current/past malnutrition-cycle history; create a disposable fictional maternal-status cycle only if the seeded data cannot demonstrate the modifier safely.
- Prepare fictional Assessment, Diagnosis, Intervention, Monitoring, and meal-plan data. No patient marker controls AI availability; privacy comes from fictional recording data plus the server's bounded de-identified provider payload.
- Prepare one due-tomorrow scheduled appointment so the real reminder workflow can be shown, and leave space to create a walk-in.
- Use no real-person names, hospital numbers, contact details, diagnoses, or documents.

**Exact clinical acceptance sequence:** Assessment save/reload → cycle category and census → bounded PES drafts/manual fallback → first complete dated Intervention Plan → carb-aware, fluid-free sole-menu generation/scaling → complete Monitoring snapshot → new plan prefilled from newest plan and latest Monitoring → distinct dated-plan reports plus newest-only NCP Summary → frozen archive check.

## Scene 1 — Open the Patient Record

**Purpose:** Introduce the patient profile without technical identifiers or audit panels.

**On-screen actions**

1. Sign in as RND and open **Nutrition Care → Patients**.
2. Select the fictional patient.
3. Show **Overview**, **ADIME Records**, **Appointments**, and **Attachments**.
4. Open the protection-rules help icon.

**Narration**

> The patient profile keeps clinical cycles, visits, and supporting files together. Technical cycle identifiers and patient audit panels are not part of the clinical working view. The help icon explains when a patient or cycle can be deleted and when a completed record becomes protected.

**Expected result:** The patient profile shows the four working tabs and the protection guidance.

## Scene 2 — Schedule a Visit

**Purpose:** Create an appointment with a useful written purpose.

**On-screen actions**

1. Open **Appointments** and select **Schedule or Walk-in**.
2. Choose **Scheduled**, enter a future date/time, and write a purpose that may cover multiple steps, such as **Complete assessment and review diagnosis**.
3. Save the schedule.
4. Return to the RND dashboard and show the appointment queue.
5. Open **Notifications**, select the appointment reminder, and confirm it opens the exact patient appointment. Return and point out that **Dismiss** remains visible but disabled while action is required.

**Narration**

> A schedule records when the patient is expected and why the visit is planned. The purpose is free text because one visit may cover more than one ADIME step. A scheduled visit does not start automatically. The reminder opens the record where the work can be completed; reading it is not the same as resolving it, so an action-required reminder cannot be dismissed yet.

**Expected result:** The appointment appears on the patient record and dashboard as Scheduled, and its reminder opens the exact appointment while dismissal remains disabled.

## Scene 3 — Start and Resume the Visit

**Purpose:** Show explicit visit start and persistent patient context.

**On-screen actions**

1. Open the scheduled patient and enter any NCP step.
2. Open the patient's **Appointments** tab and select **Start Visit** for a future scheduled appointment. Overdue appointments cannot start; reschedule, cancel, mark no-show, or create a new appointment instead. The shared patient header keeps only **Start Walk-in** and **Schedule Next** when no visit is active.
3. Navigate to another RND page, then point out the global active-visit banner.
4. Select **Resume**.

**Narration**

> Starting is an explicit action. While the visit is active, NutriScope remembers the patient and NCP cycle on the server. Leaving the page or reopening the site does not silently finish the visit. The Resume banner returns the RND to the active patient.

**Expected result:** The visit is In progress and remains resumable after navigation.

## Scene 4 — Work Across ADIME Steps

**Purpose:** Show that visit state is universal rather than tied to Intervention.

**On-screen actions**

1. In Assessment, enter one weight-duration quantity plus weeks/months, select the required Primary diagnosis category, show conditional **Specify category** for Other, and choose a confirmed Pregnancy/Lactation status. Save/reload; show no Stress Factor control and no fixed three-month copy.
2. Continue to Diagnosis, open **AI Review**, and choose **Generate AI Suggestions**. Show zero to three results paginated two per page with Evidence used, unchanged-data cache, Dismiss, and Assessment-change refresh. Choose **Edit** and show matching Problem/Etiology/Signs as selected options or checked boxes while unmatched detail remains in notes; save through the normal PES flow. Show the manual builder as fallback. Source provenance remains part of the server-side rule contract and is not shown on the draft card.
3. Continue to Intervention, choose a goal and applicable stage, then expand the calculation panel. Show goal-calculated baseline, maternal modifier, final prescription, and separate fluid guidance before saving.
4. Load a seeded goal template and show its exact items plus goal/stage/maternal compatibility, then choose **Scale to prescription** to adjust quantities without replacing foods. For the fictional maternal scenario, choose the separate NNC/DOH-derived Pregnancy or Lactation example that matches the Assessment, using its snack or no-snack variant as appropriate. Confirm fluid does not influence scaling variance or success, while the selected day separately shows approximate fluid from foods and remaining drink guidance.
5. Save the first complete dated Intervention Plan, then create its sole menu. Show the compact Auto-Generate options: **Exclude snacks** (and the liver-disease exception) plus **Use rice as carb**. Generate with the selected preference; confirm the carbohydrate is a separate scalable item, the off state excludes rice sides, and a complete rice-based dish receives no duplicate side. Confirm another menu cannot be added to the same plan. For a confirmed pregnant/lactating fictional patient, show that composition and the final prescription both reflect maternal context while the clinical goal remains authoritative.
6. In Monitoring, confirm the patient code and cycle start, complete the follow-up Assessment, and save one visit with **Update care plan** closed. On a later fictional visit, open it, verify current values are prefilled, enter effective date/reason, revise one target, select **Open Intervention after saving**, and save.
7. Open the saved visit detail and confirm it contains Monitoring data only, with empty optional sections omitted and no Intervention controls. Then open Intervention **Plans**, view the earlier plan read-only, create a new dated plan prefilled from the newest plan, confirm calculations use the latest Monitoring snapshot, and verify the earlier menu is not copied.
8. Point out the same visit bar on every step.

**Narration**

> A visit may include one step or several. Assessment keeps weight duration, census category, and maternal status explicit. PES assistance only words deterministic source-backed candidates; matching choices reappear as selections when edited, and manual entry remains available. Intervention shows complete dated plans and the final prescription after any maternal modifier, while fluid stays guidance outside meal scaling. Monitoring captures complete follow-up calculation inputs but does not revise treatment. When treatment changes, the RND creates a new dated plan in Intervention; it starts from the newest plan and latest Monitoring values, while prior plans remain read-only and keep their own sole menus. Each clinical save records which sections were worked on and which became complete during this visit. Visit completion and NCP-cycle completion remain separate decisions.

**Expected result:** The visit shows the sections worked on without forcing the RND to predict one next step.

## Scene 5 — Finish, End Early, or Correct a Mistaken Start

**Purpose:** Explain the small set of visit-ending actions.

**On-screen actions**

1. Select **Schedule Next** and create the next appointment when needed.
2. Select **Finish Visit** for the active demo visit.
3. Return to **Notifications** and show that completing the appointment resolved the reminder, then dismiss it.
4. Start a disposable empty walk-in, then select **Discard Mistaken Start**.
5. Start another disposable visit, choose **End Early**, select a reason, and confirm.

**Narration**

> Finish Visit records that the appointment ended normally and resolves the related action-required reminder even when the work was completed from the patient workflow rather than by opening the notification first. The resolved reminder can then be dismissed. End Early records an interrupted visit with a reason. Discard Mistaken Start is available only while no clinical work has been saved. Scheduling the next visit does not complete the NCP cycle.

**Expected result:** Appointment history distinguishes Completed, Ended early, and the safely discarded empty start.

## Scene 6 — Record Cancellation, No-show, and Rescheduling

**Purpose:** Resolve scheduled attendance without creating duplicate visit logs.

**On-screen actions**

1. From the dashboard or patient Appointments tab, reschedule one future appointment.
2. Show the old row marked **Rescheduled** and its new **Scheduled** replacement.
3. Mark another appointment **No-show**.
4. Cancel another appointment and choose a cancellation reason.

**Narration**

> Rescheduling keeps the original appointment for history and creates a linked replacement. No-show means the patient did not attend. Cancelled means the visit was called off and includes a reason. These attendance states do not count as completed clinical care.

**Expected result:** Past Appointments preserves each outcome and the dashboard removes resolved schedules from the active queue.

## Scene 7 — Complete, Discontinue, or Delete a Cycle

**Purpose:** Keep cycle lifecycle actions deliberate and separate from appointments.

**On-screen actions**

1. Open **ADIME Records → Current Cycle → Actions**.
2. Show that **Complete and Protect** requires clinically complete Assessment, Diagnosis, and Intervention.
3. In a disposable incomplete cycle, show **Discontinue**, select a reason, and confirm.
4. Show **Delete** only on an incomplete, unprotected cycle.

**Narration**

> Complete and Protect closes a clinically complete cycle and prevents deletion. Discontinue closes care before completion while preserving the reason. Delete is limited to cycles that have not completed Assessment, Diagnosis, and Intervention. Starting a new cycle never changes earlier records.

**Expected result:** Terminal cycles move from Current Cycle into Past Records; protected cycles cannot be deleted.

## Scene 8 — Review Past ADIME and Meal-plan Reports

**Purpose:** Show preserved history and report access.

**On-screen actions**

1. Open **ADIME Records**.
2. Show **Current Cycle** and the always-visible **Past Records** section.
3. Move through Past Records pagination, two items per page.
4. Select **Meal Plan 1** on a record.
5. Show the long-bond PDF preview: identity/prescription and **Weekly Meal Plan** first, concise education/counseling/barriers/strategies immediately after the menu, then compact three-column portions. Confirm empty snack rows disappear. Point out that a repeated food has one card while each distinct saved amount keeps its menu reference. Show the view/download controls used by Reports.
6. Open **Reports → Browse → Clinical → Patients NCP**, choose the same patient, select one ADIME cycle, and show only that cycle's Nutrition Intervention Plan and NCP Summary.
7. Return to **Patients**, search the same person using the `NS-XXXX-XXXX` patient ID, and show that the ID appears only beneath the name in the patient profile header.
8. Open **Reports → Browse → Clinical → Demographic Census**. Show that the month list begins with the earliest ADIME cycle, includes the live current month, and counts separate cycles for the same patient separately. Confirm the age/sex matrix and **By Risk Level** breakdown are present, with no ward, diagnosis-category, or nutritional-status breakdown. Existing prepared archives remain frozen and may retain their historical sections.
9. Return to the patient record and confirm that merely viewing or downloading the report did not change **Last clinical action by**; when no qualifying save exists it reads **No action recorded**.

**Narration**

> Past Records preserves finished and discontinued ADIME cycles separately from the current cycle. A past appointment's linked ADIME cycle is history text, not a clickable shortcut. Saved Intervention Plans appear newest first by date, and each plan with a menu opens its own report preview from Patients NCP; a no-menu plan remains listed but unavailable. Nutrition Intervention Plan prints its saved menu before concise guidance and compact precise portions without preparation instructions. Repeated foods share one detail card while menu references preserve different saved amounts. NCP Summary uses the newest saved plan only. Patients NCP keeps current and completed cycles separate. The same patient search accepts name, physician, hospital number, or the random patient ID shown beneath the profile name. Demographic Census counts each non-deleted cycle once in its start month and Assessment category bucket, freezes completed months, and keeps the current month live. All reports use 8.5 × 13-inch long bond paper with the report's portrait or landscape orientation. Preparing an archived copy freezes its exact PDF, branding, signatories, and source values; later plans do not reinterpret it. Passive page views and downloads do not change clinical attribution.

**Expected result:** Past records remain visible even when empty, pagination is present, patient-ID search finds the correct profile, each dated saved plan opens its own Nutrition Intervention Plan when a menu exists, NCP Summary uses the newest plan only, archived bytes remain frozen, Patients NCP keeps report cycles separate, and Demographic Census counts all existing ADIME cycles from the earliest cycle month.

## Closing Shot

**Narration**

> NutriScope separates appointments, visit activity, ADIME work, and cycle closure. That keeps attendance history clear while protecting completed clinical records.

## Recording Cleanup

- Reset the disposable demo database after filming.
- Do not attempt to delete protected completed cycles.
- Remove only disposable schedules or draft records created for the recording.
