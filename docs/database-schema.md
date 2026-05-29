## Database Schema

Build migrations in this order. Never raw SQL — always Eloquent.
One migration per schema change; never manually edit DB structure.

---

### Core Tables

```
users                   id, name, email, password, role, timestamps
patients                id, name, dob, sex, religion, address, contact,
                        physician, admission_date, medical_diagnosis, ward,
                        status, screening_type(adult/pediatric/null),
                        hospital_number, age_group_category, timestamps
```

### NCP (Nutrition Care Process)

```
ncp_records             id, patient_id, rnd_user_id, type(new/followup/reassessment),
                        status, ai_risk_score, deterministic_risk_score, timestamps
assessments             id, ncp_record_id, dietary_intake, appetite_changes,
                        dietary_restrictions, supplements, knowledge_notes,
                        weight, height, bmi, body_composition,
                        medical_history, social_history, lifestyle,
                        allergies(json), food_dislikes(json), medications(json),
                        rnd_summary, timestamps
biochemical_data        id, assessment_id, albumin, hematocrit, bun, hemoglobin,
                        calcium, ldl, cholesterol, phosphate, creatinine, potassium,
                        glucose, sodium, hba1c, triglycerides, hdl, urr, bp, abg,
                        others, timestamps
diagnoses               id, ncp_record_id, domain(NI/NC/NB/NO), problem, etiology,
                        signs_symptoms, pes_statement, extra_notes, ai_generated(bool),
                        timestamps
interventions           id, ncp_record_id, goal_type, disease_stage,
                        displayed_nutrients(json), energy_kcal, protein_g, carbs_g,
                        fat_g, fluid_ml, micronutrient_limits(json), education_notes,
                        counseling_goals, barriers, strategies, encounter_location,
                        session_type, next_followup_date, timestamps
monitorings             id, ncp_record_id, weight, bmi, lab_values(json),
                        intake_notes, symptoms, goal_achievement(json),
                        clinical_summary, ai_decision, next_monitoring_date, timestamps
```

### Document Extraction Pipeline

```
ocr_documents           id, user_id, assessment_id, file_path, extracted_text,
                        document_type(screening/lab/procurement),
                        extraction_template_id(nullable FK), parsed_fields(json),
                        confidence_score(decimal), processing_time_ms,
                        status(pending/completed/failed), timestamps
screening_documents     id, patient_id, assessment_id, type(adult/pediatric),
                        file_path, extracted_data(json), mapped_fields(json),
                        status(pending/processing/completed/failed),
                        confidence_score(decimal), reviewed_by(user_id),
                        reviewed_at, timestamps
extraction_templates    id, document_type(screening_adult/screening_pediatric/
                        lab_result/inspection_report/marketing_statement),
                        field_mappings(json), validation_rules(json),
                        version, is_active(bool), timestamps
extraction_logs         id, screening_document_id, ocr_document_id,
                        source_type(screening/lab/procurement),
                        raw_text, parsed_fields(json), confidence_scores(json),
                        errors(json), processing_time_ms, timestamps
```

### Meal Planning

```
meal_plans              id, intervention_id, patient_id, week_start_date,
                        generation_type(manual/auto), status, timestamps
meal_plan_days          id, meal_plan_id, day_of_week,
                        meal_type(breakfast/am_snack/lunch/pm_snack/dinner)
meal_plan_items         id, meal_plan_day_id, food_item_id, recipe_id,
                        quantity, unit, nutrient_snapshot(json)
```

### Food & Recipes

```
food_items              id, name, category, usda_fdc_id, calories, protein,
                        carbs, fat, micronutrients(json), allergens(json),
                        unit_price, serving_unit, serving_size, timestamps
recipes                 id, rnd_user_id, name, category, prep_notes, cost,
                        total_calories, total_protein, total_carbs, total_fat,
                        micronutrients(json), servings, timestamps
recipe_ingredients      id, recipe_id, food_item_id, quantity, unit
clinical_rules          id, condition, stage, nutrient_or_food_tag,
                        rule_type(avoid/limit/recommend), threshold, unit,
                        reason, timestamps
```

### Food Service Operations

```
inventory               id, food_item_id, quantity_in_stock, unit, expiry_date,
                        usage_rate, notes, timestamps
menu_cycles             id, rnd_user_id, week_start_date, status,
                        activation_date, timestamps
menu_cycle_days         id, menu_cycle_id, day_of_week, meal_type,
                        recipe_id, food_item_id, quantity
meal_prep_logs          id, fss_user_id, menu_cycle_day_id, prepared_quantity,
                        status(done/pending), notes, timestamps
```

### Budget & Procurement

```
budgets                 id, rnd_user_id, planned_amount, actual_amount,
                        period_start, period_end, cost_per_person, timestamps
budget_daily_logs       id, budget_id, date, planned, actual, variance, timestamps
procurements            id, rnd_user_id, status, timestamps
procurement_items       id, procurement_id, food_item_id, quantity_needed,
                        quantity_purchased, supplier_notes, receipt_image,
                        purchased(bool), timestamps
inspection_reports      id, procurement_id, supplier_name, air_no, po_no,
                        invoice_date, requisition_office, date_received,
                        date_inspected, inspection_status(complete/partial),
                        inspected_by, inspected_by_title,
                        certified_by, certified_by_title,
                        verified_by, verified_by_title,
                        approved_by, approved_by_title,
                        file_path, extracted_data(json), timestamps
inspection_report_items id, inspection_report_id, item_no, unit, description,
                        quantity(decimal), food_item_id(nullable FK), timestamps
marketing_statements    id, procurement_id, period_start, period_end,
                        grand_total(decimal),
                        certified_by, certified_by_title,
                        examined_by, examined_by_title,
                        verified_by, verified_by_title,
                        file_path, extracted_data(json), timestamps
marketing_statement_items id, marketing_statement_id, item_description,
                        unit_price(decimal), quantity, total_value(decimal),
                        food_item_id(nullable FK), timestamps
marketing_summaries     id, marketing_statement_id, date_purchased,
                        inclusive_start, inclusive_end, total_amount(decimal),
                        certified_by, certified_by_title, timestamps
```

### Reports

```
reports                 id, user_id, title, type(enum: adime_individual/
                        adime_aggregate/ncp_census/inventory/budget/
                        procurement/menu_cycle/patient_menu_plan/
                        inspection_report/marketing_statement),
                        filters(json), parameters(json), file_path,
                        status(queued/generating/completed/failed),
                        generated_at, expires_at, timestamps
report_templates        id, type(matches report types above), name,
                        blade_view, default_filters(json),
                        available_filters(json), description,
                        is_active(bool), timestamps
```

### Communication & System

```
announcements           id, user_id, title, body, attachment, pinned(bool),
                        visibility(FSS/Admin/All), timestamps
calendar_events         id, user_id, title, type(manual/system), source_module,
                        source_id, event_date, status(pending/completed/overdue),
                        deletable(bool), timestamps
notifications           id, user_id, title, message, type, source_module,
                        source_id, read(bool), timestamps
audit_logs              id, user_id, action, model_type, model_id,
                        old_values(json), new_values(json), timestamps
ai_usage_logs           id, user_id, model, tokens_input, tokens_output,
                        tokens_total, endpoint, timestamps
```