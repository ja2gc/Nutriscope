"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import { ArrowLeft } from "lucide-react";
import { ALL_MICROS } from "@/lib/nutritionCalculations";
import { formatMealPlanAmount } from "@/lib/mealPlanNutrition";
import { fetchRecipeById, type Recipe } from "@/services/foodLibraryService";
import { Button } from "@/components/ui/Button";
import type { NutrientSnapshot } from '@/services/mealPlanService';

export function RecipeMenuItemProfile({ id, name, snapshot, onBack, backLabel = 'Back' }: { id: string; name: string; snapshot?: NutrientSnapshot | null; onBack: () => void; backLabel?: string }) {
  const [recipe, setRecipe] = useState<Recipe | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    if (snapshot) return;
    let cancelled = false;
    fetchRecipeById(id).then((result) => { if (!cancelled) setRecipe(result); }).catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load Menu Item.'); });
    return () => { cancelled = true; };
  }, [id, snapshot]);
  if (snapshot) {
    const yieldAmount = snapshot.recipe_yield ?? snapshot.serving_size;
    const factor = yieldAmount / (snapshot.serving_size || 1);
    return <MenuItemProfile name={snapshot.name || name} servings={snapshot.recipe_yield} prepNotes={snapshot.prep_notes}
      nutrients={{ calories: snapshot.calories * factor, protein: snapshot.protein * factor, carbs: snapshot.carbs * factor, fat: snapshot.fat * factor, water_g: snapshot.water_g == null ? null : snapshot.water_g * factor, micronutrients: Object.fromEntries(Object.entries(snapshot.micronutrients ?? {}).map(([key, value]) => [key, value * factor])) }}
      ingredients={(snapshot.recipe_ingredients ?? []).map((ingredient, index) => ({ ...ingredient, id: ingredient.id ?? index }))}
      onBack={onBack} backLabel={backLabel} />;
  }
  if (!recipe) return <div className="space-y-3"><p role={error ? 'alert' : 'status'} className="text-sm text-warm-600">{error ?? 'Loading Menu Item…'}</p><Button variant="secondary" onClick={onBack}>{backLabel}</Button></div>;
  return <MenuItemProfile name={recipe.name ?? name} servings={recipe.servings} prepNotes={recipe.prep_notes}
    nutrients={{ calories: recipe.total_calories, protein: recipe.total_protein, carbs: recipe.total_carbs, fat: recipe.total_fat, water_g: recipe.total_water_g, micronutrients: recipe.micronutrients }}
    ingredients={recipe.ingredients.map((ingredient) => ({ id: ingredient.id, name: ingredient.food_item?.name ?? 'Ingredient', quantity: ingredient.quantity, unit: ingredient.unit }))}
    onBack={onBack} backLabel={backLabel} />;
}

export type ProfileIngredient = { id: number; name: string; quantity: number | string; unit: string };
export type ProfileNutrients = { calories?: number | string | null; protein?: number | string | null; carbs?: number | string | null; fat?: number | string | null; water_g?: number | string | null; micronutrients?: Record<string, number> | null };
export const MENU_ITEM_NUTRIENT_UNITS: Record<string, string> = {
  ...Object.fromEntries(ALL_MICROS.map(({ key, unit }) => [key, unit])),
  copper: 'mg', manganese: 'mg', selenium: 'mcg', iodine: 'mcg',
  vitamin_e: 'mg', vitamin_k: 'mcg', vitamin_b1: 'mg', vitamin_b2: 'mg', vitamin_b3: 'mg', vitamin_b6: 'mg',
};

export function MenuItemProfile({ name, servings, prepNotes, nutrients, ingredients, onBack, backLabel = "Back to template", nutritionBasis = "Whole recipe", controls, actions, renderIngredientAmount, ingredientControls, profileType = 'Menu Item' }: {
  name: string;
  servings?: number | null;
  prepNotes?: string | null;
  nutrients?: ProfileNutrients;
  ingredients: ProfileIngredient[];
  onBack: () => void;
  backLabel?: string;
  nutritionBasis?: string;
  controls?: ReactNode;
  actions?: ReactNode;
  renderIngredientAmount?: (ingredient: ProfileIngredient) => ReactNode;
  ingredientControls?: ReactNode;
  profileType?: 'Menu Item' | 'Ingredient';
}) {
  const heading = useRef<HTMLHeadingElement>(null);
  useEffect(() => { heading.current?.focus(); heading.current?.scrollIntoView?.({ block: 'start' }); }, []);
  return <section aria-label="Menu Item profile" className="mx-auto max-w-3xl space-y-5">
    <div className="flex items-center gap-3 border-b border-warm-200 pb-4">
      <button type="button" aria-label={backLabel} title={backLabel} onClick={onBack} className="shrink-0 rounded-lg border border-warm-200 p-2 text-warm-500 hover:bg-warm-50 focus-visible:ring-2 focus-visible:ring-emerald-600"><ArrowLeft className="h-4 w-4" /></button>
      <div className="min-w-0"><h3 ref={heading} tabIndex={-1} className="break-words text-xl font-extrabold text-warm-900 outline-none">{name}</h3><p className="text-sm text-warm-500">{profileType} profile</p></div>
    </div>
    <div className="space-y-4 rounded-2xl border border-warm-200 bg-white p-4 sm:p-6">
      {servings != null && <p className="text-sm text-warm-700">Recipe makes: {formatMealPlanAmount(servings)} {servings === 1 ? 'serving' : 'servings'}</p>}
      {controls}
      {nutrients && <>
      <h4 className="text-sm font-bold text-warm-700">{nutritionBasis}</h4>
      <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">{[
        ["Energy", nutrients.calories, "kcal"], ["Protein", nutrients.protein, "g"], ["Carbohydrate", nutrients.carbs, "g"], ["Fat", nutrients.fat, "g"],
      ].map(([label, value, unit]) => <div key={String(label)} className="rounded-xl border border-warm-100 bg-warm-50 p-3"><dt className="text-xs font-semibold text-warm-500">{label}</dt><dd className="mt-1 font-bold text-warm-900">{formatMealPlanAmount(value)} <span className="text-xs font-normal">{unit}</span></dd></div>)}</dl>
      {nutrients.water_g != null && <p className="text-sm text-warm-600">Water: {formatMealPlanAmount(nutrients.water_g)} g</p>}
      </>}
    </div>
    {profileType === 'Menu Item' && <><div className="space-y-3 rounded-2xl border border-warm-200 bg-white p-4 sm:p-6">
      <h4 className="text-sm font-bold text-warm-700">Ingredients</h4>
      {ingredientControls}
      {ingredients.length === 0 && <p className="text-sm text-warm-500">No measured ingredient details recorded.</p>}
      <div className="space-y-2">{ingredients.map((ingredient) => <div key={ingredient.id} className="flex flex-wrap items-center justify-between gap-3 border-b border-warm-100 py-2 last:border-0">
        <span className="min-w-0 flex-1 break-words text-sm text-warm-800">{ingredient.name}</span>
        {renderIngredientAmount ? renderIngredientAmount(ingredient) : <span className="text-sm text-warm-600">{formatMealPlanAmount(ingredient.quantity)} {ingredient.unit}</span>}
      </div>)}</div>
    </div>
    <div className="space-y-3 rounded-2xl border border-warm-200 bg-white p-4 sm:p-6"><h4 className="text-sm font-bold text-warm-700">Preparation notes</h4><p className="whitespace-pre-wrap break-words text-sm text-warm-600">{prepNotes || "No preparation notes recorded."}</p></div></>}
    {nutrients && <div className="space-y-3 rounded-2xl border border-warm-200 bg-white p-4 sm:p-6"><h4 className="text-sm font-bold text-warm-700">Micronutrients · {nutritionBasis.toLowerCase()}</h4>
      {Object.keys(nutrients.micronutrients ?? {}).length === 0 ? <p className="text-sm text-warm-500">No micronutrient data recorded.</p> : <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">{Object.entries(nutrients.micronutrients ?? {}).map(([key, value]) => {
        const metadata = ALL_MICROS.find((entry) => entry.key === key);
        return <div key={key} className="flex justify-between gap-3 text-sm"><dt className="text-warm-600">{metadata?.label ?? key.replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase())}</dt><dd className="font-semibold text-warm-800">{formatMealPlanAmount(value)} {MENU_ITEM_NUTRIENT_UNITS[key]}</dd></div>;
      })}</dl>}
    </div>}
    {actions && <div className="flex flex-wrap justify-end gap-2">{actions}</div>}
  </section>;
}
