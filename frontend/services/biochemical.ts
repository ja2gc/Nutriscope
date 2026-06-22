// String fields stored verbatim; everything else is a numeric lab value.
const STRING_BIOCHEMICAL_KEYS = new Set(["bp", "abg"]);

export function coerceBiochemicalValue(
  key: string,
  raw: string,
): number | string | null {
  if (raw === "") return null;
  if (STRING_BIOCHEMICAL_KEYS.has(key)) return raw;
  const n = Number(raw);
  return Number.isNaN(n) ? null : n;
}
