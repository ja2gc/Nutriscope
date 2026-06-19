// Local-only UX preferences (rnd.md §8) — persisted in localStorage, no backend.
// Applied as attributes/classes on <html> so they cascade app-wide:
//  - density: scales the root rem (Tailwind spacing/text are rem-based).
//  - reduce motion: disables animations/transitions globally.

export type Density = "comfortable" | "compact";

const DENSITY_KEY = "ns_density";
const MOTION_KEY = "ns_reduce_motion";

export function getDensity(): Density {
  if (typeof window === "undefined") return "comfortable";
  return localStorage.getItem(DENSITY_KEY) === "compact" ? "compact" : "comfortable";
}

export function getReduceMotion(): boolean {
  if (typeof window === "undefined") return false;
  return localStorage.getItem(MOTION_KEY) === "1";
}

export function applyPreferences(
  density: Density = getDensity(),
  reduceMotion: boolean = getReduceMotion(),
): void {
  if (typeof document === "undefined") return;
  const root = document.documentElement;
  root.dataset.density = density;
  root.classList.toggle("reduce-motion", reduceMotion);
}

export function setDensity(density: Density): void {
  localStorage.setItem(DENSITY_KEY, density);
  applyPreferences(density, getReduceMotion());
}

export function setReduceMotion(value: boolean): void {
  localStorage.setItem(MOTION_KEY, value ? "1" : "0");
  applyPreferences(getDensity(), value);
}
