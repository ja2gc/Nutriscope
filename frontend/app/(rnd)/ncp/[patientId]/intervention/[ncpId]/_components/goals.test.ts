import { describe, expect, test } from "vitest";
import { GOALS, goalsForPatient, goalLabelFor, visibleStagesForGoal } from "./goals";

describe("intervention goal catalog", () => {
  test("offers only the seven approved common goals", () => {
    expect(GOALS.map(goal => goal.value)).toEqual(["general_diet", "diabetic_control", "renal_diet", "cardiac_diet", "high_protein", "liver_disease", "custom"]);
    expect(GOALS.flatMap(goal => goal.stages ?? [])).toHaveLength(12);
  });
  test("diabetes types are independent of body weight", () => {
    expect(visibleStagesForGoal("diabetic_control")?.map(type => type.value)).toEqual(["type_1", "type_2"]);
    expect(visibleStagesForGoal("general_diet")).toBeNull();
    expect(visibleStagesForGoal("custom")).toBeNull();
  });
  test("pediatric options are age appropriate without altering legacy labels", () => {
    expect(goalsForPatient(true).map(goal => goal.value)).toEqual(["general_diet", "diabetic_control", "custom"]);
    expect(goalsForPatient(false)).toEqual(GOALS);
    expect(goalLabelFor("malnutrition")).toBe("Malnutrition");
  });
});
