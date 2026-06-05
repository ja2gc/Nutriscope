"use client";

import React, { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { Salad, User, Plus, X, Search, Loader2, Database, Leaf, Trash2, BookmarkPlus } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  fetchMealPlans, createMealPlan, fetchMealPlanItems, addMealPlanItem, removeMealPlanItem,
  MealPlan, MealPlanDay, MealPlanItem,
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

type PageParams = { patientId: string; ncpId: string };

export default function NcpInterventionPage({ params }: { params: Promise<PageParams> }) {
  const { patientId, ncpId } = use(params);
  const isPlaceholder = patientId === 'select-patient' || ncpId === 'select-ncp';

  const [tab, setTab] = useState<'goals' | 'mealplan'>('goals');
  const [plans, setPlans] = useState<MealPlan[]>([]);
  const [activePlan, setActivePlan] = useState<MealPlan | null>(null);
  const [selectedDay, setSelectedDay] = useState<string>(DAYS[0]);
  const [itemsByKey, setItemsByKey] = useState<Record<string, MealPlanItem[]>>({});
  const [loadingPlans, setLoadingPlans] = useState(false);
  const [creatingPlan, setCreatingPlan] = useState(false);

  // Picker state
  const [pickerOpen, setPickerOpen] = useState(false);
  const [pickerTarget, setPickerTarget] = useState<{ dayId: number; mealType: string } | null>(null);
  const [pickerTab, setPickerTab] = useState<'library' | 'recipes' | 'usda'>('library');
  const [libraryQuery, setLibraryQuery] = useState('');
  const [libraryResults, setLibraryResults] = useState<FoodItem[]>([]);
  const [recipeQuery, setRecipeQuery] = useState('');
  const [recipeResults, setRecipeResults] = useState<Recipe[]>([]);
  const [usdaQuery, setUsdaQuery] = useState('');
  const [usdaResults, setUsdaResults] = useState<UsdaSearchResult[]>([]);
  const [pickerLoading, setPickerLoading] = useState(false);
  const [adding, setAdding] = useState<number | string | null>(null);
  const [savingToLibrary, setSavingToLibrary] = useState<string | null>(null);

  const slotKey = (day: string, mt: string) => `${day}-${mt}`;

  const dayForSlot = useCallback((dayOfWeek: string, mealType: string, plan: MealPlan): MealPlanDay | undefined =>
    plan.days.find((d) => d.day_of_week === dayOfWeek && d.meal_type === mealType),
  []);

  const loadPlans = useCallback(async () => {
    setLoadingPlans(true);
    try {
      const data = await fetchMealPlans(ncpId);
      setPlans(data);
      if (data.length > 0) setActivePlan(data[0]);
    } finally {
      setLoadingPlans(false);
    }
  }, [ncpId]);

  useEffect(() => {
    if (!isPlaceholder && tab === 'mealplan') loadPlans();
  }, [isPlaceholder, tab, loadPlans]);

  const loadItems = useCallback(async (plan: MealPlan) => {
    const map: Record<string, MealPlanItem[]> = {};
    await Promise.all(
      plan.days.map(async (day) => {
        const items = await fetchMealPlanItems(ncpId, plan.id, day.id);
        map[slotKey(day.day_of_week, day.meal_type)] = items;
      })
    );
    setItemsByKey(map);
  }, [ncpId]);

  useEffect(() => {
    if (activePlan) loadItems(activePlan);
  }, [activePlan, loadItems]);

  const handleCreatePlan = async () => {
    setCreatingPlan(true);
    try {
      const plan = await createMealPlan(ncpId, { week_start_date: thisMonday(), generation_type: 'manual' });
      setPlans((p) => [plan, ...p]);
      setActivePlan(plan);
    } finally {
      setCreatingPlan(false);
    }
  };

  const openPicker = (dayId: number, mealType: string) => {
    setPickerTarget({ dayId, mealType });
    setPickerOpen(true);
    setPickerTab('library');
    setLibraryQuery(''); setLibraryResults([]);
    setRecipeQuery('');  setRecipeResults([]);
    setUsdaQuery('');    setUsdaResults([]);
  };

  const searchLibraryFoods = async (q: string) => {
    setLibraryQuery(q);
    if (q.length < 2) { setLibraryResults([]); return; }
    setPickerLoading(true);
    try { setLibraryResults((await fetchFoodItems(q)).data); }
    finally { setPickerLoading(false); }
  };

  const searchRecipeFoods = async (q: string) => {
    setRecipeQuery(q);
    if (q.length < 2) { setRecipeResults([]); return; }
    setPickerLoading(true);
    try { setRecipeResults((await fetchRecipes(q)).data); }
    finally { setPickerLoading(false); }
  };

  const searchUsdaFoods = async (q: string) => {
    setUsdaQuery(q);
    if (q.length < 2) { setUsdaResults([]); return; }
    setPickerLoading(true);
    try { setUsdaResults(await searchUsda(q)); }
    finally { setPickerLoading(false); }
  };

  const appendItem = (item: MealPlanItem, plan: MealPlan, dayId: number) => {
    const day = plan.days.find((d) => d.id === dayId);
    if (!day) return;
    const key = slotKey(day.day_of_week, day.meal_type);
    setItemsByKey((prev) => ({ ...prev, [key]: [...(prev[key] ?? []), item] }));
  };

  const addFromLibrary = async (food: FoodItem) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(food.id);
    try {
      const item = await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { food_item_id: food.id, quantity: 1, unit: 'serving' });
      appendItem(item, activePlan, pickerTarget.dayId);
    } finally { setAdding(null); }
  };

  const addFromRecipe = async (recipe: Recipe) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(`recipe-${recipe.id}`);
    try {
      const item = await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { recipe_id: recipe.id, quantity: 1, unit: 'serving' });
      appendItem(item, activePlan, pickerTarget.dayId);
    } finally { setAdding(null); }
  };

  const addFromUsda = async (food: UsdaSearchResult) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(food.fdc_id);
    try {
      const item = await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { fdc_id: String(food.fdc_id), quantity: 100, unit: 'g' });
      appendItem(item, activePlan, pickerTarget.dayId);
    } finally { setAdding(null); }
  };

  const removeItem = async (key: string, dayId: number, itemId: number) => {
    if (!activePlan) return;
    await removeMealPlanItem(ncpId, activePlan.id, dayId, itemId);
    setItemsByKey((prev) => ({ ...prev, [key]: (prev[key] ?? []).filter((i) => i.id !== itemId) }));
  };

  const saveToLibrary = async (item: MealPlanItem) => {
    if (!item.fdc_id) return;
    setSavingToLibrary(item.fdc_id);
    try { await importUsdaFood(parseInt(item.fdc_id)); }
    finally { setSavingToLibrary(null); }
  };

  const dayTotals = (day: string, plan: MealPlan) => {
    let cal = 0, prot = 0, carb = 0, fat = 0;
    MEAL_TYPES.forEach((mt) => {
      (itemsByKey[slotKey(day, mt)] ?? []).forEach((item) => {
        const s = item.nutrient_snapshot;
        if (!s) return;
        const scale = s.serving_size > 0 ? parseFloat(item.quantity) / s.serving_size : 1;
        cal  += s.calories * scale;
        prot += s.protein  * scale;
        carb += s.carbs    * scale;
        fat  += s.fat      * scale;
      });
    });
    return { cal: Math.round(cal), prot: Math.round(prot), carb: Math.round(carb), fat: Math.round(fat) };
  };

  if (isPlaceholder) return <PlaceholderState />;

  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
        <span className="text-zinc-300">/</span>
        <span className="font-bold text-zinc-650">NCP Cycle / Nutrition Intervention</span>
      </div>

      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Salad className="h-5 w-5 text-emerald-600" />
          Step 3: Nutrition Intervention
        </h2>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-zinc-200">
        {(['goals', 'mealplan'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)}
            className={`px-4 py-2 text-xs font-bold uppercase tracking-wider border-b-2 transition-colors cursor-pointer ${
              tab === t ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-zinc-400 hover:text-zinc-600'
            }`}>
            {t === 'goals' ? 'Intervention Goals' : 'Meal Plan'}
          </button>
        ))}
      </div>

      {tab === 'goals' && <InterventionGoalsTab />}

      {tab === 'mealplan' && (
        <div className="space-y-5">
          {/* Plan selector */}
          <div className="flex items-center justify-between flex-wrap gap-2">
            <div className="flex items-center gap-2 flex-wrap">
              {plans.map((p) => (
                <button key={p.id} onClick={() => setActivePlan(p)}
                  className={`px-3 py-1.5 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer ${
                    activePlan?.id === p.id
                      ? 'bg-emerald-600 text-white border-emerald-600'
                      : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-400'
                  }`}>
                  Week of {p.week_start_date}
                </button>
              ))}
              {loadingPlans && <Loader2 className="h-3.5 w-3.5 animate-spin text-zinc-400" />}
            </div>
            <Button variant="primary" loading={creatingPlan} onClick={handleCreatePlan} className="w-auto px-4 py-2 text-xs">
              <Plus className="h-3.5 w-3.5 mr-1" /> New Week Plan
            </Button>
          </div>

          {!activePlan && !loadingPlans && (
            <div className="bg-zinc-50 border border-zinc-200 rounded-2xl p-10 text-center">
              <p className="text-xs text-zinc-400">No meal plans yet. Create one above.</p>
            </div>
          )}

          {activePlan && (
            <>
              {/* Day pills */}
              <div className="flex gap-1.5 flex-wrap">
                {DAYS.map((d) => {
                  const t = dayTotals(d, activePlan);
                  return (
                    <button key={d} onClick={() => setSelectedDay(d)}
                      className={`px-3 py-2 rounded-xl text-[10px] font-bold border transition-colors cursor-pointer text-center ${
                        selectedDay === d
                          ? 'bg-emerald-600 text-white border-emerald-600'
                          : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-300'
                      }`}>
                      <span className="block">{d.slice(0, 3)}</span>
                      {t.cal > 0 && <span className="block font-normal opacity-80">{t.cal} kcal</span>}
                    </button>
                  );
                })}
              </div>

              {/* Daily totals bar */}
              {(() => {
                const t = dayTotals(selectedDay, activePlan);
                return t.cal > 0 ? (
                  <div className="flex gap-6 px-4 py-3 bg-emerald-50 border border-emerald-100 rounded-xl">
                    <Macro label="Energy" value={t.cal} unit="kcal" />
                    <Macro label="Protein" value={t.prot} unit="g" />
                    <Macro label="Carbs"   value={t.carb} unit="g" />
                    <Macro label="Fat"     value={t.fat}  unit="g" />
                  </div>
                ) : null;
              })()}

              {/* Meal slots */}
              <div className="space-y-3">
                {MEAL_TYPES.map((mt) => {
                  const day = dayForSlot(selectedDay, mt, activePlan);
                  if (!day) return null;
                  const key = slotKey(selectedDay, mt);
                  const items = itemsByKey[key] ?? [];
                  return (
                    <div key={mt} className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-sm space-y-2">
                      <div className="flex items-center justify-between">
                        <h4 className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">{MEAL_LABELS[mt]}</h4>
                        <button onClick={() => openPicker(day.id, mt)}
                          className="flex items-center gap-1 text-[10px] font-bold text-emerald-600 hover:text-emerald-800 cursor-pointer">
                          <Plus className="h-3 w-3" /> Add Food
                        </button>
                      </div>
                      {items.length === 0 && <p className="text-[10px] text-zinc-300 italic">No foods added</p>}
                      {items.map((item) => (
                        <MealItemRow key={item.id} item={item}
                          onRemove={() => removeItem(key, day.id, item.id)}
                          onSaveToLibrary={() => saveToLibrary(item)}
                          savingToLibrary={savingToLibrary === item.fdc_id}
                        />
                      ))}
                    </div>
                  );
                })}
              </div>
            </>
          )}
        </div>
      )}

      {/* Food Picker Modal */}
      {pickerOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[80vh]">
            <div className="flex items-center justify-between p-4 border-b border-zinc-100">
              <h3 className="text-sm font-extrabold text-zinc-900">Add Food</h3>
              <button onClick={() => setPickerOpen(false)} className="text-zinc-400 hover:text-zinc-700 cursor-pointer">
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Picker tabs */}
            <div className="flex gap-1 px-4 pt-3 flex-wrap">
              {([
                { key: 'library' as const,  label: 'Library',      Icon: Database },
                { key: 'recipes' as const,  label: 'Recipes',      Icon: Salad },
                { key: 'usda'    as const,  label: 'USDA Search',  Icon: Leaf },
              ]).map(({ key, label, Icon }) => (
                <button key={key} onClick={() => setPickerTab(key)}
                  className={`flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer ${
                    pickerTab === key ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-zinc-50 text-zinc-500 border-zinc-200'
                  }`}>
                  <Icon className="h-3 w-3" />{label}
                </button>
              ))}
            </div>

            <div className="p-4 space-y-3 overflow-y-auto flex-1">
              {pickerTab === 'library' && (
                <>
                  <SearchInput value={libraryQuery} onChange={searchLibraryFoods} placeholder="Search food library..." />
                  {pickerLoading && <Spinner />}
                  {libraryResults.map((food) => (
                    <PickerRow key={food.id}
                      name={food.name}
                      meta={`${food.calories} kcal · P ${food.protein ?? 0}g · C ${food.carbs ?? 0}g · F ${food.fat ?? 0}g`}
                      loading={adding === food.id}
                      onAdd={() => addFromLibrary(food)}
                    />
                  ))}
                  {libraryQuery.length >= 2 && !pickerLoading && libraryResults.length === 0 && <EmptyMsg text="No results in library." />}
                </>
              )}

              {pickerTab === 'recipes' && (
                <>
                  <SearchInput value={recipeQuery} onChange={searchRecipeFoods} placeholder="Search recipes..." />
                  {pickerLoading && <Spinner />}
                  {recipeResults.map((recipe) => (
                    <PickerRow key={recipe.id}
                      name={recipe.name}
                      meta={`${recipe.total_calories ?? 0} kcal · P ${recipe.total_protein ?? 0}g · C ${recipe.total_carbs ?? 0}g · F ${recipe.total_fat ?? 0}g${recipe.servings ? ` · ${recipe.servings} servings` : ''}`}
                      loading={adding === `recipe-${recipe.id}`}
                      onAdd={() => addFromRecipe(recipe)}
                    />
                  ))}
                  {recipeQuery.length >= 2 && !pickerLoading && recipeResults.length === 0 && <EmptyMsg text="No recipes found." />}
                </>
              )}

              {pickerTab === 'usda' && (
                <>
                  <SearchInput value={usdaQuery} onChange={searchUsdaFoods} placeholder="Search USDA database..." />
                  <p className="text-[9px] text-zinc-400">Foods added from USDA are not saved to your library unless you click the bookmark icon.</p>
                  {pickerLoading && <Spinner />}
                  {usdaResults.map((food) => (
                    <PickerRow key={food.fdc_id}
                      name={food.name}
                      meta={`${food.calories} kcal · P ${food.protein}g · C ${food.carbs}g · F ${food.fat}g`}
                      loading={adding === food.fdc_id}
                      onAdd={() => addFromUsda(food)}
                      addLabel="Add to Plan"
                    />
                  ))}
                  {usdaQuery.length >= 2 && !pickerLoading && usdaResults.length === 0 && <EmptyMsg text="No USDA results found." />}
                </>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Sub-components ─────────────────────────────────────────────────────────────

function MealItemRow({ item, onRemove, onSaveToLibrary, savingToLibrary }: {
  item: MealPlanItem;
  onRemove: () => void;
  onSaveToLibrary: () => void;
  savingToLibrary: boolean;
}) {
  const s = item.nutrient_snapshot;
  const scale = s && s.serving_size > 0 ? parseFloat(item.quantity) / s.serving_size : 1;
  return (
    <div className="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-zinc-50 group">
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-1.5 flex-wrap">
          <span className="text-xs font-medium text-zinc-800 truncate">{s?.name ?? 'Unknown food'}</span>
          {item.source === 'usda' && (
            <span className="flex-shrink-0 text-[8px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded-full uppercase tracking-wider">USDA</span>
          )}
          {item.source === 'recipe' && (
            <span className="flex-shrink-0 text-[8px] font-bold text-violet-600 bg-violet-50 border border-violet-200 px-1.5 py-0.5 rounded-full uppercase tracking-wider">Recipe</span>
          )}
        </div>
        {s && (
          <p className="text-[10px] text-zinc-400">
            {item.quantity}{item.unit} · {Math.round(s.calories * scale)} kcal · P {Math.round(s.protein * scale)}g · C {Math.round(s.carbs * scale)}g · F {Math.round(s.fat * scale)}g
          </p>
        )}
      </div>
      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
        {item.source === 'usda' && (
          <button onClick={onSaveToLibrary} disabled={savingToLibrary} title="Save to Library"
            className="p-1.5 rounded-lg text-zinc-400 hover:text-emerald-600 hover:bg-emerald-50 cursor-pointer transition-colors">
            {savingToLibrary ? <Loader2 className="h-3 w-3 animate-spin" /> : <BookmarkPlus className="h-3 w-3" />}
          </button>
        )}
        <button onClick={onRemove} title="Remove"
          className="p-1.5 rounded-lg text-zinc-400 hover:text-red-600 hover:bg-red-50 cursor-pointer transition-colors">
          <Trash2 className="h-3 w-3" />
        </button>
      </div>
    </div>
  );
}

function Macro({ label, value, unit }: { label: string; value: number; unit: string }) {
  return (
    <div>
      <p className="text-[9px] font-bold text-emerald-600 uppercase tracking-wider">{label}</p>
      <p className="text-sm font-extrabold text-zinc-900">{value}<span className="text-[9px] font-normal text-zinc-500 ml-0.5">{unit}</span></p>
    </div>
  );
}

function SearchInput({ value, onChange, placeholder }: { value: string; onChange: (v: string) => void; placeholder: string }) {
  return (
    <div className="relative">
      <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
      <input type="text" value={value} onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder} autoFocus
        className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
    </div>
  );
}

function PickerRow({ name, meta, loading, onAdd, addLabel = 'Add' }: {
  name: string; meta: string; loading: boolean; onAdd: () => void; addLabel?: string;
}) {
  return (
    <div className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
      <div className="flex-1 min-w-0 mr-3">
        <p className="text-xs font-semibold text-zinc-800 truncate">{name}</p>
        <p className="text-[10px] text-zinc-400">{meta}</p>
      </div>
      <Button variant="primary" loading={loading} onClick={onAdd} className="w-auto px-3 py-1.5 text-[10px] flex-shrink-0">
        {addLabel}
      </Button>
    </div>
  );
}

function Spinner() {
  return <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />;
}

function EmptyMsg({ text }: { text: string }) {
  return <p className="text-[10px] text-zinc-400 text-center py-2">{text}</p>;
}

function InterventionGoalsTab() {
  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div className="lg:col-span-2 bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm">
        <h3 className="text-sm font-bold text-zinc-900 uppercase tracking-wider mb-4 flex items-center gap-2">
          <Salad className="h-4 w-4 text-emerald-600" /> Intervention Formulation & Targets
        </h3>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-zinc-50 border border-zinc-200 rounded-xl text-center">
          {[['Energy Target', 'kcal'], ['Protein', 'g'], ['Carbs', 'g'], ['Fat', 'g']].map(([label, unit]) => (
            <div key={label} className="bg-white border border-zinc-200 p-2.5 rounded-lg">
              <span className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider block">{label}</span>
              <span className="text-sm font-extrabold text-zinc-800">-- {unit}</span>
            </div>
          ))}
        </div>
        <p className="text-xs text-zinc-400 mt-4">Intervention goal editor coming soon.</p>
      </div>
    </div>
  );
}

function PlaceholderState() {
  return (
    <div className="space-y-6 font-sans">
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Salad className="h-5 w-5 text-emerald-600 animate-pulse" /> Step 3: Nutrition Intervention
        </h2>
      </div>
      <div className="bg-white border border-zinc-250 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
          <User className="h-8 w-8" />
        </div>
        <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">No Patient Selected</h3>
        <p className="text-xs text-zinc-500 mt-2 leading-relaxed">Navigate to the NCP Patients directory and select a patient.</p>
        <div className="mt-6">
          <Link href="/ncp/patients"
            className="inline-flex px-4 py-2.5 bg-zinc-950 hover:bg-zinc-900 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors">
            Go to Patients Directory
          </Link>
        </div>
      </div>
    </div>
  );
}

function thisMonday(): string {
  const d = new Date();
  const day = d.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + diff);
  return d.toISOString().split('T')[0];
}
