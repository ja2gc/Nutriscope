"use client";

import React, { useEffect, useState, useCallback } from "react";
import {
  Plus, X, Search, Loader2, Database, Leaf, Trash2, BookmarkPlus,
  Salad, Wand2, AlertTriangle, LayoutTemplate, Edit2, FlaskConical,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  fetchMealPlans, createMealPlan, fetchAllMealPlanItems, addMealPlanItem,
  removeMealPlanItem, updateMealPlanItem, deleteMealPlan, generateMealPlan,
  fetchMealPlanTemplates, fetchMealPlanTemplate, deleteMealPlanTemplate,
  saveMealPlanAsTemplate, createPlanFromTemplate,
  MealPlan, MealPlanItem, MealPlanTemplate, MealPlanTemplateDetail, NutrientSnapshot,
} from "@/services/mealPlanService";
import {
  fetchFoodItems, fetchRecipes, searchUsda, importUsdaFood, fetchRecipeById,
  FoodItem, Recipe, UsdaSearchResult, RecipeIngredient,
} from "@/services/foodLibraryService";
import MacroTrackerBar from "./MacroTrackerBar";
import { ALL_MICROS } from "@/lib/nutritionCalculations";

const DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as const;
const MEAL_TYPES = ['breakfast','am_snack','lunch','pm_snack','dinner'] as const;
const MEAL_LABELS: Record<string, string> = {
  breakfast: 'Breakfast', am_snack: 'AM Snack', lunch: 'Lunch', pm_snack: 'PM Snack', dinner: 'Dinner',
};

interface Props {
  ncpId: string;
  prescriptionTargets: { energy: number; protein: number; carbs: number; fat: number };
  foodDislikes?: string[];
  allergens?: string[];
  displayedMicros?: string[];
  micronutrientLimits?: Record<string, { max?: number; min?: number; unit: string }>;
}

interface EditTarget {
  item: MealPlanItem;
  dayId: number;
  slotKey: string;
}

export default function MealPlanSection({
  ncpId, prescriptionTargets, foodDislikes = [], allergens = [],
  displayedMicros = [], micronutrientLimits = {},
}: Props) {
  const [plans, setPlans]               = useState<MealPlan[]>([]);
  const [activePlan, setActivePlan]     = useState<MealPlan | null>(null);
  const [selectedDay, setSelectedDay]   = useState<string>(DAYS[0]);
  const [itemsByKey, setItemsByKey]     = useState<Record<string, MealPlanItem[]>>({});
  const [loadingPlans, setLoadingPlans] = useState(false);
  const [creatingPlan, setCreatingPlan] = useState(false);

  // Delete plan
  const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting]               = useState(false);
  const [deleteError, setDeleteError]         = useState<string | null>(null);

  // Auto-generate
  const [generating, setGenerating]       = useState(false);
  const [generateError, setGenerateError] = useState<string | null>(null);

  // Micro display
  const [showMicros, setShowMicros] = useState(false);

  // Templates
  const [templates, setTemplates]                               = useState<MealPlanTemplate[]>([]);
  const [saveTemplateOpen, setSaveTemplateOpen]                 = useState(false);
  const [templateName, setTemplateName]                         = useState('');
  const [savingTemplate, setSavingTemplate]                     = useState(false);
  const [fromTemplateOpen, setFromTemplateOpen]                 = useState(false);
  const [viewingTemplate, setViewingTemplate]                   = useState<MealPlanTemplateDetail | null>(null);
  const [loadingTemplate, setLoadingTemplate]                   = useState<number | null>(null);
  const [confirmDeleteTemplateId, setConfirmDeleteTemplateId]   = useState<number | null>(null);
  const [deletingTemplate, setDeletingTemplate]                 = useState(false);

  // Edit meal item
  const [editTarget, setEditTarget]           = useState<EditTarget | null>(null);
  const [editServings, setEditServings]       = useState<string>('1');
  const [editRecipe, setEditRecipe]           = useState<Recipe | null>(null);
  const [editIngredients, setEditIngredients] = useState<{ id: number; qty: string; ing: RecipeIngredient }[]>([]);
  const [editLoadingRecipe, setEditLoadingRecipe] = useState(false);
  const [editSaving, setEditSaving]           = useState(false);
  const [editTab, setEditTab]                 = useState<'scale' | 'ingredients'>('scale');

  // Food picker
  const [pickerOpen, setPickerOpen]           = useState(false);
  const [pickerTarget, setPickerTarget]       = useState<{ dayId: number; mealType: string } | null>(null);
  const [pickerTab, setPickerTab]             = useState<'library'|'recipes'|'usda'>('library');
  const [libraryQuery, setLibraryQuery]       = useState('');
  const [libraryResults, setLibraryResults]   = useState<FoodItem[]>([]);
  const [recipeQuery, setRecipeQuery]         = useState('');
  const [recipeResults, setRecipeResults]     = useState<Recipe[]>([]);
  const [usdaQuery, setUsdaQuery]             = useState('');
  const [usdaResults, setUsdaResults]         = useState<UsdaSearchResult[]>([]);
  const [pickerLoading, setPickerLoading]     = useState(false);
  const [adding, setAdding]                   = useState<number | string | null>(null);
  const [pickerError, setPickerError]         = useState<string | null>(null);
  const [savingToLibrary, setSavingToLibrary] = useState<string | null>(null);

  const slotKey = (day: string, mt: string) => `${day}-${mt}`;

  const loadPlans = useCallback(async () => {
    setLoadingPlans(true);
    try {
      const data = await fetchMealPlans(ncpId);
      setPlans(data);
      if (data.length > 0) setActivePlan(data[0]);
    } finally { setLoadingPlans(false); }
  }, [ncpId]);

  useEffect(() => {
    loadPlans();
    fetchMealPlanTemplates().then(setTemplates);
  }, [loadPlans]);

  const loadItems = useCallback(async (plan: MealPlan) => {
    try {
      const allItems = await fetchAllMealPlanItems(ncpId, plan.id);
      const dayLookup = new Map((plan.days ?? []).map(d => [d.id, d]));
      const map: Record<string, MealPlanItem[]> = {};
      for (const item of allItems) {
        const day = dayLookup.get(item.meal_plan_day_id);
        if (!day) continue;
        const key = slotKey(day.day_of_week, day.meal_type);
        if (!map[key]) map[key] = [];
        map[key].push(item);
      }
      setItemsByKey(map);
    } catch (err) {
      console.error('loadItems failed:', err);
      setGenerateError(err instanceof Error ? err.message : 'Failed to load meal items. Please refresh.');
    }
  }, [ncpId]);

  useEffect(() => { if (activePlan) loadItems(activePlan); }, [activePlan, loadItems]);

  const handleCreatePlan = async () => {
    setCreatingPlan(true);
    try {
      const d = new Date(); const day = d.getDay();
      d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
      const plan = await createMealPlan(ncpId, { week_start_date: d.toISOString().split('T')[0], generation_type: 'manual' });
      setPlans((p) => [...p, plan]);
      setActivePlan(plan);
    } finally { setCreatingPlan(false); }
  };

  const handleGenerate = async () => {
    setGenerating(true);
    setGenerateError(null);
    try {
      const d = new Date(); const day = d.getDay();
      d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
      const result = await generateMealPlan(ncpId, {
        week_start_date: d.toISOString().split('T')[0],
        allergens: allergens.length > 0 ? allergens : undefined,
      });
      if ('insufficient_recipes' in result) {
        setGenerateError(
          `Only ${result.count} recipe${result.count !== 1 ? 's' : ''} match this patient's restrictions. ` +
          `Add more goal-appropriate recipes to the food library, or build the meal plan manually.`
        );
        return;
      }
      setPlans((prev) => [...prev, result]);
      setActivePlan(result);
    } finally { setGenerating(false); }
  };

  const handleSaveTemplate = async () => {
    if (!activePlan || !templateName.trim()) return;
    setSavingTemplate(true);
    try {
      const saved = await saveMealPlanAsTemplate(ncpId, activePlan.id, { name: templateName.trim() });
      setTemplates((prev) => [saved, ...prev]);
      setSaveTemplateOpen(false); setTemplateName('');
    } finally { setSavingTemplate(false); }
  };

  const handleFromTemplate = async (templateId: number) => {
    setFromTemplateOpen(false); setViewingTemplate(null); setCreatingPlan(true);
    try {
      const d = new Date(); d.setDate(d.getDate() + (d.getDay() === 0 ? -6 : 1 - d.getDay()));
      const plan = await createPlanFromTemplate(ncpId, { template_id: templateId, week_start_date: d.toISOString().split('T')[0] });
      setPlans((p) => [...p, plan]); setActivePlan(plan);
    } finally { setCreatingPlan(false); }
  };

  const handleViewTemplate = async (templateId: number) => {
    setLoadingTemplate(templateId);
    try { setViewingTemplate(await fetchMealPlanTemplate(templateId)); }
    finally { setLoadingTemplate(null); }
  };

  const handleDeleteTemplate = async () => {
    if (!confirmDeleteTemplateId) return;
    setDeletingTemplate(true);
    try {
      await deleteMealPlanTemplate(confirmDeleteTemplateId);
      setTemplates((prev) => prev.filter((t) => t.id !== confirmDeleteTemplateId));
      if (viewingTemplate?.id === confirmDeleteTemplateId) setViewingTemplate(null);
    } finally { setDeletingTemplate(false); setConfirmDeleteTemplateId(null); }
  };

  const handleDeletePlan = async () => {
    if (!confirmDeleteId) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await deleteMealPlan(ncpId, confirmDeleteId);
      const remaining = plans.filter((p) => p.id !== confirmDeleteId);
      setPlans(remaining); setActivePlan(remaining.length > 0 ? remaining[0] : null); setItemsByKey({});
      setConfirmDeleteId(null);
    } catch (err) {
      setDeleteError(err instanceof Error ? err.message : 'Failed to delete plan. Please try again.');
    } finally { setDeleting(false); }
  };

  // ── Edit modal ─────────────────────────────────────────────────────────────

  const openEdit = async (item: MealPlanItem, dayId: number, key: string) => {
    setEditTarget({ item, dayId, slotKey: key });
    setEditServings(item.quantity ?? '1');
    setEditTab('scale');
    setEditRecipe(null);
    setEditIngredients([]);
    if (item.recipe_id) {
      setEditLoadingRecipe(true);
      try {
        const recipe = await fetchRecipeById(item.recipe_id);
        setEditRecipe(recipe);
        setEditIngredients(recipe.ingredients.map((ing) => ({ id: ing.id, qty: String(ing.quantity), ing })));
      } finally { setEditLoadingRecipe(false); }
    }
  };

  const recalcFromIngredients = (): Partial<NutrientSnapshot> => {
    if (!editRecipe) return {};
    const servings = editRecipe.servings ?? 1;
    let cal=0, prot=0, carb=0, fat=0;
    const micros: Record<string, number> = {};
    for (const { qty, ing } of editIngredients) {
      const food = ing.food_item; if (!food) continue;
      const factor = parseFloat(qty || '0') / (parseFloat(food.serving_size ?? '100') || 100);
      cal  += parseFloat(food.calories || '0') * factor;
      prot += parseFloat(food.protein  ?? '0') * factor;
      carb += parseFloat(food.carbs    ?? '0') * factor;
      fat  += parseFloat(food.fat      ?? '0') * factor;
      for (const [k, v] of Object.entries(food.micronutrients ?? {})) {
        micros[k] = (micros[k] ?? 0) + v * factor;
      }
    }
    return {
      name:           editTarget?.item.nutrient_snapshot?.name ?? editRecipe.name,
      calories:       Math.round((cal  / servings) * 10) / 10,
      protein:        Math.round((prot / servings) * 10) / 10,
      carbs:          Math.round((carb / servings) * 10) / 10,
      fat:            Math.round((fat  / servings) * 10) / 10,
      serving_size:   1,
      serving_unit:   'serving',
      micronutrients: Object.fromEntries(Object.entries(micros).map(([k, v]) => [k, Math.round(v / servings * 10) / 10])),
    };
  };

  const handleSaveEdit = async () => {
    if (!editTarget || !activePlan) return;
    setEditSaving(true);
    try {
      const payload: { quantity?: number; nutrient_snapshot?: NutrientSnapshot } = {};
      if (editTab === 'scale') {
        payload.quantity = parseFloat(editServings) || 1;
      } else {
        const newSnap = recalcFromIngredients();
        payload.nutrient_snapshot = { ...editTarget.item.nutrient_snapshot, ...newSnap } as NutrientSnapshot;
        payload.quantity = 1;
      }
      const updated = await updateMealPlanItem(ncpId, activePlan.id, editTarget.dayId, editTarget.item.id, payload);
      setItemsByKey((prev) => ({
        ...prev,
        [editTarget.slotKey]: (prev[editTarget.slotKey] ?? []).map((i) => i.id === updated.id ? updated : i),
      }));
      setEditTarget(null);
    } finally { setEditSaving(false); }
  };

  // ── Picker helpers ──────────────────────────────────────────────────────────

  const openPicker = (dayId: number, mealType: string) => {
    setPickerTarget({ dayId, mealType }); setPickerOpen(true); setPickerTab('library');
    setLibraryQuery(''); setLibraryResults([]); setRecipeQuery(''); setRecipeResults([]);
    setUsdaQuery(''); setUsdaResults([]); setPickerError(null);
  };

  const appendItem = (item: MealPlanItem, plan: MealPlan, dayId: number) => {
    const day = plan.days.find((d) => d.id === dayId); if (!day) return;
    const key = slotKey(day.day_of_week, day.meal_type);
    setItemsByKey((prev) => ({ ...prev, [key]: [...(prev[key] ?? []), item] }));
  };

  const addFromLibrary = async (food: FoodItem) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(food.id); setPickerError(null);
    try {
      appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { food_item_id: food.id, quantity: 1, unit: 'serving' }), activePlan, pickerTarget.dayId);
    } catch (err) {
      setPickerError(err instanceof Error ? err.message : 'Failed to add food. Please try again.');
    } finally { setAdding(null); }
  };
  const addFromRecipe = async (recipe: Recipe) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(`recipe-${recipe.id}`); setPickerError(null);
    try {
      appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { recipe_id: recipe.id, quantity: 1, unit: 'serving' }), activePlan, pickerTarget.dayId);
    } catch (err) {
      setPickerError(err instanceof Error ? err.message : 'Failed to add recipe. Please try again.');
    } finally { setAdding(null); }
  };
  const addFromUsda = async (food: UsdaSearchResult) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(food.fdc_id); setPickerError(null);
    try {
      appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { fdc_id: String(food.fdc_id), quantity: 100, unit: 'g' }), activePlan, pickerTarget.dayId);
    } catch (err) {
      setPickerError(err instanceof Error ? err.message : 'Failed to add USDA food. Please try again.');
    } finally { setAdding(null); }
  };
  const removeItem = async (key: string, dayId: number, itemId: number) => {
    if (!activePlan) return;
    await removeMealPlanItem(ncpId, activePlan.id, dayId, itemId);
    setItemsByKey((prev) => ({ ...prev, [key]: (prev[key] ?? []).filter((i) => i.id !== itemId) }));
  };

  // ── Totals ──────────────────────────────────────────────────────────────────

  const dayTotals = (day: string) => {
    let cal=0, prot=0, carb=0, fat=0;
    MEAL_TYPES.forEach((mt) => {
      (itemsByKey[slotKey(day, mt)] ?? []).forEach((item) => {
        const s = item.nutrient_snapshot; if (!s) return;
        const scale = s.serving_size > 0 ? parseFloat(item.quantity) / s.serving_size : 1;
        cal += s.calories*scale; prot += s.protein*scale; carb += s.carbs*scale; fat += s.fat*scale;
      });
    });
    return { cal: Math.round(cal), prot: Math.round(prot), carb: Math.round(carb), fat: Math.round(fat) };
  };

  const dayMicroTotals = (day: string): Record<string, number> => {
    const totals: Record<string, number> = {};
    MEAL_TYPES.forEach((mt) => {
      (itemsByKey[slotKey(day, mt)] ?? []).forEach((item) => {
        const s = item.nutrient_snapshot; if (!s?.micronutrients) return;
        const scale = s.serving_size > 0 ? parseFloat(item.quantity) / s.serving_size : 1;
        for (const [k, v] of Object.entries(s.micronutrients)) {
          totals[k] = (totals[k] ?? 0) + (v as number) * scale;
        }
      });
    });
    return totals;
  };

  const t = prescriptionTargets;
  const selectedTotals = dayTotals(selectedDay);
  const selectedMicroTotals = dayMicroTotals(selectedDay);
  const trackerTargets = [
    { label: 'Energy',  current: selectedTotals.cal,  target: t.energy,  unit: 'kcal' },
    { label: 'Protein', current: selectedTotals.prot, target: t.protein, unit: 'g'    },
    { label: 'Carbs',   current: selectedTotals.carb, target: t.carbs,   unit: 'g'    },
    { label: 'Fat',     current: selectedTotals.fat,  target: t.fat,     unit: 'g'    },
  ];

  // Preview scaled nutrition in edit modal
  const editScaledSnap = (() => {
    const s = editTarget?.item.nutrient_snapshot;
    if (!s) return null;
    const q = parseFloat(editServings) || 1;
    const scale = s.serving_size > 0 ? q / s.serving_size : q;
    return { cal: Math.round(s.calories * scale), prot: Math.round(s.protein * scale), carb: Math.round(s.carbs * scale), fat: Math.round(s.fat * scale) };
  })();

  return (
    <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-2">
        <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider flex items-center gap-2">
          <Salad className="h-4 w-4 text-emerald-600" /> Weekly Meal Plan
        </h3>
        <div className="flex items-center gap-1.5 flex-wrap">
          {displayedMicros.length > 0 && (
            <button onClick={() => setShowMicros((v) => !v)}
              className={`flex items-center gap-1 px-3 py-1.5 text-[10px] font-bold border rounded-lg transition-colors cursor-pointer ${showMicros ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-200 text-zinc-500 hover:border-violet-400 hover:text-violet-700'}`}>
              <FlaskConical className="h-3 w-3" /> Micros
            </button>
          )}
          {templates.length > 0 && (
            <Button variant="secondary" onClick={() => setFromTemplateOpen(true)} className="w-auto px-3 py-1.5 text-[10px]">
              <LayoutTemplate className="h-3 w-3" /> From Template
            </Button>
          )}
          {activePlan && (
            <Button variant="secondary" onClick={() => setSaveTemplateOpen(true)} className="w-auto px-3 py-1.5 text-[10px]">
              <BookmarkPlus className="h-3 w-3" /> Save Template
            </Button>
          )}
          <Button variant="secondary" onClick={handleGenerate} loading={generating} className="w-auto px-3 py-1.5 text-[10px]">
            {!generating && <Wand2 className="h-3 w-3" />} Auto-Generate
          </Button>
          <Button variant="secondary" loading={creatingPlan} onClick={handleCreatePlan} className="w-auto px-3 py-1.5 text-[10px]">
            <Plus className="h-3 w-3" /> New Week
          </Button>
        </div>
      </div>

      {allergens.length > 0 && (
        <p className="text-[9px] text-amber-600 flex items-center gap-1">
          <AlertTriangle className="h-3 w-3" /> Auto-generate will exclude recipes containing: {allergens.join(', ')}
        </p>
      )}

      {generateError && (
        <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
          <AlertTriangle className="h-3.5 w-3.5 text-amber-600 flex-shrink-0 mt-0.5" />
          <p className="text-[10px] text-amber-800">{generateError}</p>
        </div>
      )}

      {/* Plan pills */}
      {plans.length > 0 && (
        <div className="flex gap-1.5 flex-wrap items-center">
          {plans.map((p) => (
            <div key={p.id} className="flex items-center gap-0.5">
              <button onClick={() => setActivePlan(p)}
                className={`px-3 py-1.5 rounded-l-lg text-[10px] font-bold border-y border-l transition-colors cursor-pointer ${activePlan?.id === p.id ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-400'}`}>
                Week of {p.week_start_date}
              </button>
              <Button variant="icon" onClick={() => setConfirmDeleteId(p.id)} title="Delete plan"
                className={`rounded-l-none rounded-r-lg border-y border-r ${activePlan?.id === p.id ? 'bg-emerald-600 text-white border-emerald-600 hover:!bg-red-600 hover:!border-red-600' : 'border-zinc-200 hover:text-red-500 hover:border-red-300'}`}>
                <Trash2 className="h-3 w-3" />
              </Button>
            </div>
          ))}
          {loadingPlans && <Loader2 className="h-3.5 w-3.5 animate-spin text-zinc-400" />}
        </div>
      )}

      {deleteError && (
        <div className="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-xl">
          <AlertTriangle className="h-3.5 w-3.5 text-red-600 flex-shrink-0 mt-0.5" />
          <p className="text-[10px] text-red-800">{deleteError}</p>
        </div>
      )}

      {!activePlan && !loadingPlans && (
        <div className="bg-zinc-50 border border-zinc-200 rounded-xl p-8 text-center">
          <p className="text-xs text-zinc-400">No meal plans yet. Create one above or click Auto-Generate.</p>
        </div>
      )}

      {activePlan && (
        <>
          <MacroTrackerBar targets={trackerTargets} className="sticky top-0 z-10 rounded-xl" />

          {/* Micro totals row */}
          {showMicros && displayedMicros.length > 0 && (
            <div className="p-3 bg-violet-50 border border-violet-100 rounded-xl space-y-2">
              <p className="text-[9px] font-bold text-violet-500 uppercase tracking-widest">Daily Micronutrients — {selectedDay}</p>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                {displayedMicros.map((key) => {
                  const meta = ALL_MICROS.find((m) => m.key === key);
                  const val  = Math.round((selectedMicroTotals[key] ?? 0) * 10) / 10;
                  const limit = micronutrientLimits[key];
                  const overLimit = limit?.max != null && val > limit.max;
                  const underLimit = limit?.min != null && val < limit.min;
                  return (
                    <div key={key} className={`flex items-center justify-between px-2.5 py-1.5 rounded-lg border text-[10px] ${overLimit ? 'bg-red-50 border-red-200' : underLimit ? 'bg-amber-50 border-amber-200' : 'bg-white border-zinc-200'}`}>
                      <span className="font-semibold text-zinc-700">{meta?.label ?? key}</span>
                      <span className={`font-mono font-bold ${overLimit ? 'text-red-600' : underLimit ? 'text-amber-600' : 'text-zinc-500'}`}>
                        {val}{meta?.unit}
                        {limit?.max != null && <span className="font-normal text-zinc-400"> / {limit.max}</span>}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Day pills */}
          <div className="flex gap-1.5 flex-wrap">
            {DAYS.map((d) => {
              const tot = dayTotals(d);
              const isDayFlagged = activePlan.days.some((day) => day.day_of_week === d && day.flagged === true);
              return (
                <button key={d} onClick={() => setSelectedDay(d)}
                  className={`px-3 py-2 rounded-xl text-[10px] font-bold border transition-colors cursor-pointer text-center ${
                    selectedDay === d ? 'bg-emerald-600 text-white border-emerald-600'
                    : isDayFlagged ? 'bg-white text-amber-700 border-amber-300 hover:border-amber-400'
                    : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-300'
                  }`}>
                  <span className="block">{d.slice(0,3)}</span>
                  {tot.cal > 0 && <span className="block font-normal opacity-80">{tot.cal}kcal</span>}
                  {isDayFlagged && selectedDay !== d && <span className="block text-[8px] text-amber-500 font-bold">⚠ review</span>}
                </button>
              );
            })}
          </div>

          {/* Meal slots */}
          <div className="space-y-2.5">
            {MEAL_TYPES.map((mt) => {
              const day = activePlan.days.find((d) => d.day_of_week === selectedDay && d.meal_type === mt);
              if (!day) return null;
              const key = slotKey(selectedDay, mt);
              const items = itemsByKey[key] ?? [];
              return (
                <div key={mt} className="border border-zinc-100 rounded-xl p-3.5 space-y-2">
                  <div className="flex items-center justify-between">
                    <h4 className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">{MEAL_LABELS[mt]}</h4>
                    <button onClick={() => openPicker(day.id, mt)}
                      className="flex items-center gap-1 text-[10px] font-bold text-emerald-600 hover:text-emerald-800 cursor-pointer">
                      <Plus className="h-3 w-3" /> Add
                    </button>
                  </div>
                  {items.length === 0 && <p className="text-[10px] text-zinc-300 italic">Empty</p>}
                  {items.map((item) => {
                    const s = item.nutrient_snapshot;
                    const scale = s && s.serving_size > 0 ? parseFloat(item.quantity) / s.serving_size : 1;
                    const isDisliked = foodDislikes.some((d) => s?.name?.toLowerCase().includes(d));
                    return (
                      <div key={item.id}>
                        <div className="flex items-center justify-between py-1 px-1.5 rounded-lg hover:bg-zinc-50 group">
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-1.5">
                              <span className="text-xs font-medium text-zinc-800 truncate">{s?.name ?? '—'}</span>
                              {item.source === 'usda' && <span className="text-[8px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-1.5 rounded-full uppercase">USDA</span>}
                              {parseFloat(item.quantity) !== 1 && <span className="text-[8px] text-violet-500 font-bold">×{item.quantity}</span>}
                            </div>
                            {s && <p className="text-[10px] text-zinc-400">{item.quantity}{item.unit} · {Math.round(s.calories*scale)}kcal · P{Math.round(s.protein*scale)}g · C{Math.round(s.carbs*scale)}g · F{Math.round(s.fat*scale)}g</p>}
                          </div>
                          <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <Button variant="icon" onClick={() => openEdit(item, day.id, key)} title="Edit"
                              className="hover:text-violet-600 hover:bg-violet-50">
                              <Edit2 className="h-3 w-3" />
                            </Button>
                            {item.source === 'usda' && (
                              <Button variant="icon"
                                onClick={() => { if (item.fdc_id) { setSavingToLibrary(item.fdc_id); importUsdaFood(parseInt(item.fdc_id)).finally(() => setSavingToLibrary(null)); } }}
                                disabled={savingToLibrary === item.fdc_id} title="Save to Library"
                                className="hover:text-emerald-600 hover:bg-emerald-50">
                                {savingToLibrary === item.fdc_id ? <Loader2 className="h-3 w-3 animate-spin" /> : <BookmarkPlus className="h-3 w-3" />}
                              </Button>
                            )}
                            <Button variant="icon" onClick={() => removeItem(key, day.id, item.id)}
                              className="hover:text-red-600 hover:bg-red-50">
                              <Trash2 className="h-3 w-3" />
                            </Button>
                          </div>
                        </div>
                        {isDisliked && (
                          <p className="text-[9px] text-amber-600 font-bold flex items-center gap-1 px-1.5 pb-1">
                            <AlertTriangle className="h-2.5 w-2.5" /> Patient dislikes this food — RND review recommended
                          </p>
                        )}
                      </div>
                    );
                  })}
                </div>
              );
            })}
          </div>
        </>
      )}

      {/* ── Meal Edit Modal ─────────────────────────────────────────────────── */}
      {editTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[85vh]">
            <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
              <div>
                <h3 className="text-sm font-extrabold text-zinc-900">{editTarget.item.nutrient_snapshot?.name ?? 'Edit Meal'}</h3>
                <p className="text-[9px] text-zinc-400">Changes only affect this plan — not the recipe library.</p>
              </div>
              <button onClick={() => setEditTarget(null)} className="text-zinc-400 hover:text-zinc-700 cursor-pointer"><X className="h-4 w-4" /></button>
            </div>

            {/* Tabs */}
            <div className="flex border-b border-zinc-100 px-6">
              {(['scale', 'ingredients'] as const).map((tab) => (
                <button key={tab} onClick={() => setEditTab(tab)}
                  className={`px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider border-b-2 transition-colors cursor-pointer ${editTab === tab ? 'border-violet-600 text-violet-700' : 'border-transparent text-zinc-400 hover:text-zinc-600'}`}>
                  {tab === 'scale' ? 'Master Scale' : 'Ingredients'}
                </button>
              ))}
            </div>

            <div className="overflow-y-auto flex-1 p-6 space-y-4">
              {/* Master scale tab */}
              {editTab === 'scale' && (
                <div className="space-y-4">
                  <p className="text-[10px] text-zinc-400">Adjust serving size. All nutrients scale proportionally.</p>
                  <div className="space-y-2">
                    <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Serving Amount</label>
                    <div className="flex items-center gap-3">
                      <input type="number" min="0.1" max="10" step="0.1"
                        value={editServings}
                        onChange={(e) => setEditServings(e.target.value)}
                        className="w-28 px-3 py-2 text-sm font-mono border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500" />
                      <span className="text-xs text-zinc-400">serving(s)</span>
                    </div>
                    {/* Range slider */}
                    <input type="range" min="0.5" max="3" step="0.1"
                      value={editServings}
                      onChange={(e) => setEditServings(e.target.value)}
                      className="w-full accent-violet-600" />
                    <div className="flex justify-between text-[9px] text-zinc-400">
                      <span>0.5×</span><span>1×</span><span>2×</span><span>3×</span>
                    </div>
                  </div>
                  {editScaledSnap && (
                    <div className="grid grid-cols-4 gap-2 pt-2">
                      {[['Energy', editScaledSnap.cal, 'kcal'], ['Protein', editScaledSnap.prot, 'g'], ['Carbs', editScaledSnap.carb, 'g'], ['Fat', editScaledSnap.fat, 'g']].map(([label, val, unit]) => (
                        <div key={String(label)} className="bg-violet-50 border border-violet-100 rounded-xl p-2.5 text-center">
                          <p className="text-[9px] font-bold text-violet-500 uppercase">{label}</p>
                          <p className="text-sm font-extrabold font-mono text-zinc-900">{val}<span className="text-[9px] font-normal text-zinc-400 ml-0.5">{unit}</span></p>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {/* Ingredients tab */}
              {editTab === 'ingredients' && (
                <div className="space-y-3">
                  {!editTarget.item.recipe_id && (
                    <p className="text-xs text-zinc-400 italic">Ingredient editing is only available for recipe-based items.</p>
                  )}
                  {editLoadingRecipe && <div className="flex items-center justify-center py-8"><Loader2 className="h-5 w-5 animate-spin text-zinc-400" /></div>}
                  {editRecipe && editIngredients.length > 0 && (
                    <>
                      <p className="text-[10px] text-zinc-400">
                        Modify ingredient quantities. &ldquo;Recalculate&rdquo; updates the nutrition for this plan item only.
                        Recipe makes <strong>{editRecipe.servings ?? 1} serving(s)</strong>.
                      </p>
                      <div className="space-y-2">
                        {editIngredients.map((row, i) => (
                          <div key={row.id} className="flex items-center gap-3 p-2.5 border border-zinc-100 rounded-xl">
                            <div className="flex-1 min-w-0">
                              <p className="text-xs font-semibold text-zinc-800 truncate">{row.ing.food_item?.name ?? '—'}</p>
                              <p className="text-[9px] text-zinc-400">{row.ing.unit} · {row.ing.food_item?.calories ?? 0} kcal per {row.ing.food_item?.serving_size ?? 100}{row.ing.food_item?.serving_unit ?? 'g'}</p>
                            </div>
                            <div className="flex items-center gap-1.5">
                              <input type="number" min="0" step="1"
                                value={row.qty}
                                onChange={(e) => {
                                  setEditIngredients((prev) => prev.map((r, idx) => idx === i ? { ...r, qty: e.target.value } : r));
                                }}
                                className="w-16 px-2 py-1.5 text-sm font-mono border border-zinc-200 rounded-lg focus:outline-none focus:border-violet-500 text-right" />
                              <span className="text-[10px] text-zinc-400 w-6">{row.ing.unit}</span>
                            </div>
                          </div>
                        ))}
                      </div>
                      {/* Preview recalculated totals */}
                      {(() => {
                        const r = recalcFromIngredients();
                        return (
                          <div className="grid grid-cols-4 gap-2 pt-1">
                            {[['Energy', r.calories, 'kcal'], ['Protein', r.protein, 'g'], ['Carbs', r.carbs, 'g'], ['Fat', r.fat, 'g']].map(([label, val, unit]) => (
                              <div key={String(label)} className="bg-violet-50 border border-violet-100 rounded-xl p-2.5 text-center">
                                <p className="text-[9px] font-bold text-violet-500 uppercase">{label}</p>
                                <p className="text-sm font-extrabold font-mono text-zinc-900">{Math.round(Number(val) || 0)}<span className="text-[9px] font-normal text-zinc-400 ml-0.5">{unit}</span></p>
                              </div>
                            ))}
                          </div>
                        );
                      })()}
                    </>
                  )}
                </div>
              )}
            </div>

            <div className="flex items-center justify-end gap-2 px-6 py-4 border-t border-zinc-100">
              <Button variant="ghost" onClick={() => setEditTarget(null)}>Cancel</Button>
              <button onClick={handleSaveEdit} disabled={editSaving}
                className="px-5 py-2 text-xs font-bold bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
                {editSaving ? 'Saving…' : 'Save Changes'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Delete Plan Confirm ─────────────────────────────────────────────── */}
      {confirmDeleteId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
            <h3 className="text-sm font-extrabold text-zinc-900">Delete Meal Plan?</h3>
            <p className="text-xs text-zinc-500">This will permanently delete the plan and all its food entries.</p>
            <div className="flex gap-2 justify-end">
              <Button variant="ghost" onClick={() => setConfirmDeleteId(null)}>Cancel</Button>
              <Button variant="danger" onClick={handleDeletePlan} loading={deleting} className="w-auto px-4 py-2 text-xs">
                Delete Plan
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* ── Food Picker Modal ───────────────────────────────────────────────── */}
      {pickerOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[80vh]">
            <div className="flex items-center justify-between p-4 border-b border-zinc-100">
              <h3 className="text-sm font-extrabold text-zinc-900">Add Food</h3>
              <button onClick={() => setPickerOpen(false)} className="text-zinc-400 hover:text-zinc-700 cursor-pointer"><X className="h-4 w-4" /></button>
            </div>
            <div className="flex gap-1 px-4 pt-3">
              {([{ key: 'library' as const, label: 'Library', Icon: Database }, { key: 'recipes' as const, label: 'Recipes', Icon: Salad }, { key: 'usda' as const, label: 'USDA', Icon: Leaf }]).map(({ key, label, Icon }) => (
                <button key={key} onClick={() => setPickerTab(key)}
                  className={`flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer ${pickerTab === key ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-zinc-50 text-zinc-500 border-zinc-200'}`}>
                  <Icon className="h-3 w-3" />{label}
                </button>
              ))}
            </div>
            {pickerError && (
              <div className="mx-4 mt-2 flex items-start gap-2 p-2.5 bg-red-50 border border-red-200 rounded-lg">
                <AlertTriangle className="h-3.5 w-3.5 text-red-600 flex-shrink-0 mt-0.5" />
                <p className="text-[10px] text-red-800">{pickerError}</p>
              </div>
            )}
            <div className="p-4 space-y-3 overflow-y-auto flex-1">
              {pickerTab === 'library' && (
                <>
                  <div className="relative"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                    <input type="text" value={libraryQuery} autoFocus placeholder="Search library…"
                      onChange={async (e) => { setLibraryQuery(e.target.value); if (e.target.value.length >= 2) { setPickerLoading(true); try { setLibraryResults((await fetchFoodItems(e.target.value)).data); } finally { setPickerLoading(false); } } else setLibraryResults([]); }}
                      className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" /></div>
                  {pickerLoading && <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />}
                  {libraryResults.map((food) => (
                    <div key={food.id} className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
                      <div><p className="text-xs font-semibold text-zinc-800">{food.name}</p><p className="text-[10px] text-zinc-400">{food.calories}kcal · P{food.protein}g · C{food.carbs}g · F{food.fat}g</p></div>
                      <Button variant="primary" loading={adding === food.id} onClick={() => addFromLibrary(food)} className="w-auto px-3 py-1.5 text-[10px]">Add</Button>
                    </div>
                  ))}
                </>
              )}
              {pickerTab === 'recipes' && (
                <>
                  <div className="relative"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                    <input type="text" value={recipeQuery} autoFocus placeholder="Search recipes…"
                      onChange={async (e) => { setRecipeQuery(e.target.value); if (e.target.value.length >= 2) { setPickerLoading(true); try { setRecipeResults((await fetchRecipes(e.target.value)).data); } finally { setPickerLoading(false); } } else setRecipeResults([]); }}
                      className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" /></div>
                  {pickerLoading && <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />}
                  {recipeResults.map((r) => (
                    <div key={r.id} className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
                      <div><p className="text-xs font-semibold text-zinc-800">{r.name}</p><p className="text-[10px] text-zinc-400">{r.total_calories}kcal · P{r.total_protein}g · C{r.total_carbs}g · F{r.total_fat}g{r.servings ? ` · ${r.servings} srv` : ''}</p></div>
                      <Button variant="primary" loading={adding === `recipe-${r.id}`} onClick={() => addFromRecipe(r)} className="w-auto px-3 py-1.5 text-[10px]">Add</Button>
                    </div>
                  ))}
                </>
              )}
              {pickerTab === 'usda' && (
                <>
                  <div className="relative"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                    <input type="text" value={usdaQuery} autoFocus placeholder="Search USDA…"
                      onChange={async (e) => { setUsdaQuery(e.target.value); if (e.target.value.length >= 2) { setPickerLoading(true); try { setUsdaResults(await searchUsda(e.target.value)); } finally { setPickerLoading(false); } } else setUsdaResults([]); }}
                      className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" /></div>
                  <p className="text-[9px] text-zinc-400">USDA foods are not saved to the library unless you bookmark them.</p>
                  {pickerLoading && <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />}
                  {usdaResults.map((food) => (
                    <div key={food.fdc_id} className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
                      <div><p className="text-xs font-semibold text-zinc-800">{food.name}</p><p className="text-[10px] text-zinc-400">{food.calories}kcal · P{food.protein}g · C{food.carbs}g · F{food.fat}g</p></div>
                      <Button variant="primary" loading={adding === food.fdc_id} onClick={() => addFromUsda(food)} className="w-auto px-3 py-1.5 text-[10px]">Add</Button>
                    </div>
                  ))}
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ── Save Template Modal ─────────────────────────────────────────────── */}
      {saveTemplateOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
            <h3 className="text-sm font-extrabold text-zinc-900">Save as Template</h3>
            <div className="space-y-1.5">
              <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Template Name</label>
              <input type="text" value={templateName} onChange={(e) => setTemplateName(e.target.value)} placeholder='e.g. "CKD Stage 4 — Week A"' autoFocus
                className="w-full px-3.5 py-2.5 text-sm border border-zinc-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
            </div>
            <div className="flex gap-2 justify-end">
              <Button variant="ghost" onClick={() => setSaveTemplateOpen(false)}>Cancel</Button>
              <Button variant="primary" onClick={handleSaveTemplate} loading={savingTemplate}
                disabled={savingTemplate || !templateName.trim()} className="w-auto px-4 py-2 text-xs">
                Save Template
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* ── Template Manager Modal ──────────────────────────────────────────── */}
      {fromTemplateOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[80vh]">
            <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
              {viewingTemplate ? (
                <div className="flex items-center gap-2">
                  <button onClick={() => setViewingTemplate(null)} className="text-zinc-400 hover:text-zinc-700 cursor-pointer">
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                  </button>
                  <h3 className="text-sm font-extrabold text-zinc-900">{viewingTemplate.name}</h3>
                </div>
              ) : <h3 className="text-sm font-extrabold text-zinc-900">Meal Plan Templates</h3>}
              <button onClick={() => { setFromTemplateOpen(false); setViewingTemplate(null); }} className="text-zinc-400 hover:text-zinc-700 cursor-pointer"><X className="h-4 w-4" /></button>
            </div>
            <div className="overflow-y-auto flex-1 p-4">
              {!viewingTemplate && (templates.length === 0 ? (
                <p className="text-xs text-zinc-400 text-center py-8">No templates saved yet.</p>
              ) : (
                <div className="space-y-2">
                  {templates.map((tmpl) => (
                    <div key={tmpl.id} className="flex items-center gap-2 p-3 border border-zinc-200 rounded-xl hover:border-zinc-300 transition-colors">
                      <div className="flex-1 min-w-0">
                        <p className="text-xs font-semibold text-zinc-800 truncate">{tmpl.name}</p>
                        {tmpl.goal_type && <p className="text-[10px] text-zinc-400 capitalize">{tmpl.goal_type.replace(/_/g, ' ')}</p>}
                      </div>
                      <div className="flex items-center gap-1 flex-shrink-0">
                        <button onClick={() => handleViewTemplate(tmpl.id)} disabled={loadingTemplate === tmpl.id}
                          className="px-2.5 py-1.5 text-[10px] font-bold text-zinc-500 border border-zinc-200 rounded-lg hover:border-zinc-400 hover:text-zinc-700 transition-colors cursor-pointer disabled:opacity-40">
                          {loadingTemplate === tmpl.id ? <Loader2 className="h-3 w-3 animate-spin" /> : 'View'}
                        </button>
                        <button onClick={() => handleFromTemplate(tmpl.id)}
                          className="px-2.5 py-1.5 text-[10px] font-bold text-emerald-700 border border-emerald-300 rounded-lg hover:bg-emerald-50 transition-colors cursor-pointer">Use</button>
                        <button onClick={() => setConfirmDeleteTemplateId(tmpl.id)} title="Delete template"
                          className="p-1.5 text-zinc-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors cursor-pointer"><Trash2 className="h-3 w-3" /></button>
                      </div>
                    </div>
                  ))}
                </div>
              ))}
              {viewingTemplate && (
                <div className="space-y-3">
                  {viewingTemplate.goal_type && <p className="text-[10px] text-zinc-400 capitalize">{viewingTemplate.goal_type.replace(/_/g, ' ')}</p>}
                  {(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as const).map((day) => {
                    const daySlots = viewingTemplate.days.filter((d) => d.day_of_week === day);
                    if (daySlots.length === 0) return null;
                    return (
                      <div key={day} className="border border-zinc-100 rounded-xl overflow-hidden">
                        <div className="px-3 py-2 bg-zinc-50 border-b border-zinc-100"><p className="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{day}</p></div>
                        <div className="divide-y divide-zinc-50">
                          {daySlots.map((slot) => (
                            <div key={slot.id} className="flex items-center justify-between px-3 py-2">
                              <div>
                                <p className="text-[10px] font-semibold text-zinc-500 capitalize">{slot.meal_type.replace('_', ' ')}</p>
                                <p className="text-xs text-zinc-800">{slot.food_name ?? '—'}</p>
                              </div>
                              {slot.calories != null && <p className="text-[10px] text-zinc-400">{Math.round(slot.calories)} kcal</p>}
                            </div>
                          ))}
                        </div>
                      </div>
                    );
                  })}
                  <button onClick={() => handleFromTemplate(viewingTemplate.id)}
                    className="w-full py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors cursor-pointer mt-2">
                    Use This Template
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ── Delete Template Confirm ─────────────────────────────────────────── */}
      {confirmDeleteTemplateId && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
            <h3 className="text-sm font-extrabold text-zinc-900">Delete Template?</h3>
            <p className="text-xs text-zinc-500">This template will be permanently deleted. Plans already created from it are not affected.</p>
            <div className="flex gap-2 justify-end">
              <Button variant="ghost" onClick={() => setConfirmDeleteTemplateId(null)}>Cancel</Button>
              <Button variant="danger" onClick={handleDeleteTemplate} loading={deletingTemplate} className="w-auto px-4 py-2 text-xs">
                Delete Template
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
