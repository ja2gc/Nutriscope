"use client";

import { MenuItemPicker } from "@/components/ncp/MenuItemPicker";
import ButtonFilterGroup from "@/components/ui/ButtonFilterGroup";
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
  updateMealPlanCarbs, setMealPlanSnackRows,
  MealPlan, MealPlanItem, MealPlanTemplate, MealPlanTemplateDetail, NutrientSnapshot,
} from "@/services/mealPlanService";
import {
  fetchRecipes, fetchRecipeById,
  Recipe,
} from "@/services/foodLibraryService";
import MacroTrackerBar from "./MacroTrackerBar";
import { WeeklyMenuCalendar, MENU_DAYS, MENU_SLOTS } from "@/components/ncp/WeeklyMenuCalendar";
import { MenuEditorSlots, MenuEditorActions, MenuEditorAction } from "@/components/ncp/MenuEditorSlots";
import { RecipeMenuItemProfile } from "@/components/ncp/MenuItemProfile";
import { foodFluidBalance, formatMealPlanAmount, recipeMenuSnapshot } from "@/lib/mealPlanNutrition";
import { CarbMenuEditor } from '@/components/ncp/CarbMenuEditor';
import { MealPlanOptionsDialog, type MealPlanOptions } from '@/components/ncp/MealPlanOptionsDialog';
import { MENU_ITEM_CATEGORIES, menuItemCategoryLabel } from "@/lib/menuItemCategories";
import { MicronutrientTotals } from '@/components/ncp/MicronutrientTotals';
import { ClinicalMenuItemEditor, type MenuItemEdit } from '@/components/ncp/ClinicalMenuItemEditor';

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
  const [optionsMode, setOptionsMode] = useState<'generate' | 'add' | 'edit' | null>(null);
  const generationOptions = useRef<{ excludeSnacks: boolean; excludeCarbs: boolean; carb: Recipe | null }>({ excludeSnacks: false, excludeCarbs: false, carb: null });
  const [snackBusy, setSnackBusy] = useState(false);

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

  const [editTarget, setEditTarget] = useState<EditTarget | null>(null);

  // Food picker
  const [pickerOpen, setPickerOpen]           = useState(false);
  const [pickerTarget, setPickerTarget]       = useState<{ dayId: string; mealType: string } | null>(null);
  const [recipeQuery, setRecipeQuery]         = useState('');
  const [recipeCategory, setRecipeCategory]   = useState('all');
  const [recipeResults, setRecipeResults]     = useState<Recipe[]>([]);
  const [recipePage, setRecipePage]           = useState(1);
  const [recipeMeta, setRecipeMeta]           = useState<PaginationMeta | null>(null);
  const [pickerLoading, setPickerLoading]     = useState(false);
  const [adding, setAdding]                   = useState<number | string | null>(null);
  const [pickerError, setPickerError]         = useState<string | null>(null);
  const [pickerNotice, setPickerNotice]       = useState<string | null>(null);
  const [templateProfile, setTemplateProfile] = useState<{ id: string; name: string; snapshot?: MealPlanItem["nutrient_snapshot"] } | null>(null);
  useEffect(() => {
    if (!pickerOpen) return;
    let cancelled = false;
    const timer = setTimeout(async () => {
      setPickerLoading(true);
      setPickerError(null);
      try {
        const result = await fetchRecipes(recipeQuery, recipeCategory, recipePage);
        if (!cancelled) { setRecipeResults(result.data); setRecipeMeta(result.meta ?? null); }
      } catch (error) {
        if (!cancelled) setPickerError(error instanceof Error ? error.message : 'Could not load Menu Items. Try again.');
      } finally {
        if (!cancelled) setPickerLoading(false);
      }
    }, 160);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [pickerOpen, recipeCategory, recipePage, recipeQuery]);

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
    setFromTemplateOpen(false); setOptionsMode(null);
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
    const { excludeSnacks, excludeCarbs, carb: chosenCarb } = generationOptions.current;
    carb = carb ?? chosenCarb ?? undefined;
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
      return false;
    }

    if (!excludeCarbs && !carb) {
      setGenerateError('Choose a Carb Menu Item or exclude carbs.');
      return false;
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
      if (version !== scopeVersion.current) return false;
      if ('insufficient_recipes' in result || 'insufficient_suitable_foods' in result) {
        setGenerateError(result.message);
        return false;
      }
      setPlans([result]);
      setTemplateDraft(null);
      setActivePlan(result);
      return true;
    } catch (err) {
      if (version === scopeVersion.current) setGenerateError(err instanceof Error ? err.message : 'Failed to generate meal plan. Please try again.');
      return false;
    } finally { if (version === scopeVersion.current) setGenerating(false); }
  }, [allergens, interventionPlanId, ncpId, prescriptionTargets]);

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
      meal_plan_id: templateDraft.replacePlanId, week_start_date: activePlan.week_start_date, scale, snacks_hidden: Boolean(activePlan.snacks_hidden),
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
      try { if (!await onSaveIntervention?.()) return false; }
      catch (error) { setGenerateError(error instanceof Error ? error.message : 'Could not save intervention.'); return false; }
      finally { setSavingIntervention(false); }
      return false;
    }
    if (action === 'create') await handleCreatePlan(savedInterventionId);
    else if (action === 'template') setFromTemplateOpen(true);
    else if (action === 'save-week') await handleSaveWeek(savedInterventionId);
    else if (action === 'scale') await handleScaleToPrescription();
    else return await handleGenerate(undefined, savedInterventionId);
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

  const openEdit = (item: MealPlanItem, dayId: string, key: string) => setEditTarget({ item, dayId, slotKey: key });
  async function saveEditedItem(payload: MenuItemEdit, snapshot: NutrientSnapshot | null) {
    if (!editTarget || !activePlan) return false;
    const version = scopeVersion.current;
    const updated = templateDraft ? { ...editTarget.item, quantity: String(payload.quantity ?? editTarget.item.quantity), ingredients: payload.ingredients ?? editTarget.item.ingredients, nutrient_snapshot: snapshot } : await updateMealPlanItem(ncpId, activePlan.id, editTarget.dayId, editTarget.item.id, payload);
    if (version !== scopeVersion.current) return false;
    setItemsByKey(current => ({ ...current, [editTarget.slotKey]: (current[editTarget.slotKey] ?? []).map(item => item.id === updated.id ? updated : item) }));
    markPlanNeedsScaling(activePlan.id);
  }

  // ── Picker helpers ──────────────────────────────────────────────────────────

  const openPicker = (dayId: string, mealType: string) => {
    setPickerTarget({ dayId, mealType }); setPickerLoading(true); setPickerOpen(true); setRecipeCategory('all');
    setRecipeQuery(''); setRecipeResults([]); setRecipePage(1); setRecipeMeta(null); setPickerError(null); setPickerNotice(null);
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
    const target = activePlan.days.find((day) => day.id === pickerTarget.dayId);
    const destination = target ? `${target.day_of_week} ${target.meal_type.replaceAll('_', ' ')}` : 'the weekly menu';
    const finishAdd = () => {
      setPickerNotice(`Added ${recipe.name} to ${destination}.${templateDraft ? ' Save the week to keep this change.' : ''}`);
      setPickerOpen(false);
    };
    try {
      if (templateDraft) {
        const yieldAmount = Number(recipe.servings);
        if (!(yieldAmount > 0)) throw new Error('Correct the Menu Item yield before adding it.');
        const item: MealPlanItem = {
          id: crypto.randomUUID(), meal_plan_day_id: pickerTarget.dayId, recipe_id: recipe.id,
          food_item_id: null, fdc_id: null, quantity: '1.00', unit: 'serving', source: 'recipe', ai_suggested: false,
          recipe_category: recipe.category,
          nutrient_snapshot: recipeMenuSnapshot(recipe),
        };
        appendItem(item, activePlan, pickerTarget.dayId);
        finishAdd();
        return;
      }
      appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, {
        recipe_id: recipe.id,
        quantity: 1,
        unit: 'serving',
      }), activePlan, pickerTarget.dayId);
      finishAdd();
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

  const carbEntries = Object.entries(itemsByKey).filter(([key]) => !activePlan?.snacks_hidden || (!key.endsWith('-am_snack') && !key.endsWith('-pm_snack'))).flatMap(([, items]) => items).map(item => ({ recipeId: item.recipe_id, name: item.nutrient_snapshot?.name ?? 'Carb', category: item.recipe_category ?? item.nutrient_snapshot?.recipe_category, quantity: Number(item.quantity), item }));

  async function changeSnackRows(removed: boolean) {
    if (!activePlan) return;
    const version = scopeVersion.current;
    setSnackBusy(true); setGenerateError(null);
    try {
      const next = templateDraft ? { ...activePlan, snacks_hidden: removed } : await setMealPlanSnackRows(ncpId, activePlan.id, removed);
      if (version !== scopeVersion.current) return;
      setActivePlan(next); setPlans(current => current.map(plan => plan.id === next.id ? next : plan));
      markPlanNeedsScaling(activePlan.id);
    } catch (cause) { setGenerateError(cause instanceof Error ? cause.message : 'Could not update snack rows.'); }
    finally { setSnackBusy(false); }
  }

  async function applyCarbOptions(options: MealPlanOptions, selected: Recipe | null, editedSnapshot: NutrientSnapshot | null = null) {
    const version = scopeVersion.current;
    if (optionsMode === 'generate') {
      generationOptions.current = { excludeCarbs: Boolean(options.exclude_carbs), excludeSnacks: Boolean(options.exclude_snacks), carb: selected };
      return await performMenuAction('generate');
    }
    if (!activePlan) return false;
    if (!templateDraft) {
      const result = await updateMealPlanCarbs(ncpId, activePlan.id, options);
      if (version !== scopeVersion.current) return false;
      setActivePlan(result.plan); setPlans(current => current.map(plan => plan.id === result.plan.id ? result.plan : plan));
      setItemsByKey(groupWeekItems(result.plan, result.items));
    } else {
      const replacement = selected ? await fetchRecipeById(selected.id) : null;
      if (version !== scopeVersion.current) return false;
      const snapshot = editedSnapshot ?? (replacement ? recipeMenuSnapshot(replacement) : null);
      const next = Object.fromEntries(Object.entries(itemsByKey).map(([key, items]) => [key, items.filter(item => !(options.remove && item.recipe_id === options.source_recipe_id && (item.recipe_category ?? item.nutrient_snapshot?.recipe_category) === 'carb' && (!activePlan.snacks_hidden || (!key.endsWith('-am_snack') && !key.endsWith('-pm_snack'))))).map(item => {
        if (activePlan.snacks_hidden && (key.endsWith('-am_snack') || key.endsWith('-pm_snack'))) return item;
        if (!options.source_recipe_id || item.recipe_id !== options.source_recipe_id || (item.recipe_category ?? item.nutrient_snapshot?.recipe_category) !== 'carb') return item;
        const quantity = options.quantity ?? Number(item.quantity);
        if (!(quantity >= 0.01 && quantity <= 99999.99)) throw new Error('Resulting servings must be between 0.01 and 99999.99.');
        return replacement && replacement.id !== item.recipe_id ? { ...item, recipe_id: replacement.id, recipe_category: replacement.category, quantity: String(quantity), nutrient_snapshot: snapshot, source_template_item_id: undefined, source_meal_plan_item_id: undefined, ingredient_overrides: undefined, ingredients: undefined } : { ...item, quantity: String(quantity), ...(options.ingredients ? { ingredients: options.ingredients, nutrient_snapshot: snapshot, ingredient_overrides: undefined } : {}) };
      })]));
      if (!options.source_recipe_id && replacement) for (const day of activePlan.days.filter(day => ['breakfast', 'lunch', 'dinner'].includes(day.meal_type))) {
        const key = slotKey(day.day_of_week, day.meal_type);
        if (!(next[key] ?? []).some(item => item.recipe_id === replacement.id)) (next[key] ??= []).push({ id: crypto.randomUUID(), meal_plan_day_id: day.id, recipe_id: replacement.id, recipe_category: replacement.category, quantity: String(options.quantity ?? 1), unit: 'serving', nutrient_snapshot: snapshot, ingredients: options.ingredients, food_item_id: null, fdc_id: null, source: 'recipe', ai_suggested: false });
      }
      setItemsByKey(next);
    }
    markPlanNeedsScaling(activePlan.id);
  }

  const carbDialog = optionsMode === 'generate' && <MealPlanOptionsDialog snacksHidden={Boolean(activePlan?.snacks_hidden)} replacing={Boolean(activePlan)} error={generateError} allergens={allergens} foodDislikes={foodDislikes} onClose={() => { setOptionsMode(null); setPendingMenuAction(null); }} onConfirm={applyCarbOptions} />;

  const dayTotals = (day: string) => {
    let cal=0, prot=0, carb=0, fat=0;
    MEAL_TYPES.forEach((mt) => {
      if (activePlan?.snacks_hidden && ['am_snack', 'pm_snack'].includes(mt)) return;
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
      if (activePlan?.snacks_hidden && ['am_snack', 'pm_snack'].includes(mt)) return;
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
  const selectedItems = MEAL_TYPES.filter(mealType => !activePlan?.snacks_hidden || !['am_snack', 'pm_snack'].includes(mealType)).flatMap((mealType) => itemsByKey[slotKey(selectedDay, mealType)] ?? []);
  const selectedFluid = foodFluidBalance(selectedItems, t.fluid);
  const trackerTargets = [
    { label: 'Energy',  current: selectedTotals.cal,   target: t.energy,        unit: 'kcal', operator: nutrientOperators?.energy_kcal },
    { label: 'Protein', current: selectedTotals.prot,  target: t.protein,       unit: 'g', operator: nutrientOperators?.protein_g },
    { label: 'Carbs',   current: selectedTotals.carb,  target: t.carbs,         unit: 'g', operator: nutrientOperators?.carbs_g },
    { label: 'Fat',     current: selectedTotals.fat,   target: t.fat,           unit: 'g', operator: nutrientOperators?.fat_g },
    { label: 'Fluid',   current: selectedFluid.foodFluidMl, target: t.fluid,    unit: 'mL', operator: nutrientOperators?.fluid_ml },
  ];

  if (templateProfile) return <RecipeMenuItemProfile id={templateProfile.id} name={templateProfile.name} snapshot={templateProfile.snapshot} onBack={() => setTemplateProfile(null)} />;

  if (optionsMode === 'add' || optionsMode === 'edit') return <CarbMenuEditor mode={optionsMode} items={carbEntries} onBack={() => setOptionsMode(null)} onApply={applyCarbOptions} />;
  if (editTarget) return <ClinicalMenuItemEditor item={editTarget.item} readOnly={readOnly} onBack={() => setEditTarget(null)} onSave={saveEditedItem} />;

  return (
    <div className="space-y-4 rounded-none border-0 bg-transparent p-0 shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-5 sm:shadow-sm">
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
          {!readOnly && activePlan && <><Button variant="secondary" size="sm" onClick={() => setOptionsMode('add')}>Add carbs</Button>{carbEntries.some(item => item.category === 'carb') && <Button variant="secondary" size="sm" onClick={() => setOptionsMode('edit')}>Edit carbs</Button>}</>}
          {!readOnly && <Button variant="secondary" size="sm" onClick={() => { setGenerateError(null); setOptionsMode('generate'); }} disabled={generating || savingWeek || savingIntervention || creatingPlan}>
            {generating ? <Loader2 className="h-3 w-3 animate-spin" /> : <Wand2 className="h-3 w-3" />} Generate meal plan
          </Button>}
          {!readOnly && !activePlan && <Button variant="secondary" size="sm" onClick={() => void performMenuAction('create')} disabled={creatingPlan || savingIntervention || generating}>
            {creatingPlan ? <Loader2 className="h-3 w-3 animate-spin" /> : <Plus className="h-3 w-3" />} Create Menu
          </Button>}
        </div>
      </div>

      {savingWeek && <p role="status" className="text-sm text-warm-500">Saving menu…</p>}

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

      {pickerNotice && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{pickerNotice}</p>}

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
          >
          {t.fluid > 0 && (
            <div className="grid gap-2 text-xs text-warm-600 sm:grid-cols-3">
              <p><span className="font-bold">Fluid prescription:</span> {nutrientOperators?.fluid_ml === 'max' ? '≤' : nutrientOperators?.fluid_ml === 'min' ? '≥' : ''}{selectedFluid.requiredFluidMl} mL</p>
              <p><span className="font-bold">Fluid from planned foods, if this menu is followed:</span> {selectedFluid.foodFluidMl} mL</p>
              <p><span className="font-bold">Remaining drinking fluid, if this menu is followed:</span> {selectedFluid.remainingMl} mL</p>
            </div>
          )}
          </MacroTrackerBar>

          {/* Micro totals row */}
          {showMicros && displayedMicros.length > 0 && (
            <MicronutrientTotals title={`Daily Micronutrients — ${selectedDay}`} selected={displayedMicros} totals={selectedMicroTotals} limits={micronutrientLimits} />
          )}

          {/* Day pills */}
          <div className="flex gap-1.5 flex-wrap">
            {DAYS.map((d) => {
              const tot = dayTotals(d);
              const isDayFlagged = activePlan.days.some((day) => day.day_of_week === d && day.flagged === true && (!activePlan.snacks_hidden || !['am_snack', 'pm_snack'].includes(day.meal_type)));
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
          <WeeklyMenuCalendar snacksHidden={Boolean(activePlan.snacks_hidden)} onSnackVisibilityChange={readOnly ? undefined : visible => void changeSnackRows(!visible)} snackVisibilityBusy={snackBusy} getItemCount={(day, meal) => (itemsByKey[slotKey(day, meal)] ?? []).length} renderCell={(dayName, mt, minimumItems) => {
              const day = activePlan.days.find((d) => d.day_of_week === dayName && d.meal_type === mt);
              if (!day) return null;
              const key = slotKey(dayName, mt);
              const items = itemsByKey[key] ?? [];
              return (
                <MenuEditorSlots minimumItems={minimumItems} itemActions={items.map((item) => ({ label: `${readOnly ? 'View' : 'Edit'} ${item.nutrient_snapshot?.name ?? 'Menu Item'}`, onOpen: () => openEdit(item, day.id, key) }))} items={items.map((item) => {
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

      {carbDialog}

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
      {!readOnly && pickerOpen && <MenuItemPicker query={recipeQuery} onQueryChange={(value) => { setPickerLoading(true); setRecipeQuery(value); setRecipePage(1); setRecipeResults([]); }} filters={<div className="[&_button]:min-h-11"><ButtonFilterGroup label="Category" size="sm" value={recipeCategory} onChange={(value) => { setRecipeCategory(value); setRecipePage(1); setRecipeResults([]); setPickerLoading(true); }} options={[{ value: 'all', label: 'All' }, ...MENU_ITEM_CATEGORIES.filter(option => option.value !== 'tube_feeding').map(({ value, label }) => ({ value, label: `${label}s` }))]} /></div>} loading={pickerLoading} error={pickerError} items={recipeResults.map((item) => ({ id: item.id, name: item.name, detail: `${menuItemCategoryLabel(item.category)} · Recipe makes ${item.servings} ${item.servings === 1 ? "serving" : "servings"}` }))} onSelect={(id) => { const recipe = recipeResults.find((item) => item.id === id); if (recipe) void addFromRecipe(recipe); }} onClose={() => setPickerOpen(false)} busyId={adding === null ? null : String(adding)} meta={recipeMeta} page={recipePage} onPageChange={setRecipePage} />}

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
                  <WeeklyMenuCalendar snacksHidden={Boolean(viewingTemplate.snacks_hidden)} getItemCount={(day, meal) => viewingTemplate.days.filter(slot => slot.day_of_week === day && slot.meal_type === meal).flatMap(slot => slot.items).length} renderCell={(day, meal, minimumItems) => {
                    const items = viewingTemplate.days.filter((slot) => slot.day_of_week === day && slot.meal_type === meal).flatMap((slot) => slot.items);
                    return <MenuEditorSlots minimumItems={minimumItems} itemActions={items.map((item) => item.recipe_id ? { label: `View ${item.food_name ?? 'Menu Item'}`, onOpen: () => setTemplateProfile({ id: item.recipe_id!, name: item.food_name ?? 'Menu Item', snapshot: item.nutrient_snapshot }) } : undefined)} items={items.map((item) => <div key={item.id} className="flex h-full flex-col justify-between gap-1">
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
