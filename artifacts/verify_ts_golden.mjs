// Verifies the compiled TS engine against the frozen golden cases.
// Usage: npx tsc frontend/lib/nutritionCalculations.ts --outDir artifacts/tsbuild --target es2020 --module es2020
//        node artifacts/verify_ts_golden.mjs
import { readFileSync } from "node:fs";
import { autofillPrescription, ACTIVITY_FACTORS } from "./tsbuild/nutritionCalculations.js";

const spec = JSON.parse(readFileSync("docs/logic/prescription-targets.json", "utf-8"));
const patients = spec.golden_patients;
let pass = 0, fail = 0;
const failures = [];

for (const c of spec.golden_cases) {
  const p = patients[c.patient];
  const metrics = {
    weightKg: p.weightKg, heightCm: p.heightCm, ageYears: p.ageYears,
    sex: p.sex, isAdult: true, activityFactor: ACTIVITY_FACTORS[p.activity].factor,
  };
  const got = autofillPrescription(c.goal, c.stage, metrics);
  const keys = ["energy_kcal", "protein_g", "carbs_g", "fat_g", "fluid_ml"];
  const mismatch = keys.filter((k) => got[k] !== c.expected[k]);
  if (mismatch.length === 0) pass++;
  else { fail++; failures.push({ ...c, got: Object.fromEntries(keys.map(k => [k, got[k]])), mismatch }); }
}

console.log(`GOLDEN: ${pass} pass / ${fail} fail (of ${spec.golden_cases.length})`);
for (const f of failures.slice(0, 20)) {
  console.log(`  FAIL ${f.patient} ${f.goal}/${f.stage} [${f.mismatch}] exp`,
    JSON.stringify(f.expected), "got", JSON.stringify(f.got));
}
process.exit(fail === 0 ? 0 : 1);
