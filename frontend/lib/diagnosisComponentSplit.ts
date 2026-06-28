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
    if (optionSet.has(part)) checks.push(part);
    else notes.push(part);
  }
  return { checks, notes: notes.join("; ") };
}
