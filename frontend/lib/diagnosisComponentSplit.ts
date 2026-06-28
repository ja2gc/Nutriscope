/**
 * Split a stored "; "-joined G-NCP diagnosis component (etiology or signs) back
 * into the checkbox selections that match the domain's known options and the
 * free-text remainder.
 *
 * Diagnoses persist etiology/signs as a single joined string built from checked
 * options plus a notes field. When re-opening a diagnosis for editing, this
 * reverses that join so prior checkbox selections are shown ticked (and can be
 * un-ticked) instead of being dumped wholesale into the notes textarea.
 */
export function splitStoredComponent(
  stored: string,
  options: string[],
): { checks: string[]; notes: string } {
  const optionSet = new Set(options);
  const checks: string[] = [];
  const notes: string[] = [];
  for (const part of stored.split(";").map((s) => s.trim()).filter(Boolean)) {
    const exact = optionSet.has(part) ? part : null;
    const fuzzy = exact ?? findClosestOption(part, options);
    if (fuzzy) {
      if (!checks.includes(fuzzy)) checks.push(fuzzy);
      const remainder = removeMatchedMeaning(part, fuzzy);
      if (remainder) notes.push(remainder);
    } else {
      notes.push(part);
    }
  }
  return { checks, notes: notes.join("; ") };
}

export function matchStoredOption(stored: string, options: string[]): string | null {
  if (options.includes(stored)) return stored;
  return findClosestOption(stored, options);
}

function normalize(value: string): string {
  return value
    .toLowerCase()
    .replace(/kg\/m2/g, "bmi")
    .replace(/[^a-z0-9<>%]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

function findClosestOption(part: string, options: string[]): string | null {
  const text = normalize(part);

  for (const option of options) {
    if (text.includes(normalize(option))) return option;
  }

  const aliases: Array<[string, (text: string) => boolean]> = [
    ["Poor appetite / anorexia", (t) => /\b(poor|decreased|low|absent)\s+appetite\b|\banorexia\b/.test(t)],
    ["Nausea or vomiting", (t) => /\bnausea\b|\bvomiting\b|\bemesis\b/.test(t)],
    ["Unintended Weight Loss", (t) => /\b(unintentional|unintended)?\s*weight loss\b|\b\d+%\s+weight loss\b/.test(t)],
    ["Malnutrition (undernutrition)", (t) => /\bmalnutrition\b|\bundernutrition\b/.test(t)],
    ["Predicted Suboptimal Intake", (t) => /\bsub[\s-]?optimal intake\b|\bpredicted.*intake\b/.test(t)],
    ["Estimated intake < 75% of estimated needs", (t) => /\b(intake|energy)\b.*\b(less than|below|under|<)\b.*\b(75|50)\b/.test(t)],
    ["Unintentional weight loss > 5% in 3 months", (t) => /\b(unintentional|unintended)?\s*weight loss\b|\b\d+%\s+weight loss\b/.test(t)],
    ["Significant unintended weight change", (t) => /\b(unintentional|unintended|significant)?\s*weight (loss|change)\b/.test(t)],
    ["BMI < 18.5 (underweight)", (t) => /\bbmi\b.*\b(17|18|underweight)\b|\bunderweight\b/.test(t)],
    ["Fatigue / weakness on activity", (t) => /\bfatigue\b|\bweakness\b/.test(t)],
  ];

  for (const [option, matches] of aliases) {
    if (options.includes(option) && matches(text)) return option;
  }

  return null;
}

function removeMatchedMeaning(part: string, option: string): string {
  const cleaned = part
    .replace(new RegExp(escapeRegExp(option), "i"), "")
    .replace(/\bpoor appetite\b/gi, "")
    .replace(/\banorexia\b/gi, "")
    .replace(/\bnausea\b|\bvomiting\b|\bemesis\b/gi, "")
    .replace(/\benergy intake less than 50% of estimated needs for 7 days\b/gi, "")
    .replace(/\bunintentional weight loss of 8% in 1 month\b/gi, "")
    .replace(/\b8% weight loss in 1 month\b/gi, "")
    .replace(/^\s*and\s+/i, "")
    .replace(/\s+and\s*$/i, "")
    .replace(/\s{2,}/g, " ")
    .replace(/^[,;:\s-]+|[,;:\s-]+$/g, "")
    .trim();

  return cleaned === part ? "" : cleaned;
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}
