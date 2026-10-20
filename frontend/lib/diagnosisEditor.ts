export function composeDiagnosisComponent(checks: string[], other: string, enabled: boolean): string {
  return [...checks, ...(enabled && other.trim() ? [other.trim()] : [])].join("; ");
}

export function splitDiagnosisComponent(stored: string, options: string[]): { checks: string[]; other: string } {
  const parts = stored.split(";").map(part => part.trim()).filter(Boolean);
  return { checks: parts.filter(part => options.includes(part)), other: parts.filter(part => !options.includes(part)).join("; ") };
}
