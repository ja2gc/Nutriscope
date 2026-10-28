export type DiagnosisDomain = "NI" | "NC" | "NB" | "NO";

export const NO_DIAGNOSIS = "No nutrition diagnosis at this time";
export type IntakePattern = "intake" | "predicted" | "needs" | "composition" | "other";

export interface DiagnosisProblemBuilder {
  domain: DiagnosisDomain;
  niDirection: "Inadequate" | "Excessive";
  niNutrient: string;
  ncProblems: string[];
  nbProblems: string[];
  problemOverride?: string;
  problemOther?: string;
  problemOtherChecked?: boolean;
  niPattern?: IntakePattern;
  niNutrientSpecify?: string;
  niDetail?: string;
}

export function buildDiagnosisProblemText(builder: DiagnosisProblemBuilder): string {
  if (builder.domain === "NO") return NO_DIAGNOSIS;
  const override = builder.problemOverride?.trim();
  if (override) return override;

  if (builder.domain === "NI") {
    const subject = builder.niNutrient.replace(/\s+Intake$/i, "").trim();
    const pattern = builder.niPattern ?? "intake";
    if (pattern === "other") return subject || "[Select problem]";
    if (pattern === "composition") return `Less than optimal ${subject || "[Select composition or modality]"}${subject.startsWith("intake of types") ? `: ${builder.niDetail || "[Specify type]"}` : ""}`;
    if (pattern === "needs") return `${builder.niDirection === "Inadequate" ? "Increased" : "Decreased"} nutrient needs: ${subject || "[Specify nutrient]"}`;
    if (pattern === "predicted") return `Predicted ${builder.niDirection === "Inadequate" ? "suboptimal" : "excessive"} ${subject.toLowerCase() === "energy" ? "energy intake" : `nutrient intake: ${subject || "[Specify nutrient]"}`}`;
    if (builder.niNutrientSpecify === "Vitamins") return `${builder.niDirection} vitamin intake: ${subject.replace(/^vitamin\s+/i, "") || "[Select vitamin]"}`;
    if (builder.niNutrientSpecify === "Minerals") return `${builder.niDirection} mineral intake: ${subject || "[Select mineral]"}`;
    const nutrient = subject.toLowerCase().replace(/^carbohydrates$/, "carbohydrate");
    if (nutrient === "bioactive substance") return `${builder.niDirection === "Inadequate" ? "Suboptimal" : "Excessive"} bioactive substance intake: ${builder.niDetail || "[Specify substance]"}`;
    if (nutrient.endsWith("infusion")) return `${builder.niDirection} ${nutrient}`;
    return `${builder.niDirection} ${nutrient || "[Nutrient]"} intake`;
  }
  if (builder.domain === "NC") {
    return [...builder.ncProblems.map(cleanProblemTerm), ...(builder.problemOtherChecked && builder.problemOther?.trim() ? [builder.problemOther.trim()] : [])].join("; ") || "[Select problem]";
  }
  return [...builder.nbProblems.map(cleanProblemTerm), ...(builder.problemOtherChecked && builder.problemOther?.trim() ? [builder.problemOther.trim()] : [])].join("; ") || "[Select problem]";
}

function cleanProblemTerm(term: string): string {
  return term.replace(/\s*\((?:specify|use with caution)\)/gi, "").trim();
}

