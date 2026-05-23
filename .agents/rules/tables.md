---
trigger: model_decision
description: Use when defining or modifying DB tables, fields, relationships, or any data access logic.
---

Build migrations in this order. Never raw SQL — always Eloquent.
users                   id, name, email, password, role, timestamps
patients                id, name, dob, sex, religion, address, contact, physician, admission_date, medical_diagnosis, ward, status, timestamps
ncp_records             id, patient_id, rnd_user_id, type(new/followup/reassessment), status, ai_risk_score, timestamps
assessments             id, ncp_record_id, dietary_intake, appetite_changes, dietary_restrictions, supplements, knowledge_notes, weight, height, bmi, body_composition, medical_history, social_history, lifestyle, allergies(json), food_dislikes(json), medications(json), rnd_summary, timestamps
biochemical_data        id, assessment_id, albumin, hematocrit, bun, hemoglobin, calcium, ldl, cholesterol, phosphate, creatinine, potassium, glucose, sodium, hba1c, triglycerides, hdl, urr, bp, abg, others, timestamps
ocr_documents           id, user_id, assessment_id, file_path, extracted_text, status(pending/completed/failed), timestamps
diagnoses               id, ncp_record_id, domain(NI/NC/NB/NO), problem, etiology, signs_symptoms, pes_statement, extra_notes, ai_generated(bool), timestamps
interventions           id, ncp_record_id, goal_type, disease_stage, displayed_nutrients(json), energy_kcal, protein_g, carbs_g, fat_g, fluid_ml, micronutrient_limits(json), education_notes, counseling_goals, barriers, strategies, encounter_location, session_type, next_followup_date, timestamps
meal_plans              id, intervention_id, patient_id, week_start_date, generation_type(manual/auto), timestamps
meal_plan_days          id, meal_plan_id, day_of_week, meal_type(breakfast/am_snack/lunch/pm_snack/dinner)
meal_plan_items         id, meal_plan_day_id, food_item_id, recipe_id, quantity, unit, nutrient_snapshot(json)
monitorings             id, ncp_record_id, weight, bmi, lab_values(json), intake_notes, symptoms, goal_achievement(json), clinical_summary, ai_decision, next_monitoring_date, timestamps
recipes                 id, rnd_user_id, name, prep_notes, cost, total_calories, total_protein, total_carbs, total_fat, micronutrients(json), timestamps
recipe_ingredients      id, recipe_id, food_item_id, quantity, unit
food_items              id, name, category, usda_fdc_id, calories, protein, carbs, fat, micronutrients(json), allergens(json), unit_price, timestamps
clinical_rules          id, condition, stage, nutrient_or_food_tag, rule_type(avoid/limit/recommend), threshold, reason, timestamps
inventory               id, food_item_id, quantity_in_stock, unit, expiry_date, usage_rate, notes, timestamps
menu_cycles             id, rnd_user_id, week_start_date, status, activation_date, timestamps
menu_cycle_days         id, menu_cycle_id, day_of_week, meal_type, recipe_id, food_item_id, quantity
meal_prep_logs          id, fss_user_id, menu_cycle_day_id, prepared_quantity, status(done/pending), notes, timestamps
budgets                 id, rnd_user_id, planned_amount, actual_amount, period_start, period_end, cost_per_person, timestamps
budget_daily_logs       id, budget_id, date, planned, actual, variance, timestamps
procurements            id, rnd_user_id, status, timestamps
procurement_items       id, procurement_id, food_item_id, quantity_needed, quantity_purchased, supplier_notes, receipt_image, purchased(bool), timestamps
announcements           id, user_id, title, body, attachment, pinned(bool), visibility(FSS/Admin/All), timestamps
calendar_events         id, user_id, title, type(manual/system), source_module, source_id, event_date, status(pending/completed/overdue), deletable(bool), timestamps
notifications           id, user_id, title, message, type, source_module, source_id, read(bool), timestamps
audit_logs              id, user_id, action, model_type, model_id, old_values(json), new_values(json), timestamps
reports                 id, user_id, title, type(NCP/inventory/budget/procurement/menu), filters(json), file_path, status(draft/pending/completed), timestamps
ai_usage_logs           id, user_id, model, tokens_input, tokens_output, tokens_total, endpoint, timestamps