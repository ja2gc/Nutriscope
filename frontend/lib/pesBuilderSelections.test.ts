import { describe, expect, test } from "vitest";
import { candidateBuilderSelections } from "./pesBuilderSelections";

describe("candidateBuilderSelections", () => {
  test("maps every supported PES candidate to visible builder checkboxes", () => {
    for (const id of [
      "unintended_weight_loss_v1",
      "overweight_obesity_v1",
      "swallowing_chewing_difficulty_v1",
      "altered_gi_function_v1",
      "food_medication_interaction_v1",
      "predicted_suboptimal_intake_v1",
    ]) {
      const selection = candidateBuilderSelections(id);
      expect(selection?.etiology).toBeTruthy();
      expect(selection?.sign).toBeTruthy();
    }
  });
});
