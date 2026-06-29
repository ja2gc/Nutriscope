/**
 * Catalog unit options for the food-service reference catalog and recipes.
 *
 * Convertible units (mass/volume) let recipes use a different unit than the
 * inventory item — the backend UnitConverter (and its client mirror) handle the
 * rate. Count/outlier units (pc, bundle, pack, tray, sack, unit) are not convertible: a recipe must use
 * the same unit, otherwise the cost uses the entered unit as-is (warned in UI).
 */
export const CATALOG_UNIT_OPTIONS = ["g", "kg", "ml", "mL", "L", "pc", "bundle", "pack", "tray", "sack", "unit"] as const;

export type CatalogUnit = (typeof CATALOG_UNIT_OPTIONS)[number];
