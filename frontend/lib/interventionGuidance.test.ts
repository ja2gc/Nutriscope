import { expect, test } from "vitest";
import { interventionGuidanceCharacters, mergeGeneratedGuidance } from "./interventionGuidance";

test("counts Unicode characters consistently with the server", () => {
  expect(interventionGuidanceCharacters(["a😀", "b"])).toBe(3);
});

test("replaces unedited generated text and preserves manual edits", () => {
  const current = { education_notes: "Old education", counseling_goals: "My goal", strategies: "" };
  const prior = { education_notes: "Old education", counseling_goals: "Generated goal", strategies: "" };
  const next = { education_notes: "New education", counseling_goals: "New goal", strategies: "New strategy" };
  expect(mergeGeneratedGuidance(current, prior, next)).toEqual({
    values: { education_notes: "New education", counseling_goals: "My goal", strategies: "New strategy" },
    pending: { counseling_goals: "New goal" },
  });
});
