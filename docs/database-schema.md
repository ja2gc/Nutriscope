## Database Schema

Build migrations in this order. Never raw SQL — always Eloquent.
One migration per schema change; never manually edit DB structure.

---

### Core Tables

```
users                   id, name, email, password, role, is_active, deleted_at, timestamps
patients                id, name, dob, sex, religion, address, contact,
                        physician, admission_date, medical_diagnosis, ward,
                        status, screening_type(adult/pediatric/null),
                        hospital_number, age_group_category, timestamps
sessions                id, user_id, ip_address, user_agent, payload, last_activity
password_reset_tokens   email, token, created_at
personal_access_tokens  id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, timestamps
```

### NCP (Nutrition Care Process)

```
ncp_records             id, patient_id, rnd_user_id, type(new/followup/reassessment),
                        status, risk_score(nullable deterministic score from
                        screening checklist; system-calculated, not AI-generated),
                        timestamps
assessments             id, ncp_record_id, dietary_intake, appetite_changes,
                        dietary_restrictions, supplements, knowledge_notes,
                        weight, height, bmi, body_composition,
                        medical_history, social_history, lifestyle,
                        allergies(json), food_dislikes(json), medications(json),
                        religion, clinical_fields(json), activity_measurements(json),
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
                        clinical_summary, ai_decision, ai_review(json), next_monitoring_date, timestamps
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
meal_plan_days          id, meal_plan_id, day_of_week, variance(json),
                        meal_type(breakfast/am_snack/lunch/pm_snack/dinner)
meal_plan_items         id, meal_plan_day_id, food_item_id, recipe_id, fdc_id,
                        quantity, unit, nutrient_snapshot(json)
meal_plan_templates     id, name, description, rnd_user_id, timestamps
meal_plan_template_days id, meal_plan_template_id, day_of_week, meal_type, recipe_id, food_item_id, quantity
```

### Food & Recipes (Clinical)

```
food_items              id, name, category, usda_fdc_id, calories, protein,
                        carbs, fat, water_g, micronutrients(json), allergens(json),
                        serving_unit, serving_size, ready_to_eat(bool), timestamps
recipes                 id, rnd_user_id, name, category, prep_notes,
                        total_calories, total_protein, total_carbs, total_fat, total_water_g,
                        micronutrients(json), servings, meal_types(json), timestamps
recipe_ingredients      id, recipe_id, food_item_id, quantity, unit
clinical_rules          id, condition, stage, nutrient_or_food_tag,
                        rule_type(avoid/limit/recommend), threshold, unit,
                        reason, timestamps
```

### Food Service Catalog

```
fs_items                id, name, category, unit_price, purchase_unit, inventory_unit, conversion_factor, timestamps
food_service_recipes    id, rnd_user_id, name, category, prep_notes, cost, servings, timestamps
food_service_recipe_ingredients id, food_service_recipe_id, fs_item_id, quantity, unit
```

### Food Service Operations

```
inventory               id, fs_item_id, quantity_in_stock, unit, received_date,
                        usage_rate, notes, unit_price(decimal), timestamps
menu_cycles             id, rnd_user_id, week_start_date, status,
                        activation_date, cost_snapshot(json), timestamps
menu_cycle_days         id, menu_cycle_id, day_of_week, meal_type,
                        food_service_recipe_id, fs_item_id, quantity
menu_cycle_templates    id, name, description, timestamps
menu_cycle_template_days id, menu_cycle_template_id, day_of_week, meal_type, food_service_recipe_id, fs_item_id, quantity
meal_prep_logs          id, fss_user_id, menu_cycle_id, service_date, target_population, status(done/pending), notes, timestamps
meal_prep_log_lines     id, meal_prep_log_id, menu_cycle_day_id, prepared_quantity, shortfall_qty, timestamps
cleaning_logs           id, fss_user_id, item_name, category, status, notes, cleaned_at, timestamps
```

### Budget & Procurement

```
budgets                 id, rnd_user_id, scope(monthly/quarterly/yearly/custom), name,
                        allocated_amount, actual_amount, period_start, period_end,
                        cost_per_person, population, budget_per_head_day,
                        budget_per_head_month, budget_per_head_year, timestamps
budget_daily_logs       id, budget_id, log_date, spent, notes, timestamps
suppliers               id, name, contact_name, email, phone, address, timestamps
shopping_lists          id, rnd_user_id, menu_cycle_id, status, generated_at, timestamps
shopping_list_items     id, shopping_list_id, fs_item_id, quantity_needed, unit, timestamps
purchase_orders         id, fss_user_id, supplier_id, status(draft/ordered/received/cancelled), timestamps
purchase_order_items    id, purchase_order_id, fs_item_id, quantity_needed, purchase_unit,
                        quantity_purchased, supplier_notes, purchased(bool), timestamps
purchase_order_attachments id, purchase_order_id, file_path, type(receipt/proof), caption, timestamps
inspection_reports      id, purchase_order_id, supplier_name, air_no, po_no,
                        invoice_date, requisition_office, date_received,
                        date_inspected, inspection_status(complete/partial),
                        inspected_by, inspected_by_title,
                        certified_by, certified_by_title,
                        verified_by, verified_by_title,
                        approved_by, approved_by_title,
                        file_path, extracted_data(json), timestamps
inspection_report_items id, inspection_report_id, item_no, unit, description,
                        quantity(decimal), fs_item_id(nullable FK), timestamps
marketing_statements    id, purchase_order_id, period_start, period_end,
                        grand_total(decimal),
                        certified_by, certified_by_title,
                        examined_by, examined_by_title,
                        verified_by, verified_by_title,
                        file_path, extracted_data(json), timestamps
marketing_statement_items id, marketing_statement_id, item_description,
                        unit_price(decimal), quantity, total_value(decimal),
                        fs_item_id(nullable FK), timestamps
marketing_summaries     id, marketing_statement_id, date_purchased,
                        inclusive_start, inclusive_end, total_amount(decimal),
                        certified_by, certified_by_title, timestamps
```

### Reports

```
reports                 id, user_id, title, type(enum), archive_mode(bool),
                        filters(json), parameters(json), file_path,
                        status(queued/generating/completed/failed),
                        generated_at, expires_at, timestamps
report_templates        id, type, name, blade_view, default_filters(json),
                        available_filters(json), description,
                        is_active(bool), timestamps
report_branding         id, hospital_name, logo_path, address, header_text, is_active, timestamps
```

### Communication & System

```
announcements           id, user_id, title, body,
                        category(General/Event/Operational/Urgent),
                        attachment(nullable), pinned(bool),
                        visibility(FSS/Admin/All), timestamps
calendar_events         id, user_id, title, type(manual/system), source_module,
                        source_id, event_date, status(pending/completed/overdue),
                        deletable(bool), timestamps
notifications           id, user_id, title, message, type, source_module,
                        source_id, read(bool), timestamps
activity_log            id, log_name, description, subject_type, event, subject_id, causer_type, causer_id, properties(json), batch_uuid, timestamps
ai_usage_logs           id, user_id, model, tokens_input, tokens_output,
                        tokens_total, endpoint, timestamps
```

### Confirmed Implemented Tables

- Hand-verified against `database/migrations` on 2026-06-15 (the earlier "auto-verified" claim was not accurate — `budgets` and `food_service_recipes` owner columns and `budgets`/`budget_daily_logs` columns were corrected after checking the migrations).
- `budgets.rnd_user_id`: budgets are RND-owned planning artifacts (migration `2026_06_15_020000` renamed `fss_user_id` → `rnd_user_id`); FSS has read-only access via `/api/fss/budgets`.
- `ncp_records.risk_score` is the canonical system-calculated risk score column.
- Inventory/Recipes decoupled into clinical `food_items` / `recipes` and operational `fs_items` / `food_service_recipes`.
