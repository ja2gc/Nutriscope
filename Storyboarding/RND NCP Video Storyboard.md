# RND Nutrition Care Process Video Storyboard

Full workflow and rendered-report verification: **2026-09-28**. Assessment and Monitoring validation, text counters, dashboard card navigation, and PO/Budget Activity removal checked against the deployed UI on **2026-10-17**. Meal-plan wording checked against code on **2026-10-13**; not live re-tested in this pass.

Use this as the recording checklist and narration script for a future **Help → Guides** video. Record in a disposable demo database. Do not show real patient information.

Nutrition support workflow checked against `7c6a1623` plus this working tree on **2026-10-27** (local QA; no deployed acceptance claim). Record one required Assessment choice: **Oral / Per Orem**, **Tube Feeding**, or **NPO / TPN — No food menu**. Existing missing selections migrate to Oral. Saved Intervention Plans retain their mode.

Add a Tube Feeding demonstration alongside the Oral scenes: open Food / Nutrient Delivery, **Select formula** / **Create formula** in the next empty column, plus **Load Template** outside the columns using the shared Menu Item form with Tube Feeding selected. Show **Use in this plan**, **Save to Food Library and use**, and Cancel's existing unsaved-change popup. Open a formula profile; edit an ingredient at 2x, then change to 3x and confirm its current quantity multiplies by 1.5. **Reset recipe** restores the captured recipe. **Save as template** captures the prepared collection; library template profiles allow selection/removal without editing portions or ingredients. Tube formulas have no weekdays, daily totals, or automatic prescription scaling and stay out of Oral searches. Show Formula 1/2 selectors changing the top macros and selected prescription micros without summing formulas. Confirm formula and every Food Recall card show names only, use the whole-card action and top-right X, and stay white until hovered. Show fluid in the same summary card as the macros for Oral and Tube Feeding; profiles and PDFs retain planned ingredient measurements. Show the full-tab Food Calculator in Food Library.

Report variation: the Tube Feeding Nutrition Intervention Plan prints each formula’s exact planned portion details, followed by macros and all selected micronutrients; NCP Summary names the support type and formulas. NPO / TPN prints prescription and guidance without requiring a food menu. Empty Oral/Tube plans remain unavailable; NPO / TPN needs no menu. Older archived PDFs remain unchanged. In Census, also show **Per Orem**, **Tube Feeding**, **NPO / TPN**, and any unrecorded historical counts, using qualifying ADIME cycles rather than unique-patient totals.

## How to Use This Script

1. Complete **Recording Setup** before filming.
2. Record scenes in order and confirm each **Expected result**.
3. Follow **On-screen actions** while recording and use **Narration** as the spoken explanation.
4. Keep patient identifiers, contact details, clinical notes, and uploaded files fictional.

**Overall sequence:** Open patient → schedule or start visit → continue ADIME work → finish visit → manage attendance → protect or discontinue cycle → review past records and reports.

## Compact System Demo — Revised October 19, 2026

This insert supersedes the longer guide sequence for the combined system demo only. Checked against local source through `a520269e` and the October 17–18 NCP changes. This is a source review, not a new live acceptance run. Keep the existing 30.6-second Admin cut; the full proposed sequence totals **3:19.6**, including a **2:49 RND segment**. The narration introduces the purpose of each workflow for viewers without a nutrition background. RND means the registered nutritionist-dietitian; say “nutritionist” in narration where clearer.

Use an existing fictional patient with Assessment, Diagnosis, multiple dated Intervention Plans, saved menus, and several Monitoring entries. Do not start with a new patient whose empty history cannot demonstrate progress. Prefill recording forms off camera and show deliberate actions, results, and smooth scrolling. Do not rush through every field as if this were a training tutorial.

| System time | Scene and sequence | Narration |
|---|---|---|
| 00:00–00:04 | RND dashboard; show the system name and overview cards. | NutriScope connects patient nutrition care, food-service planning, and administrative oversight. |
| 00:04–00:13 | Open the prepared fictional patient. Overview, ADIME Records, then Appointments; show available appointment actions without starting a visit. End on the Assessment entry point. | Nutrition care assesses needs, identifies nutrition problems, plans treatment, and monitors progress. Patient profiles connect this care with appointments and previous records. |
| 00:13–00:25 | Assessment: show prefilled anthropometric, clinical, diet and functional sections in one controlled scroll. Include nutrition care category and maternal status. Select Generate Summary, show the editable result, then save only the prepared demo update. | Assessment brings body measurements, clinical findings, and eating habits together. Generate Summary turns the recorded findings into an editable overview, reducing repeated documentation. |
| 00:25–00:36 | Diagnosis: AI Review, Generate AI Suggestions; cut the processing wait. Show a PES draft and Evidence used, then its normal edit/save flow. Keep manual entry visible briefly. | Diagnosis connects a nutrition problem with its cause and supporting evidence. Suggested drafts show the assessment findings used, while the nutritionist reviews the final diagnosis. |
| 00:36–00:46 | Food Library: open Import from USDA with a prepared search; select an appropriate result and import. Open the ingredient profile and show micronutrients. Briefly show Menu Items and the Nutrition facts label option without creating a second full example. | The Food Library imports USDA nutrient data, including vitamins and minerals. Reusable menu items support recipes and packaged-food labels for more detailed meal planning. |
| 00:46–01:05 | Intervention opens on Plans. Open a prepared new dated plan: goal, calculation panel, prescription, weight target, nutrient target/maximum/minimum controls; show Education/Counseling briefly. Save the plan. Record Auto-Generate explicitly: choose a prepared Carb Menu Item, show the snack options, select Generate week, then show the populated week and nutrient totals. Briefly open an item profile. Keep template loading, Clear menu, and edit/delete controls peripheral; no separate feature tour. Use clean cuts between prepared stages. | Intervention sets nutrient targets, guidance, and a weekly menu. Auto-Generate builds meal choices around the prescription, with a selected carbohydrate side and optional snacks. The nutritionist reviews portions and nutrient totals, then adjusts the plan as needed. |
| 01:05–01:21 | Monitoring: show existing Visit Log and Progress Trends first. Open a prefilled monitoring entry; measurements, labs and intake, then Generate AI Summary. Cut processing time; show result, Use this summary, required Care Decision, then Save Visit and updated history. Do not show the removed standalone AI-review card. | Monitoring compares follow-up measurements and intake with the care plan. An AI draft helps summarize the entered progress; the nutritionist reviews it, chooses a care decision, and saves the visit. |
| 01:21–01:33 | Clinical outputs immediately after NCP: Reports, Patients NCP, select the same patient, choose one ADIME cycle, then preview one available Nutrition Intervention Plan with a saved menu. Show its menu and guidance; close. Briefly show NCP Summary in that cycle's list, then Demographic Census tab with populated aggregates. No Census download. Allow roughly three seconds for selection, four for plan preview, one for the summary listing and four for Census. | Patient-linked plans preserve dated menus and guidance. NCP Summary consolidates the care record, while Demographic Census summarizes patient groups and nutrition needs. |

Continue immediately with the compact Food Service sequence in `RND Food Service Operations Video Storyboard.md` at **01:33**. There is no standalone reports montage.

**Recording preparation and accuracy**

- Patient candidate: the existing fictional Maria Santos workflow described in the longer guide; verify its current owner, dated plans, saved menu, and nonempty trends before capture. No new live patient inspection or clinical change was performed for this script review.
- Assessment Generate Summary is an editable assessment summary, not a generative-AI diagnosis. Diagnosis currently displays **Evidence used**, not a visible source bibliography. Do not narrate source citations as an on-screen feature.
- Monitoring now drafts **Generate AI Summary** from unsaved entry values, offers **Use this summary**, and requires **Care Decision** before saving. Use the appropriate Continue/Modify/Discontinue choice for the prepared scenario. Monitoring does not itself create a revised Intervention Plan. The three-generation allowance and changed-input warning can remain visible without separate demonstrations.
- Prepare a clinically coherent goal, prescription, any nutrient bounds, weight target, menu, and monitoring values before recording. Do not invent changes solely to create motion. Existing dated plans and progress provide continuity; the prepared draft demonstrates entry without repetitive typing.
- Preserve actual loading results. Trim loading time with straight cuts; never substitute an older AI result as if it were a new response. Record enough spare frames around every click. Keep all example values fictional.
- Clinical report path verified in source: **Reports → Patients NCP → patient → ADIME cycle → dated Nutrition Intervention Plan**. A plan without a saved menu is unavailable; select one with a menu. **NCP Summary** uses the newest saved plan, whereas each intervention report belongs to its dated plan.
- Use one USDA result with micronutrients and a unit basis appropriate for the recipe. Live import availability and the exact search result must be checked during recording preparation. Do not present the clinical Food Library as the food-service purchase catalog.
- The revised rows are timing budgets, not claims of measured speech duration. Record narration, then align cuts to phrase endings with no long silent gaps. Shorten secondary field views before rushing the explanation. Target **3:10–3:19.6** after real narration fitting, and never exceed **3:20** for the combined system demo.

## Full Guide Recording Setup

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

For the full guide, open **Overview → Contact details → Edit contact details**. Keep the existing contact number, add a fictional Email or Messenger contact, check **Preferred** beside that contact, save and reopen to verify its badge. Up to five additional contacts are supported; **Other** permits a custom method label. X / Discard / Save protects unsaved contact edits when leaving.

**Narration**

> The patient profile keeps clinical cycles, visits, and supporting files together. Technical cycle identifiers and patient audit panels are not part of the clinical working view. The help icon explains when a patient or cycle can be deleted and when a completed record becomes protected.

**Expected result:** The patient profile shows the four working tabs and the protection guidance.

## Scene 2 — Schedule a Visit

**Purpose:** Create an appointment with a useful written purpose.

**On-screen actions**

1. Open **Appointments** and select **Schedule or Walk-in**.
2. Choose **Scheduled**, enter a future date/time, and write a purpose that may cover multiple steps, such as **Complete assessment and review diagnosis**.
3. Save the schedule.
4. Return to the RND dashboard and show the appointment queue. Patients in Care and Scheduled Visits both open the Patients page; Pending POs opens Procurement. Each entire card is clickable.
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
4. Show that the Nutrition Care step links still open the selected patient and cycle. Select **Resume**. Use **Unselect Patient** in the shared patient header when finished with this patient.

**Narration**

> Starting is an explicit action. While the visit is active, NutriScope remembers the patient and NCP cycle on the server. Leaving the page or reopening the site does not silently finish the visit. The Resume banner returns the RND to the active patient. The step links retain the selected cycle across page changes until the RND unselects the patient; unselecting does not finish the visit.

**Expected result:** The visit is In progress and remains resumable after navigation.

## Scene 4 — Work Across ADIME Steps

**Purpose:** Show that visit state is universal rather than tied to Intervention.

**Classification check:** In Anthropometrics, open the small BMI info button without changing card alignment: WHO sex/month bands cover 61–228 months and Asia-Pacific applies afterward. Month 60 has BMI without a class; ages 0–4 retain measurements without BMI. Pediatric adult-panel estimates are blank. Show BMI class separately from screening risk; %IBW cannot establish malnutrition. AI receives relevant calculated anthropometrics without the screening score/legacy status; height stays local and raw weights appear only when relevant. Monitoring retains measurements without adult pediatric targets. The NCP Summary separates classification from screening risk. Saved diagnoses remain unchanged.

**On-screen actions**

1. In Assessment, enter one weight-duration quantity plus weeks/months, select the required Nutrition care category, show conditional **Specify category** for Other, and choose a confirmed Pregnancy/Lactation status. Show Functional Assessment choices **Independent**, **Needs assistance**, and **Bedridden**, plus Yes/No chewing/swallowing and bowel fields with visible **Details (optional)** labels. Show compact character counters, the 100-character Food Intolerance field, up to five Food Dislikes of 50 characters each, and all Medications in one 100-character field. In Anthropometrics, show WHR changing when hip circumference changes; a 1 cm circumference shows a validation error with no risk category. WHR uses waist divided by hip, with adult risk classified before display rounding; waist, hip, and WHR do not feed nutrient prescriptions. Generate and review the editable RND Summary with its 3,500-character limit. Save/reload; show PAL in the NCP Summary and no Stress Factor control or fixed three-month copy.
2. Continue to Diagnosis, open **AI Review**, and choose **Generate AI Suggestions**. Show zero to three prioritized evidence-supported nutrition diagnoses from one review of eligible candidates. Review Evidence used and concise P/E/S notes, then accept, edit or dismiss. Completed findings reopen for relevant evidence changes or related draft/saved-diagnosis deletion; deletion does not generate automatically. Rejected or partially rejected output offers **Retry AI Review** without an automatic refill. With exhausted evidence, click **Generate AI Suggestions** to show **No supported evidence left.** without token use. Show the separate diagnosis allowance of ten requests per RND per ten minutes and its quota explanation. In **Edit**, matching choices are checked; an **Other** checkbox reveals a 75-character field for unmatched Clinical/Behavioral problems and each domain's etiology/signs. Notes stay outside PES. Save through the normal PES flow and show the manual builder as fallback. Relevant referral/medication context and selective clinical guidance assist interpretation; patient names are removed and source provenance stays server-side.
3. Continue to Intervention, which opens on **Plans**. Create a new plan and choose a goal and applicable **Type**. General Diet has no type; diabetes offers Type 1 Diabetes and Type 2 Diabetes at any body weight. Select **Add Surplus** or **Add Deficit**, enter a fixed kcal/day amount and select **Calculate targets**. Expand **Show calculations** and show **Measurements** and **Final target**, including the fixed adjustment once and any applicable maternal addition. Edit the existing Fluid amount and select ≤ for an actual prescribed maximum; show that it remains a maximum after an energy/goal change. No separate fluid-mode or coefficient controls are shown. Open a pediatric example: only General Diet, diabetes and Custom Plan appear; the tooltip explains age at measurement and unchanged saved plans. Show automatic general energy/growth estimates and Custom Plan for other assessed needs. Open Education and Counseling and use Show suggestion below the relevant white text field. Show Copy and selectable patient-friendly text, matching the Shopping List summary pattern. Suggestions never populate or overwrite notes; paste only the advice wanted. Expanded suggestions have a neutral border, header and Copy button; they are read-only text with no inclusion checkboxes. Only text pasted into the editable fields becomes part of the saved plan. Repeated copying adds no duplicates. Character counters and combined save validation still apply. Refeeding and gradual changes never reduce the final endpoint. Show editable adult Target weight defaulting to IBW; pediatric target weight requires individual growth assessment; switch between one value and a range with one decimal place. Uncheck a generated micronutrient in Display Micros, then edit another nutrient's amount and blank/≤/≥ selector. Energy offers the same selector as the other macros; macro units appear outside the fields. Explain target, maximum and minimum through the aligned tooltip. Save; show the same bounds and weight target in the plan and newly generated reports. Target weight does not automatically change the calorie formula.
4. Show **Food Library → Ingredients / Menu Items / Templates**. Keep Ingredient food-group filters separate from Meal, Carb, and Snack Menu Item categories. Open a one-Ingredient Carb and a multi-Ingredient Snack; show compatible units. In **Create Menu Item**, choose **Nutrition facts label** to record a branded product's measured serving basis, nutrients and allergens. Templates have one Edit opening action, no Qty/Unit inputs, and centered clickable item cards with a compact top-right X. Open a card's complete read-only profile and return without losing the draft. Toggle **Show snack slots** in both templates and planning calendars; hiding rows preserves items. Load a seeded goal template, review compatibility, and choose **Scale to prescription**. Loading and subsequent menu actions persist directly; there is no Save week/Discard draft stage. Preserve maternal milk/fruit main-meal placements in no-snack templates. Fluid remains separate from food scaling.
5. Save the first complete dated Intervention Plan, then create its sole menu. For Auto-Generate, choose one Carb Menu Item and confirm it accompanies the Meal in all 21 main slots, even for a dish containing carbohydrates. Demonstrate **Exclude carbs** and **Exclude snacks**, including snack exclusion for liver disease. Included Snacks appear only in AM/PM slots; manual Snack placement in a main meal remains possible. Show an incompatible-allergy Carb warning and recovery. Compare whole-batch and patient portions: 500 g in a five-serving batch gives 120 g at 1.2 servings. Confirm saved snapshots retain their values after library changes. Save a reusable template and open its calendar/profile controls from Food Library. Each saved intervention still owns at most one menu.
6. In Monitoring, confirm the patient code and cycle start and complete the follow-up calculation inputs. Show patient-specific lab and nutrient inputs shared with the graphs. Allergies and Food Dislikes stay in Assessment and are not reentered in Monitoring. Dietary Restrictions allow 100 characters, and intake/symptom notes allow 400 characters. Progress Assessment is required with a 700-character counter. Use **Generate AI Summary** on unsaved values with selected goal/clinical context. Identical inputs reuse the result; **Regenerate AI Summary** requests fresh wording. Show monitoring's separate ten-requests-per-RND-per-ten-minutes allowance; another draft does not reset it. Edit the result and change an input to demonstrate the review warning. Existing text is replaced only with **Use this summary**. Save the fictional visit and inspect its saved assessment. Switch between Visit Log and Progress Trends; the old evaluation/AI card is absent. Filter trends by All, Anthropometric, Clinical Labs, or Intake, including selected micronutrients. Show neutral measurement lines and existing targets/reference ranges. Keep treatment changes in Intervention; Monitoring records follow-up evidence without creating or revising an Intervention Plan.
7. In **Visit History**, show five visits per page under one Date, Visit type, Decision, and Actions header; measurements appear inside the visit. Select the eye action or a row to replace history with the same Monitoring entry form populated with that visit's recorded values and a **View only** label. The latest saved Monitoring entry in an active cycle exposes edit and delete actions, enforced by the API; older entries remain View only. Edit and save the latest entry, then demonstrate deletion unlocking the preceding saved entry. The back arrow returns to the same history page. **Log New Visit** also replaces history with the editable form; the back arrow preserves the existing unsaved-change confirmation. Then open Intervention **Plans**, view the earlier plan read-only, create a new dated plan prefilled from the newest plan, keep the copied prescription values while reviewing the latest Monitoring context, edit one prefilled menu portion, then save the new intervention and its independent menu. Reopen the latest plan for editing; older plans display **View only**. Plans exposes existing-style edit/view and delete actions. Verify the previous saved menu remains unchanged.
8. Point out the same visit bar on every step.
9. Show that Monitoring requires Care Decision before Save Visit. In Progress Trends, identify Starting as the saved Assessment measurement, distinguish laboratory reference ranges from prescription targets/limits, and show the latest saved Intervention weight target. Missing starting intake remains blank. Check the new reports retain blank-target/≤/≥ meanings.

**Narration**

> A visit may include one step or several. Assessment keeps weight duration, Nutrition care category, and maternal status explicit. PES assistance only words deterministic source-backed candidates; matching choices reappear as selections when edited, and manual entry remains available. Intervention shows complete dated plans and the final prescription after any maternal modifier, while fluid stays guidance outside meal scaling. Monitoring captures complete follow-up calculation inputs but does not revise treatment. When treatment changes, the RND creates a new dated plan in Intervention; it prefills the previous prescription and notes plus the latest saved menu as an editable draft. Saving the new intervention stores its independent menu; the latest plan remains editable after reopening. Older plans remain view-only and keep their own menus. Auto-Generate is always available on the editable plan and replaces its current menu; Clear menu removes its items. Ingredient edits are local to the planned item and recalculate nutrients without altering Food Library. Each clinical save records which sections were worked on and which became complete during this visit. Visit completion and NCP-cycle completion remain separate decisions.

**Expected result:** The visit shows the sections worked on without forcing the RND to predict one next step.

**Food Recall demonstration:** At the bottom of Assessment's Dietary tab, expand the calculator and show its empty seven-column area. Add Weekday and Add Weekend in alternating order; weekdays group first and each type has its own numbering. Add beyond seven days and use the side arrows. Add Ingredients and Menu Items without meal slots, open a saved profile, and change a compatible unit while keeping its quantity to show nutrient scaling. Review daily totals, Display Micros, and the full-plan daily average. Save Plan accepts one calculation into the form and reveals Recall Coverage; Save Assessment persists it. Demonstrate cancel/discard restoring the previous calculation. In Monitoring, show the same calculator outside the entry card on the right, keep Intake Notes separate, and persist through Save Visit. Open an older saved log to view its coverage, frozen profiles, and totals. The latest saved entry permits recall edits while preserving saved nutrient baselines. Recall Coverage stays hidden when no calculation exists.

For Food Recall inputs, show Energy alone initially, then use Display Macros and Display Micros to add nutrient fields in the neutral responsive grid. Assessment follows the Dietary tab's existing field styling; Monitoring retains its intake grouping and target hints. Save/reopen the nutrient inputs with the whole entry. Intake Notes remains separate, and AI diagnosis rules are unchanged.

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
2. Show that **Complete cycle** requires clinically complete Assessment, Diagnosis, and Intervention.
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
5. Show the long-bond PDF preview: identity/prescription and **Weekly Meal Plan** first, concise education/counseling/barriers/strategies immediately after the menu, then four compact recipe cards per row. Each recipe lists ingredients once with **Recipe makes: X servings** and a **Your servings** reference guide. Explain the printed ingredient-scaling formula and one-decimal amounts. Direct foods show recorded quantities without a recipe-batch column. Confirm empty snack rows disappear and repeated identical portions reuse their menu reference. Show the view/download controls used by Reports.
6. Open **Reports → Browse → Clinical → Patients NCP**, choose the same patient, select one ADIME cycle, and show only that cycle's Nutrition Intervention Plan and NCP Summary. In the NCP Summary, show recorded Present Diet, Energy / Nutrient Intake, PAL, Functional Assessment, and assessment symptom statuses/details. Monitoring shows Date, Weight, BMI, numeric Intake and the saved Evaluation / Summary; intake notes and symptoms feed the AI summary. Show whole-number macros and micronutrients together on a wrapping line in generated reports.
7. Return to **Patients**, search the same person using the `NS-XXXX-XXXX` patient ID, and show that the ID appears only beneath the name in the patient profile header.
8. Open **Reports → Browse → Clinical → Demographic Census**. Select the earliest year, a completed month, and the live current month. Show that a new ADIME cycle counts in its start month only after a saved Assessment and a finished visit that included Assessment work; assessed legacy cycles without visit records remain included. Year totals sum the months. Show the white age/sex table beneath its shaded header, then the evenly spaced nutrition support, risk and nutrition care sections. BMI classification has separate ordered **Pediatric** and **Adult** groups from each cycle's initial Assessment. Every classification and nutrition care category includes zero totals. Older calculation-basis periods recalculate for display without overwriting their stored snapshots. Use **Download PDF** to show the selected Census on one page. Use the arrow back controls in report detail/history views. No Census item appears in Archived Reports.
9. Return to the patient record and confirm that merely viewing or downloading the report did not change **Last clinical action by**; when no qualifying save exists it reads **No action recorded**.

**Narration**

> Past Records preserves finished and discontinued ADIME cycles separately from the current cycle. A past appointment's linked ADIME cycle is history text, not a clickable shortcut. Saved Intervention Plans appear newest first by date, and each plan with a menu opens its own report preview from Patients NCP; an empty Oral or Tube plan remains listed but unavailable; NPO / TPN needs no menu. Oral Nutrition Intervention Plan prints its saved menu before concise guidance and compact precise portions without preparation instructions; Tube Feeding prints formula recipes and nutrient profiles. Repeated foods share one detail card while menu references preserve different saved amounts. NCP Summary uses the newest saved plan only. Patients NCP keeps current and completed cycles separate. The same patient search accepts name, physician, hospital number, or the random patient ID shown beneath the profile name. Demographic Census counts a new cycle in its start month after its first Assessment is saved and an Assessment-working visit is finished. Assessed legacy cycles without visit records remain included. Completed months freeze, the current month stays live, and year totals sum the months. Its selected-view PDF does not create a report identity. Other report PDFs use 8.5 × 13-inch long bond paper with the report's portrait or landscape orientation. Clinical reports remain linked to their dated plan or cycle. Passive page views and downloads do not change clinical attribution.

**Expected result:** Past records remain visible even when empty, pagination is present, patient-ID search finds the correct profile, each dated saved plan opens its own Nutrition Intervention Plan when its Oral menu or Tube Feeding formulas exist; NPO / TPN needs no menu, NCP Summary uses the newest plan only, archived bytes remain frozen, Patients NCP keeps report cycles separate, and Demographic Census counts qualifying cycles from the earliest cycle month.

## Closing Shot

**Narration**

> NutriScope separates appointments, visit activity, ADIME work, and cycle closure. That keeps attendance history clear while protecting completed clinical records.

## Recording Cleanup

- Reset the disposable demo database after filming.
- Do not attempt to delete protected completed cycles.
- Remove only disposable schedules or draft records created for the recording.
