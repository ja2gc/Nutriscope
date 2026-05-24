MEAL PLAN ALGORITHM 
1. Read from assessment: allergies(hard exclude), religious restrictions(hard exclude)
2. Read nutrition prescription from intervention
3. Query recipe library — filter out allergens/restricted ingredients, score by nutrient fit
4. Query food library for snacks — same filters
5. Build 7-day plan — assign best-fit recipes, adjust quantities mathematically, ensure variety
6. Validate each day — within 10% of targets = green, miss by >10% = flag for RND review
7. AI fallback (Sonnet) — ONLY if <5 recipes match. Label: "AI Suggested — Pending RND Review"
Patient food dislikes: NOT filtered. Displayed as warning note to RND only: "Patient dislikes: [list]"
Require min 15 recipes in library before auto-generation. Show prompt to RND if below threshold.