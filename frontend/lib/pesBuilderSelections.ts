const selections: Record<string, { etiology: string; sign: string }> = {
  unintended_weight_loss_v1: {
    etiology: "Poor appetite / anorexia",
    sign: "Significant unintended weight change",
  },
  overweight_obesity_v1: {
    etiology: "Energy intake exceeding needs",
    sign: "BMI ≥ 25 (overweight / obesity)",
  },
  swallowing_chewing_difficulty_v1: {
    etiology: "Mechanical eating difficulty",
    sign: "Swallowing / chewing difficulty documented",
  },
  altered_gi_function_v1: {
    etiology: "Altered GI function or motility",
    sign: "Altered bowel function (constipation / diarrhea)",
  },
  food_medication_interaction_v1: {
    etiology: "Medications affecting nutritional status",
    sign: "Food-medication interaction documented",
  },
  predicted_suboptimal_intake_v1: {
    etiology: "Poor appetite / anorexia",
    sign: "Suboptimal energy intake documented",
  },
};

export function candidateBuilderSelections(candidateId: string) {
  return selections[candidateId] ?? null;
}
