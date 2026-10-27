export type NutritionSupport = 'oral' | 'tube_feeding' | 'no_food_menu';
export const NUTRITION_SUPPORT_OPTIONS = [
  { value: 'oral', label: 'Oral / Per Orem' },
  { value: 'tube_feeding', label: 'Tube Feeding' },
  { value: 'no_food_menu', label: 'NPO / TPN — No food menu' },
] as const;
export function nutritionSupportLabel(value?: NutritionSupport | null) {
  return NUTRITION_SUPPORT_OPTIONS.find(option => option.value === value)?.label ?? 'Not selected';
}
