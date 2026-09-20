# RND Nutrition Care Process Video Storyboard

Verified against the current RND web workflow on **2026-09-15**.

Use this as the recording checklist and narration script for a future **Help → Guides** video. Record in a disposable demo database. Do not show real patient information.

## How to Use This Script

1. Complete **Recording Setup** before filming.
2. Record scenes in order and confirm each **Expected result**.
3. Follow **On-screen actions** while recording and use **Narration** as the spoken explanation.
4. Keep patient identifiers, contact details, clinical notes, and uploaded files fictional.

**Overall sequence:** Open patient → schedule or start visit → continue ADIME work → finish visit → manage attendance → protect or discontinue cycle → review past records and reports.

## Recording Setup

- Use one RND demo account and one fictional patient with a current draft NCP cycle.
- Prepare fictional Assessment, Diagnosis, Intervention, Monitoring, and meal-plan data.
- Prepare one due-tomorrow scheduled appointment so the real reminder workflow can be shown, and leave space to create a walk-in.
- Use no real names, hospital numbers, contact details, diagnoses, or documents.

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
2. Select **Start Scheduled Visit** in the shared visit bar.
3. Navigate to another RND page, then point out the global active-visit banner.
4. Select **Resume**.

**Narration**

> Starting is an explicit action. While the visit is active, NutriScope remembers the patient and NCP cycle on the server. Leaving the page or reopening the site does not silently finish the visit. The Resume banner returns the RND to the active patient.

**Expected result:** The visit is In progress and remains resumable after navigation.

## Scene 4 — Work Across ADIME Steps

**Purpose:** Show that visit state is universal rather than tied to Intervention.

**On-screen actions**

1. Save fictional Assessment data.
2. Continue to Diagnosis and save a complete PES statement.
3. Continue to Intervention and save the prescription and goal.
4. Load a seeded goal template and show its exact items, then choose **Scale to prescription** to adjust quantities without replacing foods.
5. Show **Exclude snacks** on Auto-Generate. Explain that it redistributes snack targets to main meals and is unavailable for a liver-disease goal that requires frequent intake.
6. Point out the same visit bar on every step.

**Narration**

> A visit may include one step or several. Patient meal plans can use exact saved templates, scale their quantities to the current prescription, or generate a snack-free variant when the selected clinical goal permits it. Each clinical save records which sections were worked on and which became complete during this visit. Visit completion and NCP-cycle completion remain separate decisions.

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
5. Show the PDF preview, then the view/download controls used by Reports.
6. Open **Reports → Browse → Clinical → Patients NCP**, choose the same patient, select one ADIME cycle, and show only that cycle's Patient Menu Plan and NCP Summary.
7. Return to **Patients**, search the same person using the `NS-XXXX-XXXX` patient ID, and show that the ID appears only beneath the name in the patient profile header.
8. Open **Reports → Browse → Clinical → Demographic Census**. Show that the month list begins with the earliest ADIME cycle, includes the live current month, and counts separate cycles for the same patient separately.
9. Return to the patient record and confirm that merely viewing or downloading the report did not change **Last clinical action by**; when no qualifying save exists it reads **No action recorded**.

**Narration**

> Past Records preserves finished and discontinued ADIME cycles separately from the current cycle. A past appointment's linked ADIME cycle is history text, not a clickable shortcut. Meal plans use simple numbered labels and open the same report preview available from Patients NCP. Patients NCP is under Browse and Clinical: select a patient, then select one ADIME cycle so current and completed reports never mix. The same patient search accepts name, physician, hospital number, or the random patient ID shown beneath the profile name. Demographic Census counts each non-deleted ADIME cycle once in its start month, freezes completed months, and keeps the current month live. Prepared by comes from the RND responsible for the care cycle, and Attending physician comes from patient assessment data. Preparing an archived copy freezes its exact PDF, branding, signatories, and source values. Passive page views and downloads do not change clinical attribution.

**Expected result:** Past records remain visible even when empty, pagination is present, patient-ID search finds the correct profile, a numbered meal plan opens its PDF, Patients NCP keeps report cycles separate, and Demographic Census counts all existing ADIME cycles from the earliest cycle month.

## Closing Shot

**Narration**

> NutriScope separates appointments, visit activity, ADIME work, and cycle closure. That keeps attendance history clear while protecting completed clinical records.

## Recording Cleanup

- Reset the disposable demo database after filming.
- Do not attempt to delete protected completed cycles.
- Remove only disposable schedules or draft records created for the recording.
