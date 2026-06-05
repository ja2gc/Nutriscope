"use client";

import React, { useEffect, useState, useCallback } from "react";
import { Plus, X, Search, Loader2, Database, Leaf, Trash2, BookmarkPlus, Salad } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  fetchMealPlans, createMealPlan, fetchMealPlanItems, addMealPlanItem, removeMealPlanItem,
  MealPlan, MealPlanItem,
} from "@/services/mealPlanService";
import {
  fetchFoodItems, fetchRecipes, searchUsda, importUsdaFood,
  FoodItem, Recipe, UsdaSearchResult,
} from "@/services/foodLibraryService";

const DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as const;
const MEAL_TYPES = ['breakfast','am_snack','lunch','pm_snack','dinner'] as const;
const MEAL_LABELS: Record<string, string> = {
  breakfast: 'Breakfast', am_snack: 'AM Snack', lunch: 'Lunch', pm_snack: 'PM Snack', dinner: 'Dinner',
};

interface Props {
  ncpId: string;
  prescriptionTargets: { energy: number; protein: number; carbs: number; fat: number };
}

export default function MealPlanSection({ ncpId, prescriptionTargets }: Props) {
  const [plans, setPlans]               = useState<MealPlan[]>([]);
  const [activePlan, setActivePlan]     = useState<MealPlan | null>(null);
  const [selectedDay, setSelectedDay]   = useState<string>(DAYS[0]);
  const [itemsByKey, setItemsByKey]     = useState<Record<string, MealPlanItem[]>>({});
  const [loadingPlans, setLoadingPlans] = useState(false);
  const [creatingPlan, setCreatingPlan] = useState(false);

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

  useEffect(() => { loadPlans(); }, [loadPlans]);

  const loadItems = useCallback(async (plan: MealPlan) => {
    const map: Record<string, MealPlanItem[]> = {};
    await Promise.all(plan.days.map(async (day) => {
      const items = await fetchMealPlanItems(ncpId, plan.id, day.id);
      map[slotKey(day.day_of_week, day.meal_type)] = items;
    }));
    setItemsByKey(map);
  }, [ncpId]);

  useEffect(() => { if (activePlan) loadItems(activePlan); }, [activePlan, loadItems]);

  const handleCreatePlan = async () => {
    setCreatingPlan(true);
    try {
      const d = new Date(); const day = d.getDay();
      d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
      const plan = await createMealPlan(ncpId, { week_start_date: d.toISOString().split('T')[0], generation_type: 'manual' });
      setPlans((p) => [plan, ...p]);
      setActivePlan(plan);
    } finally { setCreatingPlan(false); }
  };

  const openPicker = (dayId: number, mealType: string) => {
    setPickerTarget({ dayId, mealType }); setPickerOpen(true); setPickerTab('library');
    setLibraryQuery(''); setLibraryResults([]); setRecipeQuery(''); setRecipeResults([]);
    setUsdaQuery(''); setUsdaResults([]);
  };

  const appendItem = (item: MealPlanItem, plan: MealPlan, dayId: number) => {
    const day = plan.days.find((d) => d.id === dayId); if (!day) return;
    const key = slotKey(day.day_of_week, day.meal_type);
    setItemsByKey((prev) => ({ ...prev, [key]: [...(prev[key] ?? []), item] }));
  };

  const addFromLibrary = async (food: FoodItem) => {
    if (!pickerTarget || !activePlan) return; setAdding(food.id);
    try { appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { food_item_id: food.id, quantity: 1, unit: 'serving' }), activePlan, pickerTarget.dayId); }
    finally { setAdding(null); }
  };
  const addFromRecipe = async (recipe: Recipe) => {
    if (!pickerTarget || !activePlan) return; setAdding(`recipe-${recipe.id}`);
    try { appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { recipe_id: recipe.id, quantity: 1, unit: 'serving' }), activePlan, pickerTarget.dayId); }
    finally { setAdding(null); }
  };
  const addFromUsda = async (food: UsdaSearchResult) => {
    if (!pickerTarget || !activePlan) return; setAdding(food.fdc_id);
    try { appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { fdc_id: String(food.fdc_id), quantity: 100, unit: 'g' }), activePlan, pickerTarget.dayId); }
    finally { setAdding(null); }
  };
  const removeItem = async (key: string, dayId: number, itemId: number) => {
    if (!activePlan) return;
    await removeMealPlanItem(ncpId, activePlan.id, dayId, itemId);
    setItemsByKey((prev) => ({ ...prev, [key]: (prev[key] ?? []).filter((i) => i.id !== itemId) }));
  };

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

  const t = prescriptionTargets;
  const dayT = dayTotals(selectedDay);

  return (
    <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider flex items-center gap-2">
          <Salad className="h-4 w-4 text-emerald-600" /> Weekly Meal Plan
        </h3>
        <Button variant="primary" loading={creatingPlan} onClick={handleCreatePlan} className="w-auto px-3 py-1.5 text-[10px]">
          <Plus className="h-3 w-3 mr-1" /> New Week
        </Button>
      </div>

      {/* Plan selector */}
      {plans.length > 0 && (
        <div className="flex gap-1.5 flex-wrap">
          {plans.map((p) => (
            <button key={p.id} onClick={() => setActivePlan(p)}
              className={`px-3 py-1.5 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer ${
                activePlan?.id === p.id ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-400'
              }`}>Week of {p.week_start_date}</button>
          ))}
          {loadingPlans && <Loader2 className="h-3.5 w-3.5 animate-spin text-zinc-400" />}
        </div>
      )}

      {!activePlan && !loadingPlans && (
        <div className="bg-zinc-50 border border-zinc-200 rounded-xl p-8 text-center">
          <p className="text-xs text-zinc-400">No meal plans yet. Create one above.</p>
        </div>
      )}

      {activePlan && (
        <>
          {/* Day pills */}
          <div className="flex gap-1.5 flex-wrap">
            {DAYS.map((d) => {
              const tot = dayTotals(d);
              return (
                <button key={d} onClick={() => setSelectedDay(d)}
                  className={`px-3 py-2 rounded-xl text-[10px] font-bold border transition-colors cursor-pointer text-center ${
                    selectedDay === d ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-300'
                  }`}>
                  <span className="block">{d.slice(0,3)}</span>
                  {tot.cal > 0 && <span className="block font-normal opacity-80">{tot.cal}kcal</span>}
                </button>
              );
            })}
          </div>

          {/* Day vs target bar */}
          {dayT.cal > 0 && (
            <div className="flex gap-4 px-4 py-2.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs">
              {([
                { label: 'Energy',  curr: dayT.cal,  tgt: t.energy,  unit: 'kcal' },
                { label: 'Protein', curr: dayT.prot, tgt: t.protein, unit: 'g'    },
                { label: 'Carbs',   curr: dayT.carb, tgt: t.carbs,   unit: 'g'    },
                { label: 'Fat',     curr: dayT.fat,  tgt: t.fat,     unit: 'g'    },
              ]).map(({ label, curr, tgt }) => {
                const pct = tgt > 0 ? Math.abs(curr - tgt) / tgt : 0;
                const color = pct <= 0.10 ? 'text-emerald-700' : pct <= 0.20 ? 'text-amber-600' : 'text-red-600';
                return (
                  <div key={label}>
                    <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">{label}</p>
                    <p className={`font-extrabold font-mono ${color}`}>
                      {curr}{tgt > 0 && <span className="text-[9px] font-normal text-zinc-400">/{tgt}</span>}
                    </p>
                  </div>
                );
              })}
            </div>
          )}

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
                    return (
                      <div key={item.id} className="flex items-center justify-between py-1 px-1.5 rounded-lg hover:bg-zinc-50 group">
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-1.5">
                            <span className="text-xs font-medium text-zinc-800 truncate">{s?.name ?? '—'}</span>
                            {item.source === 'usda' && <span className="text-[8px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-1.5 rounded-full uppercase">USDA</span>}
                          </div>
                          {s && <p className="text-[10px] text-zinc-400">{item.quantity}{item.unit} · {Math.round(s.calories*scale)}kcal · P{Math.round(s.protein*scale)}g · C{Math.round(s.carbs*scale)}g</p>}
                        </div>
                        <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                          {item.source === 'usda' && (
                            <button
                              onClick={() => { if (item.fdc_id) { setSavingToLibrary(item.fdc_id); importUsdaFood(parseInt(item.fdc_id)).finally(() => setSavingToLibrary(null)); } }}
                              disabled={savingToLibrary === item.fdc_id} title="Save to Library"
                              className="p-1.5 rounded text-zinc-400 hover:text-emerald-600 hover:bg-emerald-50 cursor-pointer transition-colors">
                              {savingToLibrary === item.fdc_id ? <Loader2 className="h-3 w-3 animate-spin" /> : <BookmarkPlus className="h-3 w-3" />}
                            </button>
                          )}
                          <button onClick={() => removeItem(key, day.id, item.id)}
                            className="p-1.5 rounded text-zinc-400 hover:text-red-600 hover:bg-red-50 cursor-pointer transition-colors">
                            <Trash2 className="h-3 w-3" />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              );
            })}
          </div>
        </>
      )}

      {/* Food Picker Modal */}
      {pickerOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[80vh]">
            <div className="flex items-center justify-between p-4 border-b border-zinc-100">
              <h3 className="text-sm font-extrabold text-zinc-900">Add Food</h3>
              <button onClick={() => setPickerOpen(false)} className="text-zinc-400 hover:text-zinc-700 cursor-pointer"><X className="h-4 w-4" /></button>
            </div>
            <div className="flex gap-1 px-4 pt-3">
              {([
                { key: 'library' as const, label: 'Library', Icon: Database },
                { key: 'recipes' as const, label: 'Recipes',  Icon: Salad },
                { key: 'usda' as const,    label: 'USDA',     Icon: Leaf },
              ]).map(({ key, label, Icon }) => (
                <button key={key} onClick={() => setPickerTab(key)}
                  className={`flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer ${
                    pickerTab === key ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-zinc-50 text-zinc-500 border-zinc-200'
                  }`}><Icon className="h-3 w-3" />{label}</button>
              ))}
            </div>
            <div className="p-4 space-y-3 overflow-y-auto flex-1">
              {pickerTab === 'library' && (
                <>
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                    <input type="text" value={libraryQuery} autoFocus placeholder="Search library…"
                      onChange={async (e) => {
                        setLibraryQuery(e.target.value);
                        if (e.target.value.length >= 2) { setPickerLoading(true); try { setLibraryResults((await fetchFoodItems(e.target.value)).data); } finally { setPickerLoading(false); } }
                        else setLibraryResults([]);
                      }}
                      className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
                  </div>
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
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                    <input type="text" value={recipeQuery} autoFocus placeholder="Search recipes…"
                      onChange={async (e) => {
                        setRecipeQuery(e.target.value);
                        if (e.target.value.length >= 2) { setPickerLoading(true); try { setRecipeResults((await fetchRecipes(e.target.value)).data); } finally { setPickerLoading(false); } }
                        else setRecipeResults([]);
                      }}
                      className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
                  </div>
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
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                    <input type="text" value={usdaQuery} autoFocus placeholder="Search USDA…"
                      onChange={async (e) => {
                        setUsdaQuery(e.target.value);
                        if (e.target.value.length >= 2) { setPickerLoading(true); try { setUsdaResults(await searchUsda(e.target.value)); } finally { setPickerLoading(false); } }
                        else setUsdaResults([]);
                      }}
                      className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
                  </div>
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
    </div>
  );
}
