"""Independent oracle: computes frozen golden prescription cases from prescription-targets.json.
Not reused by PHP/TS engines — serves as the cross-check. Run: python artifacts/oracle_golden.py
Writes golden_cases back into docs/logic/prescription-targets.json."""
import json, math, os

ROOT = r"C:\Users\HP\OneDrive\Documents\Nutriscope"
SPEC = os.path.join(ROOT, "docs", "logic", "prescription-targets.json")

def rnd(x):  # half-up to whole, matching JS Math.round (which is half-up for positives)
    return math.floor(x + 0.5)

def ibw_hamwi(cm, sex):
    inches_over = (cm / 2.54) - 60
    base = 48.0 if sex == "Male" else 45.5
    per = 2.7 if sex == "Male" else 2.2
    return max(base + per * inches_over, 30)

def mifflin(w, h, age, sex):
    b = 10*w + 6.25*h - 5*age
    return b + 5 if sex == "Male" else b - 161

def macros(energy, protein_g, fat_pct):
    fat_g = rnd(energy * fat_pct / 9)
    carbs_g = max(rnd((energy - protein_g*4 - fat_g*9)/4), 0)
    return carbs_g, fat_g

def compute(spec, goal, stage, p):
    g = spec["adult_goals"][goal]
    st = g.get("stages", {}).get(stage, {}) if stage else {}
    ibw = ibw_hamwi(p["heightCm"], p["sex"])
    pct = p["weightKg"] / ibw * 100
    ajbw = ibw + 0.25 * (p["weightKg"] - ibw)
    working = ajbw if pct > 120 else p["weightKg"]
    bmrwt = ajbw if pct > 120 else p["weightKg"]
    af = spec["activity_factors"][p["activity"]]
    tee = mifflin(bmrwt, p["heightCm"], p["ageYears"], p["sex"]) * af

    method = st.get("energy_method", g.get("energy_method"))
    floor = spec["baseline_pdri"]["caloric_floor"][p["sex"]]
    if method == "flat":
        kpk = st.get("energy_kcal_per_kg", g.get("energy_kcal_per_kg"))
        energy = rnd(working * kpk)
    elif method == "tee":
        energy = rnd(tee)
    elif method == "tee_deficit":
        energy = max(rnd(tee - st["energy_deficit"]), floor) if g.get("apply_floor") else rnd(tee - st["energy_deficit"])
    elif method == "tee_surplus":
        energy = rnd(tee + st["energy_surplus"])
    elif method == "refeeding":
        energy = rnd(working * st["refeeding_kcal_per_kg"])
    else:
        return None
    # diabetic stage_2 deficit + floor
    if goal == "diabetic_control" and st.get("energy_deficit"):
        energy = rnd(tee - st["energy_deficit"])
        if st.get("apply_floor"):
            energy = max(energy, floor)
    ppk = st.get("protein_g_per_kg_ibw", g.get("protein_g_per_kg_ibw"))
    protein_g = rnd(ibw * ppk)
    fat_pct = st.get("fat_pct", g.get("fat_pct"))
    carbs_g, fat_g = macros(energy, protein_g, fat_pct)
    fl = st.get("fluid", "std")
    fluid_ml = rnd(working * spec["weight_basis"]["fluid_std_factor_ml_per_kg"]) if fl == "std" else fl
    return {"energy_kcal": energy, "protein_g": protein_g, "carbs_g": carbs_g, "fat_g": fat_g, "fluid_ml": fluid_ml}

PATIENTS = {
    "A_m40_170_80": {"sex":"Male","ageYears":40,"heightCm":170,"weightKg":80,"activity":"sedentary"},
    "B_f55_155_70": {"sex":"Female","ageYears":55,"heightCm":155,"weightKg":70,"activity":"sedentary"},
    "C_m30_175_60": {"sex":"Male","ageYears":30,"heightCm":175,"weightKg":60,"activity":"light"},
}

def main():
    spec = json.load(open(SPEC, encoding="utf-8"))
    cases = []
    for pid, p in PATIENTS.items():
        for goal, g in spec["adult_goals"].items():
            if goal == "custom":
                continue
            stages = g.get("stages", {None: {}}) or {None: {}}
            for stage in (stages.keys() if g.get("stages") else [None]):
                out = compute(spec, goal, stage, p)
                if out:
                    cases.append({"patient": pid, "goal": goal, "stage": stage, "expected": out})
    spec["golden_cases"] = cases
    spec["golden_patients"] = PATIENTS
    json.dump(spec, open(SPEC, "w", encoding="utf-8"), indent=2, ensure_ascii=False)
    print(f"wrote {len(cases)} golden cases")
    # print a few for hand-verification
    for c in cases[:6]:
        print(c["patient"], c["goal"], c["stage"], c["expected"])

if __name__ == "__main__":
    main()
