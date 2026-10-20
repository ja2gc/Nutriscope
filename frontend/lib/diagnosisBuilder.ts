export type DiagnosisDomain = "NI" | "NC" | "NB";

export interface DiagnosisProblemBuilder {
  domain: DiagnosisDomain;
  niDirection: "Inadequate" | "Excessive";
  niNutrient: string;
  ncProblems: string[];
  nbProblems: string[];
  problemOverride?: string;
  problemOther?: string;
  problemOtherChecked?: boolean;
}

export function buildDiagnosisProblemText(builder: DiagnosisProblemBuilder): string {
  const override = builder.problemOverride?.trim();
  if (override) return override;

  if (builder.domain === "NI") {
    return `${builder.niDirection} ${builder.niNutrient.replace(/\s+Intake$/i, "") || "[Nutrient]"} Intake`;
  }
  if (builder.domain === "NC") {
    return [...builder.ncProblems, ...(builder.problemOtherChecked && builder.problemOther?.trim() ? [builder.problemOther.trim()] : [])].join("; ") || "[Select problem]";
  }
  return [...builder.nbProblems, ...(builder.problemOtherChecked && builder.problemOther?.trim() ? [builder.problemOther.trim()] : [])].join("; ") || "[Select problem]";
}

