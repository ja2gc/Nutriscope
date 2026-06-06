import requests, sys

BASE = "http://localhost:5001"

tests = [
    ("1 checkmark prefix",
     "adult",
     "☑ Reduced dietary intake in the past week\n□ Others\n□ Admission to ICU"),
    ("2 trailing tick",
     "adult",
     "Reduced dietary intake in the past week ✓\n□ Others"),
    ("3 standalone tick + next line",
     "adult",
     "□ Coma\n✓\nReduced dietary intake in the past week\n□ Others"),
    ("4 v as OCR misread",
     "adult",
     "v Reduced dietary intake in the past week\n□ Others"),
    ("5 nothing checked (adult)",
     "adult",
     "□ Admission to ICU\n□ Coma\n□ Reduced dietary intake\n□ Others"),
    ("6 nothing checked (pediatric)",
     "pediatric",
     "□ Admission to ICU\n□ Coma\n□ Reduced dietary intake"),
    ("7 A1 adult - real form simulation",
     "adult",
     "□ Admission to ICU\n□ Anorexia Nervosa\n□ Cachexia\n□ Coma\n□ Diabetes Mellitus\n□ Gastrointestinal disease\n□ Liver disease\n□ Malabsorption\n□ Sepsis\n□ Unintentional weight loss in the past 3 months\n☑ Reduced dietary intake in the past week\n□ BMI below 18.5 and above 30"),
]

print("Live endpoint tests against http://localhost:5001/parse\n")
failed = 0
for name, form_type, text in tests:
    r = requests.post(f"{BASE}/parse", json={"text": text, "form_type": form_type}, timeout=5)
    d = r.json()
    cc = d.get("clinical_conditions", [])
    iw = d.get("intake_weight_history", [])
    print(f"  Test {name}")
    print(f"    conditions : {cc}")
    print(f"    intake     : {iw}")
    print()

# Also test /health and /omr endpoint presence
h = requests.get(f"{BASE}/health").json()
print(f"  /health -> {h}")
