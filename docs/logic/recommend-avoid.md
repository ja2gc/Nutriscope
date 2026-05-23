RECOMMEND/AVOID LOGIC
This is entirely algorithm-driven. 
Data sources pulled from:

assessments.allergies → hard filter, never include these foods ever
assessments.food_dislikes → soft filter, exclude from auto meal plan
patients.religion + assessments.lifestyle → religious/cultural hard filters (no pork for Muslim, etc.)
assessments.medications → food-drug interaction check against hardcoded interaction rules
diagnoses.domain + diagnoses.problem → identify conditions
interventions.goal_type + interventions.disease_stage → apply clinical rules from clinical_rules table
biochemical_data → lab value refinement (high potassium lab → stricter even without CKD, low albumin → prioritize protein, high glucose → stricter carbs)
food_items.allergens + food_items.micronutrients → match against all filters above

food_items.allergens tags (14 standard + Filipino context):
gluten, dairy, eggs, fish, shellfish, tree_nuts, peanuts, soy, sesame, seafood, chicken