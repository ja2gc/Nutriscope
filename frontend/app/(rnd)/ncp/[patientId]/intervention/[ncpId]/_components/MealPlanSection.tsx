"use client";

import { useEditorChanges } from "@/hooks/useUnsavedChanges";
import { MenuItemPicker } from "@/components/ncp/MenuItemPicker";
import React, { useEffect, useState, useCallback, useRef } from "react";
import {
  Plus, X, Loader2, Trash2, BookmarkPlus,
  Wand2, AlertTriangle, LayoutTemplate,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { InfoHint } from "@/components/ui/InfoHint";
import {
  fetchMealPlans, createMealPlan, fetchAllMealPlanItems, addMealPlanItem,
  removeMealPlanItem, updateMealPlanItem, clearMealPlan, generateMealPlan,
  fetchMealPlanTemplates, fetchMealPlanTemplate, deleteMealPlanTemplate,
  saveMealPlanAsTemplate, createPlanFromTemplate, scaleMealPlanToPrescription,
  MealPlan, MealPlanItem, MealPlanTemplate, MealPlanTemplateDetail, NutrientSnapshot,
} from "@/services/mealPlanService";
import {
  fetchRecipes, importUsdaFood, fetchRecipeById, fetchFoodItems,
  Recipe, RecipeIngredient, FoodItem,
} from "@/services/foodLibraryService";
import MacroTrackerBar from "./MacroTrackerBar";
import { WeeklyMenuCalendar, MENU_DAYS, MENU_SLOTS } from "@/components/ncp/WeeklyMenuCalendar";
import { MenuEditorSlots, MenuEditorActions, MenuEditorAction } from "@/components/ncp/MenuEditorSlots";
import { MenuItemProfile, RecipeMenuItemProfile } from "@/components/ncp/MenuItemProfile";
import { ALL_MICROS } from "@/lib/nutritionCalculations";
import { foodFluidBalance, formatMealPlanAmount } from "@/lib/mealPlanNutrition";
import { clinicalIngredientFactor, clinicalRecipeUnits } from "@/lib/clinicalUnits";
import NumericInput from '@/components/ui/NumericInput';

const DAYS = MENU_DAYS;
const MEAL_TYPES = MENU_SLOTS.map(([key]) => key);

interface Props {
  ncpId: string;
  interventionPlanId: string;
  onSaveIntervention?: () => Promise<string | null>;
  newlySavedInterventionId?: string | null;
  readOnly?: boolean;
  prescriptionTargets: { energy: number; protein: number; carbs: number; fat: number; fluid: number };
  foodDislikes?: string[];
  allergens?: string[];
  displayedMicros?: string[];
  micronutrientLimits?: Record<string, { target?: number; max?: number; min?: number; unit: string }>;
  nutrientOperators?: Record<string, 'min' | 'max'> | null;
  interventionGoal?: string | null;
}

interface EditTarget {
  item: MealPlanItem;
  dayId: string;
  slotKey: string;
}

const slotKey = (day: string, meal: string) => `${day}-${meal}`;

function groupWeekItems(plan: MealPlan, items: MealPlanItem[]): Record<string, MealPlanItem[]> {
  const result: Record<string, MealPlanItem[]> = {};
  for (const item of items) {
    const day = plan.days.find((slot) => slot.id === item.meal_plan_day_id);
    if (!day) continue;
    const key = slotKey(day.day_of_week, day.meal_type);
    (result[key] ??= []).push(item);
  }
  return result;
}

export default function MealPlanSection({
  ncpId, interventionPlanId, onSaveIntervention, newlySavedInterventionId, readOnly = false, prescriptionTargets, foodDislikes = [], allergens = [],
  displayedMicros = [], micronutrientLimits = {}, nutrientOperators = {},
}: Props) {
  const [, setPlans]                    = useState<MealPlan[]>([]);
  const scopeVersion = useRef(0);
  const [activePlan, setActivePlan]     = useState<MealPlan | null>(null);
  const [selectedDay, setSelectedDay]   = useState<string>(DAYS[0]);
  const [itemsByKey, setItemsByKey]     = useState<Record<string, MealPlanItem[]>>({});
  const [loadingPlans, setLoadingPlans] = useState(false);
  const [creatingPlan, setCreatingPlan] = useState(false);

  // Delete plan
  const [confirmDeleteId, setConfirmDeleteId] = useState<string | null>(null);
  const [deleting, setDeleting]               = useState(false);
  const [deleteError, setDeleteError]         = useState<string | null>(null);

  // Auto-generate
  const [generating, setGenerating]       = useState(false);
  const [generateError, setGenerateError] = useState<string | null>(null);
  const [excludeSnacks, setExcludeSnacks] = useState(false);
  const [excludeCarbs, setExcludeCarbs] = useState(false);
  const [carbChoiceOpen, setCarbChoiceOpen] = useState(false);
  const [selectedCarb, setSelectedCarb] = useState<Recipe | null>(null);
  const [carbChoices, setCarbChoices] = useState<Recipe[]>([]);
  const [carbQuery, setCarbQuery] = useState('');
  const [carbPage, setCarbPage] = useState(1);
  const [carbMeta, setCarbMeta] = useState<PaginationMeta | null>(null);
  const [loadingCarbs, setLoadingCarbs] = useState(false);

  useEffect(() => {
    if (!carbChoiceOpen) return;
    let cancelled = false;
    const timer = setTimeout(async () => {
      setLoadingCarbs(true);
      try {
        const result = await fetchRecipes(carbQuery, 'carb', carbPage);
        if (!cancelled) {
          setCarbChoices(result.data);
          setCarbMeta(result.meta ?? null);
          setSelectedCarb((current) => current ?? result.data[0] ?? null);
        }
      } catch (error) {
        if (!cancelled) setGenerateError(error instanceof Error ? error.message : 'Could not load Carb Menu Items. Try again.');
      } finally {
        if (!cancelled) setLoadingCarbs(false);
      }
    }, 160);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [carbChoiceOpen, carbPage, carbQuery]);

  // Micro display
  const [showMicros, setShowMicros] = useState(false);

  // Templates
  const [templates, setTemplates]                               = useState<MealPlanTemplate[]>([]);
  const [saveTemplateOpen, setSaveTemplateOpen]                 = useState(false);
  const [templateName, setTemplateName]                         = useState('');
  const [savingTemplate, setSavingTemplate]                     = useState(false);
  const [fromTemplateOpen, setFromTemplateOpen]                 = useState(false);
  const [viewingTemplate, setViewingTemplate]                   = useState<MealPlanTemplateDetail | null>(null);
  const [loadingTemplate, setLoadingTemplate]                   = useState<string | null>(null);
  const [templatePage, setTemplatePage]                         = useState(1);
  const [templateMeta, setTemplateMeta]                         = useState<PaginationMeta | null>(null);
  const [confirmDeleteTemplateId, setConfirmDeleteTemplateId]   = useState<string | null>(null);
  const [deletingTemplate, setDeletingTemplate]                 = useState(false);
  const [templateWarning, setTemplateWarning]                   = useState<string | null>(null);
  const [templateDraft, setTemplateDraft] = useState<{ templateId?: string; sourceMealPlanId?: string; replacePlanId?: string; originalPlan: MealPlan | null; originalItems: Record<string, MealPlanItem[]> } | null>(null);
  const draftRef = useRef(templateDraft);
  useEffect(() => { draftRef.current = templateDraft; }, [templateDraft]);
  const [savingWeek, setSavingWeek] = useState(false);
  const [pendingMenuAction, setPendingMenuAction] = useState<'create' | 'template' | 'generate' | 'save-week' | 'scale' | null>(null);
  const [savingIntervention, setSavingIntervention] = useState(false);
  const [loadedInterventionId, setLoadedInterventionId] = useState('');
  const [scalingPlan, setScalingPlan]                           = useState(false);
  const [scaleNotice, setScaleNotice]                           = useState<string | null>(null);

  // Edit meal item
  const [editTarget, setEditTarget]           = useState<EditTarget | null>(null);
  const editRequest = useRef(0);
  const [editServings, setEditServings]       = useState<string>('1');
  const [editRecipe, setEditRecipe]           = useState<Pick<Recipe, 'name' | 'servings' | 'ingredients'> | null>(null);
  const [editIngredients, setEditIngredients] = useState<{ id: number; qty: string; ing: RecipeIngredient; added?: boolean }[]>([]);
  const [editAmountDrafts, setEditAmountDrafts] = useState<Record<number, string>>({});
  const [editLoadingRecipe, setEditLoadingRecipe] = useState(false);
  const [editSaving, setEditSaving]           = useState(false);
  const [editError, setEditError] = useState<string | null>(null);
  const [editTab, setEditTab]                 = useState<'scale' | 'ingredients'>('scale');
  const [ingredientQuery, setIngredientQuery] = useState('');
  const [ingredientPickerOpen, setIngredientPickerOpen] = useState(false);
  const [ingredientLoading, setIngredientLoading] = useState(false);
  const [ingredientPage, setIngredientPage] = useState(1);
  const [ingredientMeta, setIngredientMeta] = useState<PaginationMeta | null>(null);
  const [ingredientResults, setIngredientResults] = useState<FoodItem[]>([]);
  const [ingredientSearchError, setIngredientSearchError] = useState<string | null>(null);
  useEffect(() => {
    if (!editTarget || editTab !== 'ingredients' || !ingredientPickerOpen) { setIngredientResults([]); return; }
    let cancelled = false;
    setIngredientLoading(true);
    setIngredientSearchError(null);
    const timer = setTimeout(() => { fetchFoodItems(ingredientQuery, undefined, ingredientPage).then((result) => { if (!cancelled) { setIngredientResults(result.data); setIngredientMeta(result.meta ?? null); } }).catch(() => { if (!cancelled) setIngredientSearchError('Could not load Ingredients. Try again.'); }).finally(() => { if (!cancelled) setIngredientLoading(false); }); }, 200);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [ingredientQuery, editTarget, editTab, ingredientPickerOpen, ingredientPage]);

  // Food picker
  const [pickerOpen, setPickerOpen]           = useState(false);
  const [pickerTarget, setPickerTarget]       = useState<{ dayId: string; mealType: string } | null>(null);
  const [recipeQuery, setRecipeQuery]         = useState('');
  const [recipeResults, setRecipeResults]     = useState<Recipe[]>([]);
  const [recipePage, setRecipePage]           = useState(1);
  const [recipeMeta, setRecipeMeta]           = useState<PaginationMeta | null>(null);
  const [pickerLoading, setPickerLoading]     = useState(false);
  const [adding, setAdding]                   = useState<number | string | null>(null);
  const [pickerError, setPickerError]         = useState<string | null>(null);
  const [savingToLibrary, setSavingToLibrary] = useState<string | null>(null);
  const [templateProfile, setTemplateProfile] = useState<{ id: string; name: string; snapshot?: MealPlanItem["nutrient_snapshot"] } | null>(null);
  useEffect(() => {
    if (!pickerOpen) return;
    let cancelled = false;
    const timer = setTimeout(async () => {
      setPickerLoading(true);
      setPickerError(null);
      try {
        const result = await fetchRecipes(recipeQuery, 'all', recipePage);
        if (!cancelled) { setRecipeResults(result.data); setRecipeMeta(result.meta ?? null); }
      } catch (error) {
        if (!cancelled) setPickerError(error instanceof Error ? error.message : 'Could not load Menu Items. Try again.');
      } finally {
        if (!cancelled) setPickerLoading(false);
      }
    }, 160);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [pickerOpen, recipePage, recipeQuery]);

  const markPlanNeedsScaling = (planId: string) => {
    setPlans((current) => current.map((plan) => plan.id === planId
      ? { ...plan, scale_status: 'available', scaled_at: null }
      : plan));
    setActivePlan((current) => current?.id === planId
      ? { ...current, scale_status: 'available', scaled_at: null }
      : current);
  };

  const loadPlans = useCallback(async () => {
    const version = scopeVersion.current;
    setLoadingPlans(true);
    try {
      const data = await fetchMealPlans(ncpId);
      if (version !== scopeVersion.current) return;
      if (!interventionPlanId) {
        setPlans([]); setLoadedInterventionId('');
        const source = data[0];
        if (!source) { setActivePlan(null); return; }
        const sourceItems = await fetchAllMealPlanItems(ncpId, source.id);
        if (version !== scopeVersion.current) return;
        const dayIds = new Map(source.days.map((day) => [day.id, crypto.randomUUID()]));
        const draft: MealPlan = {
          ...source, id: crypto.randomUUID(), intervention_plan_id: '', status: 'draft',
          scale_status: 'available', scaled_at: null,
          days: source.days.map((day) => ({ ...day, id: dayIds.get(day.id)! })),
        };
        const items = sourceItems.map((item) => ({
          ...item, id: crypto.randomUUID(), meal_plan_day_id: dayIds.get(item.meal_plan_day_id)!,
          source_meal_plan_item_id: item.id,
        }));
        setTemplateDraft({ sourceMealPlanId: source.id, originalPlan: null, originalItems: {} });
        setActivePlan(draft); setItemsByKey(groupWeekItems(draft, items));
        return;
      }
      const ownedPlans = data.filter((plan) => plan.intervention_plan_id === interventionPlanId);
      setPlans(ownedPlans);
      if (newlySavedInterventionId === interventionPlanId && draftRef.current) {
        setActivePlan((current) => current ? { ...current, intervention_plan_id: interventionPlanId } : current);
      } else {
        setActivePlan(ownedPlans[0] ?? null);
      }
      setLoadedInterventionId(interventionPlanId);
    } catch (error) {
      if (version === scopeVersion.current) setGenerateError(error instanceof Error ? error.message : 'Could not load this weekly plan.');
    } finally { if (version === scopeVersion.current) setLoadingPlans(false); }
  }, [interventionPlanId, ncpId, newlySavedInterventionId]);

  useEffect(() => {
    const version = ++scopeVersion.current;
    if (!interventionPlanId || newlySavedInterventionId !== interventionPlanId || !draftRef.current) {
      setTemplateDraft(null); setActivePlan(null); setItemsByKey({});
    }
    setFromTemplateOpen(false); setCarbChoiceOpen(false);
    setCreatingPlan(false); setSavingWeek(false); setScalingPlan(false);
    if (interventionPlanId && newlySavedInterventionId !== interventionPlanId) setPendingMenuAction(null);
    void loadPlans();
    return () => { scopeVersion.current = version + 1; };
  }, [loadPlans, interventionPlanId, newlySavedInterventionId]);

  useEffect(() => {
    let cancelled = false;
    fetchMealPlanTemplates(templatePage).then((result) => {
      if (!cancelled) { setTemplates(result.data); setTemplateMeta(result.meta); }
    }).catch((error) => { if (!cancelled) setGenerateError(error instanceof Error ? error.message : 'Could not load templates.'); });
    return () => { cancelled = true; };
  }, [templatePage]);

  useEffect(() => {
    if (!activePlan || templateDraft) return;
    let cancelled = false;
    fetchAllMealPlanItems(ncpId, activePlan.id).then((items) => {
      if (!cancelled) setItemsByKey(groupWeekItems(activePlan, items));
    }).catch((error) => {
      if (!cancelled) setGenerateError(error instanceof Error ? error.message : 'Failed to load meal items. Please refresh.');
    });
    return () => { cancelled = true; };
  }, [activePlan, ncpId, templateDraft]);

  const handleCreatePlan = useCallback(async (savedInterventionId = interventionPlanId) => {
    if (!savedInterventionId) return;
    const version = scopeVersion.current;
    setCreatingPlan(true);
    try {
      const d = new Date(); const day = d.getDay();
      d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
      const plan = await createMealPlan(ncpId, {
        intervention_plan_id: savedInterventionId,
        week_start_date: d.toISOString().split('T')[0],
        generation_type: 'manual',
      });
      if (version !== scopeVersion.current) return;
      setPlans([plan]);
      setActivePlan(plan);
    } finally { if (version === scopeVersion.current) setCreatingPlan(false); }
  }, [interventionPlanId, ncpId]);

  const handleGenerate = useCallback(async (carb?: Recipe, savedInterventionId = interventionPlanId) => {
    if (!savedInterventionId) return;
    const version = scopeVersion.current;
    const missingTargets = [
      ['energy', prescriptionTargets.energy],
      ['protein', prescriptionTargets.protein],
      ['carbs', prescriptionTargets.carbs],
      ['fat', prescriptionTargets.fat],
    ].filter(([, value]) => !Number.isFinite(Number(value)) || Number(value) <= 0).map(([label]) => label);

    if (missingTargets.length > 0) {
      setGenerateError(`Complete nutrition prescription before generating. Missing: ${missingTargets.join(', ')}.`);
      return;
    }

    if (!excludeCarbs && !carb) {
      setGenerateError(null);
      setCarbChoiceOpen(true);
      return;
    }

    setGenerating(true);
    setGenerateError(null);
    try {
      const d = new Date(); const day = d.getDay();
      d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
      const result = await generateMealPlan(ncpId, {
        replace: true,
        intervention_plan_id: savedInterventionId,
        week_start_date: d.toISOString().split('T')[0],
        allergens: allergens.length > 0 ? allergens : undefined,
        exclude_snacks: excludeSnacks,
        ...(excludeCarbs ? { exclude_carbs: true as const } : { exclude_carbs: false as const, carb_menu_item_id: carb!.id }),
      });
      if (version !== scopeVersion.current) return;
      if ('insufficient_recipes' in result || 'insufficient_suitable_foods' in result) {
        setGenerateError(result.message);
        return;
      }
      setPlans([result]);
      setTemplateDraft(null);
      setActivePlan(result);
      setCarbChoiceOpen(false);
    } catch (err) {
      if (version === scopeVersion.current) setGenerateError(err instanceof Error ? err.message : 'Failed to generate meal plan. Please try again.');
    } finally { if (version === scopeVersion.current) setGenerating(false); }
  }, [allergens, excludeCarbs, excludeSnacks, interventionPlanId, ncpId, prescriptionTargets]);

  const handleSaveTemplate = async () => {
    if (!activePlan || !templateName.trim()) return;
    setSavingTemplate(true);
    try {
      const saved = await saveMealPlanAsTemplate(ncpId, activePlan.id, { name: templateName.trim() });
      setTemplates((prev) => [saved, ...prev]);
      setSaveTemplateOpen(false); setTemplateName('');
    } finally { setSavingTemplate(false); }
  };

  const templateRequest = useCallback((scale = false, savedInterventionId = interventionPlanId) => {
    if (!templateDraft || !activePlan) throw new Error('Load a template before saving this draft.');
    return {
      intervention_plan_id: savedInterventionId, template_id: templateDraft.templateId, source_meal_plan_id: templateDraft.sourceMealPlanId,
      meal_plan_id: templateDraft.replacePlanId, week_start_date: activePlan.week_start_date, scale,
      lines: activePlan.days.flatMap((day) => (itemsByKey[slotKey(day.day_of_week, day.meal_type)] ?? []).map((item) => ({
        source_template_item_id: item.source_template_item_id, source_meal_plan_item_id: item.source_meal_plan_item_id, recipe_id: item.recipe_id,
        day_of_week: day.day_of_week, meal_type: day.meal_type, quantity: Number(item.quantity), unit: item.unit,
        ingredient_overrides: item.ingredient_overrides,
        ingredients: item.ingredients,
      }))),
    };
  }, [templateDraft, activePlan, itemsByKey, interventionPlanId]);

  const handleSaveWeek = useCallback(async (savedInterventionId = interventionPlanId) => {
    if (!templateDraft || !activePlan) return;
    if (!savedInterventionId) { setPendingMenuAction('save-week'); return; }
    const version = scopeVersion.current;
    setSavingWeek(true); setGenerateError(null);
    try {
      const result = await createPlanFromTemplate(ncpId, { ...templateRequest(false, savedInterventionId), preview: false });
      if (version !== scopeVersion.current) return;
      setTemplateDraft(null); setPlans([result.plan]); setActivePlan(result.plan);
      setItemsByKey(groupWeekItems(result.plan, result.items));
    } catch (error) {
      if (version === scopeVersion.current) setGenerateError(error instanceof Error ? error.message : 'Could not save week. Try again.');
    } finally { if (version === scopeVersion.current) setSavingWeek(false); }
  }, [templateDraft, activePlan, interventionPlanId, ncpId, templateRequest]);

  const handleScaleToPrescription = useCallback(async () => {
    if (!activePlan || activePlan.scale_status === 'already_scaled') return;
    if (!interventionPlanId) { setPendingMenuAction('scale'); return; }
    const version = scopeVersion.current;
    setScalingPlan(true); setScaleNotice(null);
    try {
      if (templateDraft) {
        const result = await createPlanFromTemplate(ncpId, { ...templateRequest(true), preview: true });
        if (version !== scopeVersion.current) return;
        setActivePlan(result.plan); setItemsByKey(groupWeekItems(result.plan, result.items));
        setScaleNotice('Quantities scaled. Review the week against the prescription.');
        return;
      }
      const { plan, scaling } = await scaleMealPlanToPrescription(ncpId, activePlan.id);
      if (version !== scopeVersion.current) return;
      setPlans((current) => current.map((item) => item.id === plan.id ? plan : item));
      setActivePlan(plan);
      setScaleNotice(scaling.problem_days.length > 0
        ? `Quantities were scaled. Review unresolved tolerances for: ${scaling.problem_days.join(', ')}.`
        : `Scaled ${scaling.changed_items} item quantities to the current prescription.`);
    } catch (err) {
      if (version === scopeVersion.current) setScaleNotice(err instanceof Error ? err.message : 'Failed to scale meal plan.');
    } finally { if (version === scopeVersion.current) setScalingPlan(false); }
  }, [activePlan, templateDraft, interventionPlanId, ncpId, templateRequest]);

  const performMenuAction = useCallback(async (action: 'create' | 'template' | 'generate' | 'save-week' | 'scale', savedInterventionId = interventionPlanId) => {
    if (!savedInterventionId) {
      setPendingMenuAction(action);
      setSavingIntervention(true);
      try { await onSaveIntervention?.(); }
      catch (error) { setGenerateError(error instanceof Error ? error.message : 'Could not save intervention.'); }
      finally { setSavingIntervention(false); }
      return;
    }
    if (action === 'create') await handleCreatePlan(savedInterventionId);
    else if (action === 'template') setFromTemplateOpen(true);
    else if (action === 'save-week') await handleSaveWeek(savedInterventionId);
    else if (action === 'scale') await handleScaleToPrescription();
    else await handleGenerate(undefined, savedInterventionId);
  }, [handleCreatePlan, handleGenerate, handleSaveWeek, handleScaleToPrescription, interventionPlanId, onSaveIntervention]);

  useEffect(() => {
    if (!pendingMenuAction || !interventionPlanId || newlySavedInterventionId !== interventionPlanId || loadedInterventionId !== interventionPlanId || readOnly) return;
    setPendingMenuAction(null);
    void performMenuAction(pendingMenuAction).catch((error) => setGenerateError(error instanceof Error ? error.message : 'Could not continue. Try again.'));
  }, [pendingMenuAction, interventionPlanId, newlySavedInterventionId, loadedInterventionId, readOnly, performMenuAction]);

  useEffect(() => {
    if (templateDraft && interventionPlanId && loadedInterventionId === interventionPlanId && !pendingMenuAction && !savingWeek && !generateError && !readOnly) void handleSaveWeek();
  }, [templateDraft, interventionPlanId, loadedInterventionId, pendingMenuAction, savingWeek, generateError, readOnly, handleSaveWeek]);


  const handleFromTemplate = async (templateId: string) => {
    const version = scopeVersion.current;
    setCreatingPlan(true); setGenerateError(null);
    try {
      const d = new Date(); d.setDate(d.getDate() + (d.getDay() === 0 ? -6 : 1 - d.getDay()));
      const result = await createPlanFromTemplate(ncpId, {
        intervention_plan_id: interventionPlanId, template_id: templateId,
        week_start_date: activePlan?.week_start_date ?? d.toISOString().split('T')[0], preview: false,
        meal_plan_id: templateDraft?.replacePlanId ?? (templateDraft ? undefined : activePlan?.id),
      });
      if (version !== scopeVersion.current) return;
      setTemplateDraft(null); setPlans([result.plan]);
      setActivePlan(result.plan); setItemsByKey(groupWeekItems(result.plan, result.items));
      setTemplateWarning(result.compatibility.warning);
      setFromTemplateOpen(false); setViewingTemplate(null); setScaleNotice(null);
    } catch (error) {
      if (version === scopeVersion.current) setGenerateError(error instanceof Error ? error.message : 'Could not load template. Try again.');
    } finally { if (version === scopeVersion.current) setCreatingPlan(false); }
  };


  const handleViewTemplate = async (templateId: string) => {
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
      if (templateDraft) { setTemplateDraft(null); setActivePlan(null); setPlans([]); }
      else {
        const cleared = await clearMealPlan(ncpId, confirmDeleteId);
        setPlans([cleared]); setActivePlan(cleared);
      }
      setItemsByKey({}); setGenerateError(null); setTemplateWarning(null);
      setConfirmDeleteId(null);
    } catch (err) {
      setDeleteError(err instanceof Error ? err.message : 'Failed to delete plan. Please try again.');
    } finally { setDeleting(false); }
  };


  // ── Edit modal ─────────────────────────────────────────────────────────────

  const openEdit = (item: MealPlanItem, dayId: string, key: string) => {
    const request = ++editRequest.current;
    setEditTarget({ item, dayId, slotKey: key });
    setEditError(null);
    setEditServings(item.quantity ?? '1');
    setEditTab('scale');
    setEditRecipe(null);
    setEditIngredients([]);
    setEditAmountDrafts({});
    setIngredientQuery(''); setIngredientResults([]); setIngredientSearchError(null); setIngredientPickerOpen(false);
    const snapshot = item.nutrient_snapshot;
    if (snapshot?.recipe_yield && snapshot.recipe_original_ingredients?.length && snapshot.recipe_original_ingredients.every((ingredient) => ingredient.id && ingredient.food_item)) {
      const ingredients = snapshot.recipe_original_ingredients.map((ingredient) => ({
        ...ingredient, quantity: String(snapshot.recipe_ingredients?.find((planned) => planned.id === ingredient.id)?.quantity ?? ingredient.quantity),
      }));
      setEditRecipe({ name: snapshot.name, servings: snapshot.recipe_yield, ingredients });
      setEditIngredients(ingredients.map((ingredient) => ({ id: ingredient.id, qty: ingredient.quantity, ing: ingredient })));
      setEditLoadingRecipe(false);
      return;
    }
    if (readOnly) {
      setEditLoadingRecipe(false);
      return;
    }
    if (item.recipe_id) {
      setEditLoadingRecipe(true);
      fetchRecipeById(item.recipe_id).then((recipe) => {
        if (request !== editRequest.current) return;
        setEditRecipe(recipe);
        setEditIngredients(recipe.ingredients.map((ingredient) => ({
          id: ingredient.id, qty: String(item.ingredient_overrides?.find((row) => row.id === ingredient.id)?.quantity ?? ingredient.quantity), ing: ingredient,
        })));
      }).catch((cause) => { if (request === editRequest.current) setEditError(cause instanceof Error ? cause.message : 'Could not load ingredients. Try again.'); }).finally(() => { if (request === editRequest.current) setEditLoadingRecipe(false); });
    }
  };

  // Existing rows store whole-recipe amounts; additions are entered for this planned portion.
  const ingredientBatchQuantity = (row: (typeof editIngredients)[number]) => row.added
    ? Number((Number(row.qty) * (editRecipe?.servings ?? 1) / (Number(editServings) || 1)).toFixed(6))
    : Number(row.qty);

  const recalcFromIngredients = (): Partial<NutrientSnapshot> => {
    if (!editRecipe) return {};
    const servings = editRecipe.servings ?? 1;
    let cal = 0, prot = 0, carb = 0, fat = 0, water = 0;
    const micros: Record<string, number> = {};
    for (const row of editIngredients) {
      const { ing } = row;
      const qty = ingredientBatchQuantity(row);
      const food = ing.food_item;
      if (!food) continue;
      const factor = clinicalIngredientFactor(Number(qty), ing.unit, Number(food.serving_size), food.serving_unit ?? '');
      if (factor === null) continue;
      cal += parseFloat(food.calories || '0') * factor;
      prot += parseFloat(food.protein ?? '0') * factor;
      carb += parseFloat(food.carbs ?? '0') * factor;
      fat += parseFloat(food.fat ?? '0') * factor;
      water += Number(food.water_g ?? 0) * factor;
      for (const [key, value] of Object.entries(food.micronutrients ?? {})) {
        micros[key] = (micros[key] ?? 0) + value * factor;
      }
    }
    return {
      name: editTarget?.item.nutrient_snapshot?.name ?? editRecipe.name,
      calories: Math.round(cal / servings * 100) / 100,
      protein: Math.round(prot / servings * 100) / 100,
      carbs: Math.round(carb / servings * 100) / 100,
      fat: Math.round(fat / servings * 100) / 100,
      water_g: Math.round(water / servings * 100) / 100,
      serving_size: 1,
      serving_unit: 'serving',
      recipe_yield: servings,
      recipe_original_ingredients: editIngredients.map((row) => ({ ...row.ing, id: row.id, quantity: ingredientBatchQuantity(row), name: row.ing.food_item?.name ?? 'Ingredient' })),
      recipe_ingredients: editIngredients.map((row) => ({ id: row.id, name: row.ing.food_item?.name ?? 'Ingredient', quantity: ingredientBatchQuantity(row), unit: row.ing.unit })),
      micronutrients: Object.fromEntries(Object.entries(micros).map(([key, value]) => [key, Math.round(value / servings * 1000) / 1000])),
    };
  };

  const editValid = Number(editServings) >= 0.01 && Number(editServings) <= 100000
    && (editTab !== 'ingredients' || editIngredients.length > 0)
    && editIngredients.every((row) => Number(row.qty) > 0 && Number(row.qty) <= 100000
      && clinicalIngredientFactor(Number(row.qty), row.ing.unit, Number(row.ing.food_item?.serving_size), row.ing.food_item?.serving_unit ?? '') !== null);

  const handleSaveEdit = async () => {
    if (!editTarget || !activePlan) return false;
    if (!editValid || editLoadingRecipe) {
      setEditError('Enter a valid planned portion and positive ingredient quantities in compatible units.');
      return false;
    }
    setEditSaving(true);
    try {
      const payload: { quantity?: number; ingredients?: { food_item_id: string; quantity: number; unit: string }[] } = {};
      if (editTab === 'scale') {
        payload.quantity = Number(editServings);
      }
      if (editTab === 'ingredients' || editIngredients.some((row) => Number(row.qty) !== Number(row.ing.quantity))) {
        payload.ingredients = editIngredients.map((row) => ({ food_item_id: row.ing.food_item!.id, quantity: ingredientBatchQuantity(row), unit: row.ing.unit }));
        payload.quantity = Number(editServings);
      }
      if (templateDraft) {
        const snapshot = payload.ingredients ? { ...editTarget.item.nutrient_snapshot!, ...recalcFromIngredients() } : editTarget.item.nutrient_snapshot;
        const updated: MealPlanItem = {
          ...editTarget.item, quantity: String(payload.quantity ?? editTarget.item.quantity),
          ingredients: payload.ingredients ?? editTarget.item.ingredients,
          nutrient_snapshot: snapshot,
          recipe_portion: snapshot?.recipe_yield && snapshot.recipe_ingredients ? { yield: snapshot.recipe_yield, ingredients: snapshot.recipe_ingredients } : editTarget.item.recipe_portion,
        };
        setItemsByKey((previous) => ({ ...previous, [editTarget.slotKey]: (previous[editTarget.slotKey] ?? []).map((item) => item.id === updated.id ? updated : item) }));
        markPlanNeedsScaling(activePlan.id); setEditTarget(null);
        return true;
      }
      const updated = await updateMealPlanItem(ncpId, activePlan.id, editTarget.dayId, editTarget.item.id, payload);
      setItemsByKey((prev) => ({
        ...prev,
        [editTarget.slotKey]: (prev[editTarget.slotKey] ?? []).map((i) => i.id === updated.id ? updated : i),
      }));
      markPlanNeedsScaling(activePlan.id);
      setEditTarget(null);
      return true;
    } catch (cause) {
      setEditError(cause instanceof Error ? cause.message : 'Could not save this portion. Try again.');
      return false;
    } finally { setEditSaving(false); }
  };

  const editGuard = useEditorChanges(JSON.stringify({ editServings, ingredients: editIngredients.map(({ id, qty, ing }) => ({ id, qty, unit: ing.unit })) }), Boolean(editTarget && !editLoadingRecipe && !readOnly), handleSaveEdit);

  // ── Picker helpers ──────────────────────────────────────────────────────────

  const openPicker = (dayId: string, mealType: string) => {
    setPickerTarget({ dayId, mealType }); setPickerLoading(true); setPickerOpen(true);
    setRecipeQuery(''); setRecipeResults([]); setRecipePage(1); setRecipeMeta(null); setPickerError(null);
  };

  const appendItem = (item: MealPlanItem, plan: MealPlan, dayId: string) => {
    const day = plan.days.find((d) => d.id === dayId); if (!day) return;
    const key = slotKey(day.day_of_week, day.meal_type);
    setItemsByKey((prev) => ({ ...prev, [key]: [...(prev[key] ?? []), item] }));
    markPlanNeedsScaling(plan.id);
  };

  const addFromRecipe = async (recipe: Recipe) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(`recipe-${recipe.id}`); setPickerError(null);
    try {
      if (templateDraft) {
        const yieldAmount = Number(recipe.servings);
        if (!(yieldAmount > 0)) throw new Error('Correct the Menu Item yield before adding it.');
        const item: MealPlanItem = {
          id: crypto.randomUUID(), meal_plan_day_id: pickerTarget.dayId, recipe_id: recipe.id,
          food_item_id: null, fdc_id: null, quantity: '1.00', unit: 'serving', source: 'recipe', ai_suggested: false,
          nutrient_snapshot: {
            name: recipe.name, calories: Number(recipe.total_calories) / yieldAmount,
            protein: Number(recipe.total_protein) / yieldAmount, carbs: Number(recipe.total_carbs) / yieldAmount,
            fat: Number(recipe.total_fat) / yieldAmount, serving_size: 1, serving_unit: 'serving',
            water_g: Number(recipe.total_water_g ?? 0) / yieldAmount,
            micronutrients: Object.fromEntries(Object.entries(recipe.micronutrients ?? {}).map(([key, value]) => [key, Number(value) / yieldAmount])),
          },
        };
        appendItem(item, activePlan, pickerTarget.dayId);
        return;
      }
      appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, {
        recipe_id: recipe.id,
        quantity: 1,
        unit: 'serving',
      }), activePlan, pickerTarget.dayId);
    } catch (err) {
      setPickerError(err instanceof Error ? err.message : 'Failed to add Menu Item. Please try again.');
    } finally { setAdding(null); }
  };
  const removeItem = async (key: string, dayId: string, itemId: string) => {
    if (!activePlan) return;
    if (!templateDraft) await removeMealPlanItem(ncpId, activePlan.id, dayId, itemId);
    setItemsByKey((prev) => ({ ...prev, [key]: (prev[key] ?? []).filter((i) => i.id !== itemId) }));
    markPlanNeedsScaling(activePlan.id);
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
  const selectedItems = MEAL_TYPES.flatMap((mealType) => itemsByKey[slotKey(selectedDay, mealType)] ?? []);
  const selectedFluid = foodFluidBalance(selectedItems, t.fluid);
  const trackerTargets = [
    { label: 'Energy',  current: selectedTotals.cal,   target: t.energy,        unit: 'kcal', operator: nutrientOperators?.energy_kcal },
    { label: 'Protein', current: selectedTotals.prot,  target: t.protein,       unit: 'g', operator: nutrientOperators?.protein_g },
    { label: 'Carbs',   current: selectedTotals.carb,  target: t.carbs,         unit: 'g', operator: nutrientOperators?.carbs_g },
    { label: 'Fat',     current: selectedTotals.fat,   target: t.fat,           unit: 'g', operator: nutrientOperators?.fat_g },
  ];

  if (templateProfile) return <RecipeMenuItemProfile id={templateProfile.id} name={templateProfile.name} snapshot={templateProfile.snapshot} onBack={() => setTemplateProfile(null)} />;

  if (editTarget) {
    const snapshot = editRecipe && editIngredients.length > 0 ? recalcFromIngredients() : editTarget.item.nutrient_snapshot;
    const portion = Number(editServings);
    const factor = portion / (Number(editRecipe?.servings) || 1);
    const nutrientFactor = portion / (Number(snapshot?.serving_size) || 1);
    return <MenuItemProfile
      name={editTarget.item.nutrient_snapshot?.name ?? 'Menu Item'}
      servings={editRecipe?.servings}
      prepNotes={editTarget.item.nutrient_snapshot?.prep_notes ?? (editRecipe && 'prep_notes' in editRecipe ? String(editRecipe.prep_notes ?? '') : null)}
      onBack={() => editGuard.requestLeave(() => setEditTarget(null))} backLabel="Back to menu plan" nutritionBasis="Planned portion"
      ingredients={editIngredients.map((row) => ({ id: row.id, name: row.ing.food_item?.name ?? 'Ingredient', quantity: row.qty === '' ? '' : Number(row.qty) * (row.added ? 1 : factor), unit: row.ing.unit }))}
      nutrients={{
        calories: Number(snapshot?.calories ?? 0) * nutrientFactor,
        protein: Number(snapshot?.protein ?? 0) * nutrientFactor,
        carbs: Number(snapshot?.carbs ?? 0) * nutrientFactor,
        fat: Number(snapshot?.fat ?? 0) * nutrientFactor,
        water_g: Number(snapshot?.water_g ?? 0) * nutrientFactor,
        micronutrients: Object.fromEntries(Object.entries(snapshot?.micronutrients ?? {}).map(([key, value]) => [key, value * nutrientFactor])),
      }}
      controls={<div className="space-y-3">
        <p className="text-xs text-warm-500">Changes affect this plan only. Ingredient amounts and nutrients below are for the planned portion.</p>
        <div className="max-w-xs"><NumericInput label="Planned portion" min={0} max={100000} step={0.1} value={editServings} onChange={(value) => { setEditServings(value); setEditAmountDrafts({}); }} disabled={readOnly} unit={editTarget.item.unit} /></div>
        {!readOnly && <div className="flex gap-2"><Button variant={editTab === 'scale' ? 'secondary' : 'ghost'} size="sm" onClick={() => setEditTab('scale')}>Portion</Button><Button variant={editTab === 'ingredients' ? 'secondary' : 'ghost'} size="sm" onClick={() => setEditTab('ingredients')}>Ingredients</Button></div>}
        {editLoadingRecipe && <p role="status" className="text-sm text-warm-500">Loading ingredients…</p>}
        {editError && <p role="alert" className="text-sm text-red-700">{editError}</p>}
      </div>}
      ingredientControls={!readOnly && editTab === 'ingredients' ? <div className="space-y-2">
        <Button type="button" variant="secondary" size="sm" onClick={() => { setIngredientQuery(''); setIngredientPage(1); setIngredientPickerOpen(true); }}><Plus className="h-3 w-3" />Add Ingredient</Button>
        {ingredientPickerOpen && <MenuItemPicker title="Add Ingredient" itemLabel="Ingredient" query={ingredientQuery} onQueryChange={(query) => { setIngredientQuery(query); setIngredientPage(1); }} loading={ingredientLoading} error={ingredientSearchError} meta={ingredientMeta} page={ingredientPage} onPageChange={setIngredientPage} items={ingredientResults.map((food) => ({ id: food.id, name: food.name }))} onClose={() => setIngredientPickerOpen(false)} onSelect={(foodId) => {
          const food = ingredientResults.find((item) => item.id === foodId);
          if (!food) return;
          const id = Math.min(0, ...editIngredients.map((row) => row.id)) - 1;
          setEditIngredients((previous) => [...previous, { id, qty: '', added: true, ing: { id, food_item_id: 0, quantity: '', unit: food.serving_unit ?? '', food_item: food } }]);
          setIngredientQuery(''); setIngredientResults([]); setIngredientPickerOpen(false);
        }} />}
      </div> : undefined}
      renderIngredientAmount={!readOnly && editTab === 'ingredients' ? (ingredient) => <div className="flex flex-wrap items-center gap-2 text-sm text-warm-600"><input aria-label={`Amount of ${ingredient.name}`} type="number" min="0" step="0.1" value={editAmountDrafts[ingredient.id] ?? (ingredient.quantity === '' ? '' : Number(Number(ingredient.quantity).toPrecision(12)))} onChange={(event) => {
        if (!(factor > 0)) return;
        setEditAmountDrafts((previous) => ({ ...previous, [ingredient.id]: event.target.value }));
        const row = editIngredients.find((item) => item.id === ingredient.id);
        const amount = event.target.value === '' ? '' : String(Number((Number(event.target.value) / (row?.added ? 1 : factor)).toFixed(6)));
        setEditIngredients((previous) => previous.map((row) => row.id === ingredient.id ? { ...row, qty: amount } : row));
      }} className="w-28 rounded-lg border border-warm-200 px-2 py-2 text-right text-base" />
        <select aria-label={`Unit of ${ingredient.name}`} value={ingredient.unit} onChange={(event) => { setEditIngredients((previous) => previous.map((row) => row.id === ingredient.id ? { ...row, ing: { ...row.ing, unit: event.target.value } } : row)); setEditAmountDrafts({}); }} className="rounded-lg border border-warm-200 px-2 py-2 text-base">{clinicalRecipeUnits(editIngredients.find((row) => row.id === ingredient.id)?.ing.food_item?.serving_unit ?? ingredient.unit).map((unit) => <option key={unit}>{unit}</option>)}</select>
        <Button variant="icon" aria-label={`Remove ${ingredient.name}`} onClick={() => setEditIngredients((previous) => previous.filter((row) => row.id !== ingredient.id))}><Trash2 className="h-4 w-4" /></Button>
      </div> : undefined}
      actions={!readOnly ? <>
        {editGuard.dialog}
        {editTarget.item.source === 'usda' && editTarget.item.fdc_id && <Button variant="secondary" loading={savingToLibrary === editTarget.item.fdc_id} onClick={() => { const fdcId = editTarget.item.fdc_id!; setSavingToLibrary(fdcId); importUsdaFood(Number(fdcId)).catch((cause) => setEditError(cause instanceof Error ? cause.message : 'Could not save to library.')).finally(() => setSavingToLibrary(null)); }}>Save to Library</Button>}
        <Button variant="secondary" onClick={() => editGuard.requestLeave(() => setEditTarget(null))}>Cancel</Button><Button variant="primary" loading={editSaving} disabled={editLoadingRecipe || !editValid} onClick={() => void handleSaveEdit()}>Save Changes</Button>
      </> : undefined}
    />;
  }

  return (
    <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-2">
        <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Weekly Meal Plan</h3>
        <div className="flex min-w-0 flex-wrap items-center justify-end gap-2">
          {!readOnly && (
            <Button variant="secondary" size="sm" disabled={generating || creatingPlan || savingWeek || savingIntervention} onClick={() => void performMenuAction('template')}>
              <LayoutTemplate className="h-3 w-3" /> Load Template
            </Button>
          )}
          {!readOnly && activePlan && !templateDraft && (
            <Button variant="secondary" size="sm" onClick={() => setSaveTemplateOpen(true)}>
              <BookmarkPlus className="h-3 w-3" /> Save Template
            </Button>
          )}
          {!readOnly && activePlan && <Button variant="secondary" size="sm" disabled={savingWeek || generating || creatingPlan} onClick={() => setConfirmDeleteId(activePlan.id)}><Trash2 className="h-3 w-3" />Clear menu</Button>}
          {!readOnly && activePlan && (
            <Button variant="secondary" size="sm" onClick={handleScaleToPrescription} disabled={scalingPlan || savingWeek || generating || activePlan.scale_status === 'already_scaled'}>
              {scalingPlan ? <Loader2 className="h-3 w-3 animate-spin" /> : <Wand2 className="h-3 w-3" />}
              {activePlan.scale_status === 'already_scaled' ? 'Already scaled' : 'Scale to prescription'}
            </Button>
          )}
          {!readOnly && <Button variant="secondary" size="sm" onClick={() => void performMenuAction('generate')} disabled={generating || savingWeek || savingIntervention || creatingPlan}>
            {generating ? <Loader2 className="h-3 w-3 animate-spin" /> : <Wand2 className="h-3 w-3" />} Auto-Generate
          </Button>}
          {!readOnly && !activePlan && <Button variant="secondary" size="sm" onClick={() => void performMenuAction('create')} disabled={creatingPlan || savingIntervention || generating}>
            {creatingPlan ? <Loader2 className="h-3 w-3 animate-spin" /> : <Plus className="h-3 w-3" />} Create Menu
          </Button>}
        </div>
      </div>

      {savingWeek && <p role="status" className="text-sm text-warm-500">Saving menu…</p>}

      {!readOnly && <div className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-xl border border-warm-100 bg-warm-50/60 px-3 py-1.5">
        <span className="text-xs font-bold uppercase tracking-wider text-warm-400">Auto-generate</span>
        <label className="flex min-h-9 items-center gap-2 text-xs font-semibold text-warm-600">
          <input type="checkbox" checked={excludeCarbs}
            onChange={(event) => { setExcludeCarbs(event.target.checked); setSelectedCarb(null); setCarbChoiceOpen(false); }}
            className="h-4 w-4 accent-emerald-600" />
          Exclude carbs
        </label>
        <InfoHint label="How the carbohydrate choice works" title="Carbohydrate choice">
          Choose one Carb Menu Item for all 21 main slots, or exclude the separate Carb side. Carbohydrates within Meal Menu Items remain.
        </InfoHint>
        <label className="flex min-h-9 items-center gap-2 text-xs font-semibold text-warm-600">
          <input type="checkbox" checked={excludeSnacks}
            onChange={(event) => setExcludeSnacks(event.target.checked)}
            className="h-4 w-4 accent-emerald-600" />
          Exclude snacks
        </label>
        {(allergens.length > 0 || foodDislikes.length > 0) && (
          <InfoHint label="How allergies and dislikes affect meal generation" title="Allergies and dislikes">
            {allergens.length > 0 && (
              <p>Allergens are excluded from auto-generated recommendations: {allergens.join(", ")}.</p>
            )}
            {foodDislikes.length > 0 && (
              <p className={allergens.length > 0 ? "mt-2" : undefined}>
                Disliked foods remain eligible but are flagged for RND review: {foodDislikes.join(", ")}.
              </p>
            )}
          </InfoHint>
        )}
      </div>}

      {generateError && (
        <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
          <AlertTriangle className="h-3.5 w-3.5 text-amber-600 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-amber-800">{generateError}</p>
          {templateDraft && interventionPlanId && <Button variant="secondary" size="sm" onClick={() => void handleSaveWeek()}>Retry</Button>}
        </div>
      )}

      {templateWarning && (
        <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
          <AlertTriangle className="h-3.5 w-3.5 text-amber-600 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-amber-800">{templateWarning}</p>
        </div>
      )}

      {scaleNotice && (
        <div className="flex items-start gap-2 p-3 bg-sky-50 border border-sky-200 rounded-xl">
          <AlertTriangle className="h-3.5 w-3.5 text-sky-600 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-sky-800">{scaleNotice}</p>
        </div>
      )}

      {loadingPlans && <div className="flex items-center gap-1.5 px-1 text-xs text-warm-400"><Loader2 className="h-3 w-3 animate-spin" />Loading…</div>}

      {deleteError && (
        <div className="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-xl">
          <AlertTriangle className="h-3.5 w-3.5 text-red-600 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-red-800">{deleteError}</p>
        </div>
      )}

      {activePlan && (
        <>
          <MacroTrackerBar
            targets={trackerTargets}
            showMicros={showMicros}
            onToggleMicros={() => setShowMicros((v) => !v)}
            hasMicros={displayedMicros.length > 0}
          />

          {t.fluid > 0 && (
            <div className="grid gap-2 rounded-xl border border-sky-100 bg-sky-50 p-3 text-sm text-sky-900 sm:grid-cols-3">
              <p><span className="font-bold">Fluid prescription:</span> {nutrientOperators?.fluid_ml === 'max' ? '≤' : nutrientOperators?.fluid_ml === 'min' ? '≥' : ''}{selectedFluid.requiredFluidMl} mL</p>
              <p><span className="font-bold">Fluid from planned foods, if this menu is followed:</span> {selectedFluid.foodFluidMl} mL</p>
              <p><span className="font-bold">Remaining drinking fluid, if this menu is followed:</span> {selectedFluid.remainingMl} mL</p>
            </div>
          )}

          {/* Micro totals row */}
          {showMicros && displayedMicros.length > 0 && (
            <div className="p-3 bg-sky-50 border border-sky-100 rounded-xl space-y-2">
              <p className="text-xs font-bold text-sky-500 uppercase tracking-widest">Daily Micronutrients — {selectedDay}</p>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                {displayedMicros.map((key) => {
                  const meta = ALL_MICROS.find((m) => m.key === key);
                  const val  = Math.round((selectedMicroTotals[key] ?? 0) * 10) / 10;
                  const limit = micronutrientLimits[key];
                  const overLimit = limit?.max != null && val > limit.max;
                  const underLimit = limit?.min != null && val < limit.min;
                  return (
                    <div key={key} className={`flex items-center justify-between px-2.5 py-1.5 rounded-lg border text-xs ${overLimit ? 'bg-red-50 border-red-200' : underLimit ? 'bg-amber-50 border-amber-200' : 'bg-white border-warm-200'}`}>
                      <span className="font-semibold text-warm-700">{meta?.label ?? key}</span>
                      <span className={`font-mono font-bold ${overLimit ? 'text-red-600' : underLimit ? 'text-amber-600' : 'text-warm-500'}`}>
                        {val}{meta?.unit}
                        {limit?.target != null && <span className="font-normal text-warm-400"> / {limit.target}</span>}
                        {limit?.min != null && <span className="font-normal text-warm-400"> / ≥{limit.min}</span>}
                        {limit?.max != null && <span className="font-normal text-warm-400"> / ≤{limit.max}</span>}
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
                  className={`px-3 py-2 rounded-xl text-xs font-bold border transition-colors cursor-pointer text-center ${
                    selectedDay === d ? 'bg-emerald-600 text-white border-emerald-600'
                    : isDayFlagged ? 'bg-white text-amber-700 border-amber-300 hover:border-amber-400'
                    : 'bg-white text-warm-600 border-warm-200 hover:border-emerald-300'
                  }`}>
                  <span className="block">{d.slice(0,3)}</span>
                  {tot.cal > 0 && <span className="block font-normal opacity-80">{tot.cal}kcal</span>}
                </button>
              );
            })}
          </div>

          {/* Meal slots */}
          <WeeklyMenuCalendar renderCell={(dayName, mt) => {
              const day = activePlan.days.find((d) => d.day_of_week === dayName && d.meal_type === mt);
              if (!day) return null;
              const key = slotKey(dayName, mt);
              const items = itemsByKey[key] ?? [];
              return (
                <MenuEditorSlots itemActions={items.map((item) => ({ label: `${readOnly ? 'View' : 'Edit'} ${item.nutrient_snapshot?.name ?? 'Menu Item'}`, onOpen: () => openEdit(item, day.id, key) }))} items={items.map((item) => {
                    const s = item.nutrient_snapshot;
                    const isDisliked = foodDislikes.some((d) => s?.name?.toLowerCase().includes(d));
                    return <div key={item.id} className="flex h-full flex-col justify-between gap-1">
                          <MenuEditorActions>
                            {!readOnly && <MenuEditorAction kind="remove" onClick={() => void removeItem(key, day.id, item.id)} label={`Remove ${s?.name ?? 'Menu Item'}`} />}
                          </MenuEditorActions>
                          <div className="flex min-h-0 items-start justify-between gap-1">
                            <div className="flex min-w-0 flex-1 items-center gap-1.5">
                              <p className="line-clamp-2 w-full min-w-0 overflow-hidden break-words text-center text-xs font-semibold leading-4 text-warm-800">{s?.name ?? 'Menu Item'}</p>
                              {item.source === 'usda' && <span className="text-xs font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-1.5 rounded-full uppercase">USDA</span>}
                              {isDisliked && (
                                <span className="pointer-events-auto relative z-10"><InfoHint label={`Patient dislikes ${s?.name ?? "this food"}`} title="Patient food dislike">
                                  Patient marked this food as disliked. Review the item before using this plan.
                                </InfoHint></span>
                              )}
                            </div>
                          </div>
                          <p className="truncate text-xs text-warm-500">{formatMealPlanAmount(item.quantity)} {item.unit}</p>
                        </div>;
                  })} addLabel="Add item" addAriaLabel={`Add item to ${dayName} ${mt.replaceAll('_', ' ')}`} readOnly={readOnly} emptyLabel="Empty" onAdd={() => openPicker(day.id, mt)} />
              );
            }} />
        </>
      )}

      {/* ── Meal Edit Modal ─────────────────────────────────────────────────── */}
      {!readOnly && carbChoiceOpen && <div role="dialog" aria-modal="true" aria-label="Choose Carb Menu Item" className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
        <div className="flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div className="flex items-center justify-between border-b border-warm-100 p-4">
            <h3 className="font-bold text-warm-900">Choose Carb Menu Item</h3>
            <button type="button" aria-label="Close Carb selection" onClick={() => setCarbChoiceOpen(false)} className="rounded-lg p-2 text-warm-500"><X className="h-4 w-4" /></button>
          </div>
          <div className="space-y-3 overflow-y-auto p-4">
            <p className="text-sm text-warm-600">The same Carb will accompany the Meal in all 21 Breakfast, Lunch, and Dinner slots.</p>
            <label className="block text-sm font-semibold text-warm-700">Search Carb Menu Items
              <input value={carbQuery} onChange={(event) => { setCarbQuery(event.target.value); setCarbPage(1); setCarbChoices([]); }} className="mt-1 w-full rounded-lg border border-warm-200 px-3 py-2" />
            </label>
            {loadingCarbs && <p role="status" className="text-sm text-warm-500">Loading Carb Menu Items…</p>}
            {!loadingCarbs && carbChoices.length === 0 && <p className="text-sm text-warm-500">No Carb Menu Items found. Add one in Food Library or use Exclude carbs.</p>}
            {carbChoices.map((choice) => <label key={choice.id} className="flex items-center gap-3 rounded-xl border border-warm-200 p-3">
              <input type="radio" name="selected-carb" checked={selectedCarb?.id === choice.id} onChange={() => { setSelectedCarb(choice); setGenerateError(null); }} className="h-4 w-4 accent-emerald-600" />
              <span className="min-w-0 text-sm font-semibold text-warm-800">{choice.name}</span>
            </label>)}
            <Pagination meta={carbMeta} page={carbPage} onPageChange={setCarbPage} />
            {selectedCarb && <p className="text-sm text-warm-700">Selected: {selectedCarb.name}. <a href={`/food-library/recipes/${selectedCarb.id}`} target="_blank" rel="noopener noreferrer" className="font-semibold underline">View Ingredients</a></p>}
            {generateError && <p role="alert" className="text-sm text-red-700">{generateError}</p>}
          </div>
          <div className="flex justify-end gap-2 border-t border-warm-100 p-4">
            <Button variant="secondary" onClick={() => setCarbChoiceOpen(false)}>Cancel</Button>
            <Button variant="primary" disabled={!selectedCarb || generating} loading={generating} onClick={() => void handleGenerate(selectedCarb ?? undefined)}>Generate week</Button>
          </div>
        </div>
      </div>}


      {/* ── Delete Plan Confirm ─────────────────────────────────────────────── */}
      {!readOnly && confirmDeleteId && (
        <div role="dialog" aria-modal="true" aria-label="Clear menu" className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
            <h3 className="text-base font-extrabold text-warm-900">Clear menu?</h3>
            <p className="text-sm text-warm-500">Remove all items from this weekly menu?</p>
            <div className="flex gap-2 justify-end">
              <Button variant="ghost" onClick={() => setConfirmDeleteId(null)}>Cancel</Button>
              <Button variant="danger" onClick={handleDeletePlan} loading={deleting} className="w-auto px-4 py-2 text-sm">
                Clear menu
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* ── Food Picker Modal ───────────────────────────────────────────────── */}
      {!readOnly && pickerOpen && <MenuItemPicker query={recipeQuery} onQueryChange={(value) => { setPickerLoading(true); setRecipeQuery(value); setRecipePage(1); setRecipeResults([]); }} loading={pickerLoading} error={pickerError} items={recipeResults.map((item) => ({ id: item.id, name: item.name, detail: `${item.category} · Recipe makes ${item.servings} ${item.servings === 1 ? "serving" : "servings"}` }))} onSelect={(id) => { const recipe = recipeResults.find((item) => item.id === id); if (recipe) void addFromRecipe(recipe); }} onClose={() => setPickerOpen(false)} busyId={adding === null ? null : String(adding)} meta={recipeMeta} page={recipePage} onPageChange={setRecipePage} />}

      {/* ── Save Template Modal ─────────────────────────────────────────────── */}
      {!readOnly && saveTemplateOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
            <h3 className="text-base font-extrabold text-warm-900">Save as Template</h3>
            <div className="space-y-1.5">
              <label className="block text-xs font-bold text-warm-400 uppercase tracking-widest">Template Name</label>
              <input type="text" value={templateName} onChange={(e) => setTemplateName(e.target.value)} autoFocus
                className="w-full px-3.5 py-2.5 text-base border border-warm-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
            </div>
            <div className="flex gap-2 justify-end">
              <Button variant="ghost" onClick={() => setSaveTemplateOpen(false)}>Cancel</Button>
              <Button variant="primary" onClick={handleSaveTemplate} loading={savingTemplate}
                disabled={savingTemplate || !templateName.trim()} className="w-auto px-4 py-2 text-sm">
                Save Template
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* ── Template Manager Modal ──────────────────────────────────────────── */}
      {!readOnly && fromTemplateOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-7xl mx-4 flex flex-col max-h-[80vh]">
            <div className="flex items-center justify-between px-6 py-4 border-b border-warm-100">
              {viewingTemplate ? (
                <div className="flex items-center gap-2">
                  <button onClick={() => setViewingTemplate(null)} className="text-warm-400 hover:text-warm-700 cursor-pointer">
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                  </button>
                  <h3 className="text-base font-extrabold text-warm-900">{viewingTemplate.name}</h3>
                </div>
              ) : <h3 className="text-base font-extrabold text-warm-900">Meal Plan Templates</h3>}
              <button onClick={() => { setFromTemplateOpen(false); setViewingTemplate(null); }} className="text-warm-400 hover:text-warm-700 cursor-pointer"><X className="h-4 w-4" /></button>
            </div>
            <div className="overflow-y-auto flex-1 p-4">
              {generateError && <p role="alert" className="mb-3 text-sm text-red-700">{generateError}</p>}
              {!viewingTemplate && (templates.length === 0 ? (
                <p className="text-sm text-warm-400 text-center py-8">No templates saved yet.</p>
              ) : (
                <div className="space-y-2">
                  {templates.map((tmpl) => (
                    <div key={tmpl.id} className="flex items-center gap-2 p-3 border border-warm-200 rounded-xl hover:border-warm-300 transition-colors">
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-semibold text-warm-800 truncate">{tmpl.name}</p>
                        {(tmpl.goal_type || tmpl.maternal_status) && <p className="text-xs text-warm-400 capitalize">{tmpl.goal_type?.replace(/_/g, ' ') ?? 'Maternal'}{tmpl.disease_stage ? ` · ${tmpl.disease_stage.replace(/_/g, ' ')}` : ''}</p>}
                      </div>
                      <div className="flex items-center gap-1 flex-shrink-0">
                        <button onClick={() => handleViewTemplate(tmpl.id)} disabled={loadingTemplate === tmpl.id}
                          className="px-2.5 py-1.5 text-xs font-bold text-warm-500 border border-warm-200 rounded-lg hover:border-zinc-400 hover:text-warm-700 transition-colors cursor-pointer disabled:opacity-40">
                          {loadingTemplate === tmpl.id ? <Loader2 className="h-3 w-3 animate-spin" /> : 'View'}
                        </button>
                        <button onClick={() => handleFromTemplate(tmpl.id)}
                          className="px-2.5 py-1.5 text-xs font-bold text-emerald-700 border border-emerald-300 rounded-lg hover:bg-emerald-50 transition-colors cursor-pointer">Use</button>
                        <button onClick={() => setConfirmDeleteTemplateId(tmpl.id)} title="Delete template"
                          className="p-1.5 text-warm-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors cursor-pointer"><Trash2 className="h-3 w-3" /></button>
                      </div>
                    </div>
                  ))}
                </div>
              ))}
              {!viewingTemplate && <Pagination meta={templateMeta} page={templatePage} onPageChange={setTemplatePage} />}
              {viewingTemplate && (
                <div className="space-y-3">
                  {(viewingTemplate.goal_type || viewingTemplate.maternal_status) && <p className="text-xs text-warm-400 capitalize">{viewingTemplate.goal_type?.replace(/_/g, ' ') ?? 'Maternal'}{viewingTemplate.disease_stage ? ` · ${viewingTemplate.disease_stage.replace(/_/g, ' ')}` : ''}</p>}
                  <WeeklyMenuCalendar renderCell={(day, meal) => {
                    const items = viewingTemplate.days.filter((slot) => slot.day_of_week === day && slot.meal_type === meal).flatMap((slot) => slot.items);
                    return <MenuEditorSlots itemActions={items.map((item) => item.recipe_id ? { label: `View ${item.food_name ?? 'Menu Item'}`, onOpen: () => setTemplateProfile({ id: item.recipe_id!, name: item.food_name ?? 'Menu Item', snapshot: item.nutrient_snapshot }) } : undefined)} items={items.map((item) => <div key={item.id} className="flex h-full flex-col justify-between gap-1">
                    <p className="line-clamp-2 overflow-hidden break-words text-center text-xs font-semibold text-warm-800">{item.food_name ?? 'Legacy item'}</p>
                    <p className="text-xs text-warm-500">{formatMealPlanAmount(item.quantity)} {item.unit}</p>
                  </div>)} addLabel="Add item" readOnly />; }} />
                  <button onClick={() => handleFromTemplate(viewingTemplate.id)}
                    className="w-full py-2.5 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors cursor-pointer mt-2">
                    Use This Template
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ── Delete Template Confirm ─────────────────────────────────────────── */}
      {!readOnly && confirmDeleteTemplateId && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
            <h3 className="text-base font-extrabold text-warm-900">Delete Template?</h3>
            <p className="text-sm text-warm-500">This template will be permanently deleted. Plans already created from it are not affected.</p>
            <div className="flex gap-2 justify-end">
              <Button variant="ghost" onClick={() => setConfirmDeleteTemplateId(null)}>Cancel</Button>
              <Button variant="danger" onClick={handleDeleteTemplate} loading={deletingTemplate} className="w-auto px-4 py-2 text-sm">
                Delete Template
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
