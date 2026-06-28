export type DiagnosisDomain = "NI" | "NC" | "NB";

export interface DiagnosisProblemBuilder {
  domain: DiagnosisDomain;
  niDirection: "Inadequate" | "Excessive";
  niNutrient: string;
  ncProblems: string[];
  nbProblems: string[];
  problemOverride?: string;
}

export function buildDiagnosisProblemText(builder: DiagnosisProblemBuilder): string {
  const override = builder.problemOverride?.trim();
  if (override) return override;

  if (builder.domain === "NI") {
    return `${builder.niDirection} ${builder.niNutrient || "[Nutrient]"} Intake`;
  }
  if (builder.domain === "NC") {
    return builder.ncProblems.length > 0 ? builder.ncProblems.join("; ") : "[Select problem]";
  }
  return builder.nbProblems.length > 0 ? builder.nbProblems.join("; ") : "[Select problem]";
}

