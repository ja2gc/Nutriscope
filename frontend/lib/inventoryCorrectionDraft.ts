import type { AffectedRecipe, CatalogItem } from "@/services/fsCatalogService";
import { compatibleRecipeUnits } from "@/lib/foodServiceUnits";

export interface InventoryCorrectionDraft {
  editing: CatalogItem;
  form: {
    name: string; category: string; default_supplier_id: string;
    base_unit: string; purchase_price: string; include_in_generated_lists: boolean;
  };
  affected: AffectedRecipe[];
  corrections: Record<string, { quantity: string; unit: string }>;
}

const prefix = "inventory-correction:";

export function correctionKey(recipe: AffectedRecipe): string {
  return `${recipe.slot_id ? `slot:${recipe.slot_id}` : recipe.recipe_id}:${recipe.line_index}`;
}

export function correctionHref(recipe: AffectedRecipe, token: string): string | null {
  const query = `?inventoryCorrection=${encodeURIComponent(token)}`;
  if (recipe.slot_id && recipe.cycle_id) {
    return `/food-service/menu-cycle/${encodeURIComponent(recipe.cycle_id)}/lines/${encodeURIComponent(recipe.slot_id)}${query}`;
  }
  if (recipe.recipe_id) return `/food-service/recipes/${encodeURIComponent(recipe.recipe_id)}${query}`;
  return null;
}

export function readCorrectionDraft(token: string): InventoryCorrectionDraft | null {
  if (!token || typeof window === "undefined") return null;
  try {
    const raw = sessionStorage.getItem(prefix + token);
    if (!raw) return null;
    const value = JSON.parse(raw) as InventoryCorrectionDraft;
    return value?.editing?.id && Array.isArray(value.affected) && value.corrections ? value : null;
  } catch { return null; }
}

export function writeCorrectionDraft(token: string, draft: InventoryCorrectionDraft): void {
  sessionStorage.setItem(prefix + token, JSON.stringify(draft));
}

export function clearCorrectionDraft(token: string): void {
  if (token) sessionStorage.removeItem(prefix + token);
}

export function correctionUnits(unit: string): string[] {
  return compatibleRecipeUnits(unit, true);
}
