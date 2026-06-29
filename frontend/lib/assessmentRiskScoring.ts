export interface AssessmentBiochemicalData {
  albumin?: number | string | null;
  hematocrit?: number | string | null;
  bun?: number | string | null;
  hemoglobin?: number | string | null;
  calcium?: number | string | null;
  ldl?: number | string | null;
  cholesterol?: number | string | null;
  phosphate?: number | string | null;
  creatinine?: number | string | null;
  potassium?: number | string | null;
  glucose?: number | string | null;
  sodium?: number | string | null;
  hba1c?: number | string | null;
  triglycerides?: number | string | null;
  hdl?: number | string | null;
  urr?: number | string | null;
  bp?: string | null;
  abg?: string | null;
  others?: unknown[] | Record<string, unknown> | null;
}

export interface AssessmentRiskInputs {
  screeningType?: string | null;
  ibwPercentage?: number | string | null;
  weightLossPercentage?: number | string | null;
  chewingSwallowingDifficulties?: string | null;
  constipation?: string | null;
  diarrheaNotes?: string | null;
  foodIntolerance?: string | null;
  nutrientDrugInteraction?: string | null;
  lifestyle?: string | null;
  biochemicalData?: AssessmentBiochemicalData | null;
  sex?: "Male" | "Female";
}

export interface RiskFactor {
  key: string;
  label: string;
  points: number;
}

export const RISK_FACTORS: RiskFactor[] = [
  { key: "screening_criteria", label: "Screening criteria for potential nutritional risk", points: 1 },
  { key: "ibw_limit", label: "Less than 85% or greater than 130% ideal body weight", points: 1 },
  { key: "unintentional_weight_loss", label: "Unintentional weight loss over weeks/months", points: 2 },
  { key: "mechanical_digestive_problem", label: "Mechanical / digestive problem", points: 1 },
  { key: "low_albumin", label: "Low albumin", points: 1 },
  { key: "significant_lab_result", label: "Significant lab result", points: 1 },
  { key: "others", label: "Other/s", points: 1 },
];

export interface RiskScoreResult {
  checkedFactors: string[];
  score: number;
}

export function scoreRiskFactors(factors: string[]): number {
  const uniqueFactors = Array.from(new Set(factors));
  return uniqueFactors.reduce((score, factor) => {
    const match = RISK_FACTORS.find((item) => item.key === factor);
    return score + (match?.points ?? 0);
  }, 0);
}

function toNumber(value: number | string | null | undefined): number | null {
  if (value === null || value === undefined || value === "") return null;
  const next = typeof value === "number" ? value : Number(value);
  return Number.isNaN(next) ? null : next;
}

function hasText(value: string | null | undefined): boolean {
  return !!value && value.trim().length > 0;
}

export function deriveRiskScore(inputs: AssessmentRiskInputs): RiskScoreResult {
  const checkedFactors: string[] = [];
  let score = 0;

  if (inputs.screeningType) {
    score += 1;
    checkedFactors.push("screening_criteria");
  }

  const ibw = toNumber(inputs.ibwPercentage);
  if (ibw !== null && (ibw < 85 || ibw > 130)) {
    score += 1;
    checkedFactors.push("ibw_limit");
  }

  const weightLoss = toNumber(inputs.weightLossPercentage);
  if (weightLoss !== null && weightLoss > 0) {
    score += 2;
    checkedFactors.push("unintentional_weight_loss");
  }

  if (
    hasText(inputs.chewingSwallowingDifficulties) ||
    hasText(inputs.constipation) ||
    hasText(inputs.diarrheaNotes) ||
    hasText(inputs.foodIntolerance)
  ) {
    score += 1;
    checkedFactors.push("mechanical_digestive_problem");
  }

  const albumin = toNumber(inputs.biochemicalData?.albumin);
  if (albumin !== null && albumin < 3.5) {
    score += 1;
    checkedFactors.push("low_albumin");
  }

  const glucose = toNumber(inputs.biochemicalData?.glucose);
  const potassium = toNumber(inputs.biochemicalData?.potassium);
  const creatinine = toNumber(inputs.biochemicalData?.creatinine);
  const bun = toNumber(inputs.biochemicalData?.bun);
  const creatinineHigh = inputs.sex === "Female" ? 0.9 : 1.2;
  const hasSignificantLab =
    (glucose !== null && (glucose > 125 || glucose < 70)) ||
    (potassium !== null && (potassium > 5.0 || potassium < 3.5)) ||
    (creatinine !== null && creatinine > creatinineHigh) ||
    (bun !== null && bun > 20);

  if (hasSignificantLab) {
    score += 1;
    checkedFactors.push("significant_lab_result");
  }

  if (hasText(inputs.nutrientDrugInteraction) || hasText(inputs.lifestyle)) {
    score += 1;
    checkedFactors.push("others");
  }

  return { checkedFactors, score };
}
