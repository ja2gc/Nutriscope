export const MENU_ITEM_CATEGORIES = [
  { value: "meal", label: "Meal" },
  { value: "carb", label: "Carb" },
  { value: "snack", label: "Snack" },
  { value: "tube_feeding", label: "Tube Feeding" },
] as const;

export function menuItemCategoryLabel(category: string | null | undefined): string {
  return MENU_ITEM_CATEGORIES.find((option) => option.value === category)?.label ?? category ?? "";
}
