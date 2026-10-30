"use client";
import { TubeFormulaPlanner } from '@/components/ncp/TubeFormulaPlanner';
import { formulaItemsPayload } from '@/lib/formulaPlan';
import { nutritionSupportLabel } from '@/lib/nutritionSupport';

import React, { Suspense, use, useEffect, useState, useCallback, useRef } from "react";
import Link from "next/link";
import { useUnsavedChanges } from "@/hooks/useUnsavedChanges";
import { useRouter, useSearchParams } from "next/navigation";
import { User, Lock } from "lucide-react";
import {
  fetchIntervention, fetchInterventionPlan, fetchInterventionPlans, createIntervention, updateIntervention, deleteIntervention, autofillIntervention,
  AutofillError, Intervention, type AutofillResult, type InterventionPlanSummary,
} from "@/services/interventionService";
import type { PaginationMeta } from "@/components/ui/Pagination";
import { fetchAssessment, type Assessment } from "@/services/assessmentService";
import {
  fetchPatientById,
  fetchPatientNcpRecords,
  ncpRecordMatchesRoute,
  type Patient,
} from "@/services/patientService";
import { fetchDiagnoses } from "@/services/diagnosisService";
import { fetchMonitoringContext } from "@/services/monitoringService";
import {
  PatientMetrics, ACTIVITY_FACTORS, microKeys, calcIBW,
} from "@/lib/nutritionCalculations";
import {
  buildGoalPrescriptionForm,
  emptyPrescriptionForm,
  nutrientKeysWithValues,
  weightTargetError,
  type PrescriptionFormState,
} from "@/lib/interventionGoalState";
import { buildPrescriptionCalculationTrace } from "@/lib/prescriptionCalculationTrace";
import GoalSelectorModal, { GOALS } from "./_components/GoalSelectorModal";
import { goalLabelFor, goalsForPatient, typeLabelFor } from "./_components/goals";
import type { GuidanceSuggestion } from "@/services/interventionService";
import { Button } from "@/components/ui/Button";
import { NcpSectionTabs } from "@/components/ncp/NcpSectionTabs";
import NutritionPrescriptionForm from "./_components/NutritionPrescriptionForm";
import RecommendAvoidPanel from "./_components/RecommendAvoidPanel";
import EducationTab from "./_components/EducationTab";
import CounselingTab from "./_components/CounselingTab";
import MealPlanSection from "./_components/MealPlanSection";
import InterventionPlansTab from "./_components/InterventionPlansTab";
import InterventionPlanEditor from "./_components/InterventionPlanEditor";
import NcpPatientHeader from "../../../_components/NcpPatientHeader";
import { NcpBreadcrumb } from "@/components/ncp/NcpBreadcrumb";
import {
  INTERVENTION_GUIDANCE_TOTAL_MAX,
  interventionGuidanceCharacters,
} from "@/lib/interventionGuidance";

type Tab = "plans" | "nd" | "education" | "counseling";
type PageParams = { patientId: string; ncpId: string };

const TABS: { key: Tab; label: string }[] = [
  { key: "plans",     label: "Plans" },
  { key: "nd",         label: "Food / Nutrient Delivery" },
  { key: "education",  label: "Education" },
  { key: "counseling", label: "Counseling" },
];

function formatMissingField(field: string) {
  return ({
    weight: "body weight",
    usual_weight: "usual body weight",
    height: "height",
    physical_activity_level: "physical activity level",
    dry_weight_kg: "dry weight",
    dob: "date of birth",
    sex: "sex",
  } as Record<string, string>)[field] ?? field.replace(/_/g, " ");
}

function prescriptionNote(rx?: Partial<AutofillResult> | null): string | undefined {
  if (!rx) return undefined;

  const parts = [rx.note, ...(rx.safety_warnings ?? []).map((warning) => warning.message)];

  return parts.filter(Boolean).join(" ");
}

type PrescriptionForm = PrescriptionFormState;

function interventionToForm(iv: Intervention): PrescriptionForm {
  const micronutrientLimits = iv.micronutrient_limits ?? {};
  return {
    weight_target: iv.weight_target ?? undefined,
    energy_adjustment_mode: iv.energy_adjustment_mode ?? "none",
    energy_adjustment_kcal: iv.energy_adjustment_kcal ? String(iv.energy_adjustment_kcal) : "",
    fluid_mode: iv.fluid_mode,
    calculation_options: iv.calculation_options ?? {},
    calculation_snapshot: iv.calculation_snapshot,
    nutrient_operators: iv.nutrient_operators ?? {},
    energy_kcal: iv.energy_kcal != null ? String(Number(iv.energy_kcal)) : "",
    protein_g:   iv.protein_g   != null ? String(Number(iv.protein_g))   : "",
    carbs_g:     iv.carbs_g     != null ? String(Number(iv.carbs_g))     : "",
    fat_g:       iv.fat_g       != null ? String(Number(iv.fat_g))       : "",
    fluid_ml:    iv.fluid_ml    != null ? String(Number(iv.fluid_ml))    : "",
    micronutrient_limits: micronutrientLimits,
    displayed_nutrients: [...new Set([...microKeys(iv.displayed_nutrients ?? []), ...nutrientKeysWithValues(micronutrientLimits)])],
  };
}

function emptyIntervention(): Intervention {
  return {
    id: "",
    goal_type: null,
    disease_stage: null,
    displayed_nutrients: [],
    energy_kcal: null,
    protein_g: null,
    carbs_g: null,
    fat_g: null,
    fluid_ml: null,
    micronutrient_limits: {},
    education_notes: null,
    counseling_goals: null,
    barriers: null,
    strategies: null,
    session_type: null,
    next_followup_date: null,
    source_monitoring_id: null,
    source_monitoring_date: null,
    has_meal_plan: false,
    meal_plan_id: null,
    created_at: "",
    updated_at: "",
  };
}

function newPlanDraft(latest: Intervention | null): Intervention {
  const supported = latest && GOALS.some(goal => goal.value === latest.goal_type &&
    (goal.stages ? goal.stages.some(type => type.value === latest.disease_stage) : !latest.disease_stage));
  return {
    ...(latest ?? emptyIntervention()),
    goal_type: supported ? latest.goal_type : null,
    disease_stage: supported ? latest.disease_stage : null,
    calculation_snapshot: null,
    id: "",
    source_monitoring_id: null,
    source_monitoring_date: null,
    has_meal_plan: false,
    meal_plan_id: null,
    created_at: "",
    updated_at: "",
  };
}

export default function InterventionPage({ params }: { params: Promise<PageParams> }) {
  return (
    <Suspense fallback={<div className="flex h-48 items-center justify-center text-sm text-warm-500">Loading intervention…</div>}>
      <InterventionWorkspace params={params} />
    </Suspense>
  );
}

function InterventionWorkspace({ params }: { params: Promise<PageParams> }) {
  const { patientId, ncpId } = use(params);
  const router = useRouter();
  const searchParams = useSearchParams();
  const localNavigation = useRef<{ ncpId: string; query: string } | null>(null);
  const markLocalNavigation = (query: string) => {
    localNavigation.current = searchParams.toString() === query ? null : { ncpId, query };
  };
  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const [tab, setTab]                           = useState<Tab>("plans");
  const [intervention, setIntervention]         = useState<Intervention | null>(null);
  const [plans, setPlans]                       = useState<InterventionPlanSummary[]>([]);
  const [plansMeta, setPlansMeta]               = useState<PaginationMeta | null>(null);
  const [plansPage, setPlansPage]               = useState(1);
  const [plansLoading, setPlansLoading]         = useState(false);
  const [plansError, setPlansError]             = useState<string | null>(null);
  const [selectedPlanId, setSelectedPlanId]     = useState<string | null>(null);
  const [newlySavedInterventionId, setNewlySavedInterventionId] = useState<string | null>(null);
  const [latestPlanId, setLatestPlanId]         = useState<string | null>(null);
  const [editorMode, setEditorMode]             = useState<"edit" | "readonly">("readonly");
  const [editorError, setEditorError]           = useState<string | null>(null);
  const [patient, setPatient]                   = useState<Patient | null>(null);
  const [loading, setLoading]                   = useState(true);
  const [goalModalOpen, setGoalModalOpen]       = useState(false);
  const [prescription, setPrescription]         = useState<PrescriptionForm>(emptyPrescriptionForm());
  const [prescNote, setPrescNote]               = useState<string | undefined>(undefined);
  const [saving, setSaving]                     = useState(false);
  // Unsaved-changes tracking: true once the RND edits a field, false after a
  // successful save or a fresh load. Drives the "save before leaving?" guard.
  const [dirty, setDirty]                       = useState(false);
  const [workflowLoading, setWorkflowLoading]   = useState(true);
  const [workflowBlock, setWorkflowBlock]       = useState<string | null>(null);
  const [patientMetrics, setPatientMetrics]     = useState<PatientMetrics | null>(null);
  const [foodDislikes, setFoodDislikes]         = useState<string[]>([]);
  const [allergens, setAllergens]               = useState<string[]>([]);
  const [dietaryRestrictions, setDietaryRestrictions] = useState<string | null>(null);
  const [assessmentContext, setAssessmentContext] = useState<Assessment | null>(null);
  const [goalError, setGoalError]               = useState<string | null>(null);
  const [calculationWarning, setCalculationWarning] = useState<string | null>(null);

  const [calculationDirty, setCalculationDirty] = useState(false);
  const [ageAtMeasurement, setAgeAtMeasurement] = useState<number | null>(null);
  const [guidance, setGuidance] = useState<GuidanceSuggestion | null>(null);
  const calculationRequest = useRef(0);

  const [educationNotes, setEducationNotes]   = useState("");
  const [counselingGoals, setCounselingGoals] = useState("");
  const [barriers, setBarriers]               = useState("");
  const [strategies, setStrategies]           = useState("");
  const guidanceCharacters = interventionGuidanceCharacters([
    educationNotes,
    counselingGoals,
    barriers,
    strategies,
  ]);

  const applyIntervention = useCallback((iv: Intervention | null) => {
    setIntervention(iv);
    setPrescription(iv ? interventionToForm(iv) : emptyPrescriptionForm());
    setEducationNotes(iv?.education_notes ?? "");
    setCounselingGoals(iv?.counseling_goals ?? "");
    setBarriers(iv?.barriers ?? "");
    setStrategies(iv?.strategies ?? "");
    setGuidance(iv?.guidance ?? null);
    setCalculationDirty(false);
    setPrescNote(undefined);
    setCalculationWarning(null);
    setGoalError(null);
    setDirty(false);
  }, []);

  const prepareNewPlan = useCallback(async (latest: Intervention | null) => {
    const draft = newPlanDraft(latest);
    const mode = (await fetchAssessment(ncpId)).nutrition_support;
    draft.nutrition_support = mode;
    draft.formula_items = mode === 'tube_feeding' && latest?.nutrition_support === mode ? structuredClone(latest.formula_items ?? []).map(item => ({ ...item, id: crypto.randomUUID(), source_intervention_id: latest.id, source_intervention_item_id: item.id })) : [];
    setNewlySavedInterventionId(null);
    applyIntervention(draft);
    setSelectedPlanId(null);
    setEditorMode("edit");
    setEditorError(null);

    if (!draft.goal_type) {
      if (latest?.goal_type) setCalculationWarning("Select a supported goal for this new plan. The previous plan is unchanged.");
      return;
    }

    try {
      const filled = await autofillIntervention(ncpId, draft.goal_type, draft.disease_stage, draft);
      setIntervention((current) => current ? {
        ...current,
        source_monitoring_id: filled.source_monitoring_id ?? null,
        source_monitoring_date: filled.source_monitoring_date ?? null,
      } : current);
      setGuidance(filled.guidance ?? null);
      setPrescription(current => ({ ...current, calculation_snapshot: filled.calculation_snapshot }));
      if (!latest) {
        setPrescription(current => ({ ...current, ...buildGoalPrescriptionForm(draft.goal_type!, filled) }));
        setPrescNote(prescriptionNote(filled));
      }
    } catch (error) {
      const missing = error instanceof AutofillError ? error.missingFields : [];
      const missingText = missing.length ? ` Missing: ${missing.map(formatMissingField).join(", ")}.` : "";
      setCalculationDirty(true);
      setCalculationWarning(`Prescription calculation incomplete.${missingText}`);
    }
  }, [applyIntervention, ncpId]);

  const loadPlans = useCallback(async () => {
    setLoading(true);
    setPlansLoading(true);
    setPlansError(null);
    try {
      const [result, latest] = await Promise.all([
        fetchInterventionPlans(ncpId, plansPage),
        fetchIntervention(ncpId),
      ]);
      setPlans(result.data);
      setPlansMeta(result.meta);
      setLatestPlanId(latest?.id ?? null);
      const requestedPlanId = searchParams.get("plan");
      const creating = searchParams.get("mode") === "new";

      if (creating) {
        await prepareNewPlan(latest);
        return;
      }

      const planId = requestedPlanId ?? result.data[0]?.id ?? null;
      if (!planId) {
        await prepareNewPlan(null);
        return;
      }

      applyIntervention(planId === latest?.id ? latest : await fetchInterventionPlan(ncpId, planId));
      setSelectedPlanId(planId);
      setEditorMode(planId === latest?.id ? "edit" : "readonly");
    } catch (error) {
      setPlansError(error instanceof Error ? error.message : "Failed to load intervention plans.");
    } finally {
      setPlansLoading(false);
      setLoading(false);
    }
  }, [applyIntervention, ncpId, plansPage, prepareNewPlan, searchParams]);

  const loadMetrics = useCallback(async () => {
    setWorkflowLoading(true);
    try {
      const [assessment, patientData, diagnoses, ncpRecords, monitoringContext] = await Promise.allSettled([
        fetchAssessment(ncpId),
        fetchPatientById(patientId),
        fetchDiagnoses(ncpId),
        fetchPatientNcpRecords(patientId, 1, ncpId),
        fetchMonitoringContext(ncpId),
      ]);

      const a = assessment.status === "fulfilled" ? assessment.value : null;
      const p = patientData.status === "fulfilled" ? patientData.value : null;
      const hasDiagnosis = diagnoses.status === "fulfilled" && diagnoses.value.length > 0;
      const effective = monitoringContext.status === "fulfilled" ? monitoringContext.value : a;
      if (p) setPatient(p);
      setAssessmentContext(a);

      const routeMatchesPatient = ncpRecords.status === "fulfilled"
        && ncpRecordMatchesRoute(ncpRecords.value.data, ncpId);
      if (!routeMatchesPatient) {
        setPatientMetrics(null);
        setWorkflowBlock("This care cycle does not belong to the selected patient.");
        return;
      }

      if (!a) {
        setWorkflowBlock("Save the assessment before starting intervention.");
      } else if (!hasDiagnosis) {
        setWorkflowBlock("Save at least one diagnosis before starting intervention.");
      } else {
        setWorkflowBlock(null);
      }

      const ageYears = effective && "age_years" in effective ? effective.age_years : null;
      setAgeAtMeasurement(ageYears == null ? null : Number(ageYears));
      const sex = effective && "sex" in effective ? effective.sex : p?.sex;
      const calculationWeight = effective?.edema_present ? effective?.dry_weight_kg : effective?.weight;
      if (calculationWeight && effective?.height && ageYears != null && (sex === "Male" || sex === "Female")) {
        const palKey = effective.physical_activity_level ?? "sedentary";
        setPatientMetrics({
          weightKg: Number(calculationWeight), heightCm: Number(effective.height),
          ageYears: Number(ageYears), sex, isAdult: Number(ageYears) >= 18,
          activityFactor: ACTIVITY_FACTORS[palKey]?.factor ?? 1.2,
          pregnancyLactationStatus: effective.pregnancy_lactation_status ?? "none",
        });
      } else setPatientMetrics(null);
      setFoodDislikes(Array.isArray(effective?.food_dislikes)
        ? effective.food_dislikes.map((d: string) => d.toLowerCase())
        : []);
      setAllergens(Array.isArray(effective?.allergies)
        ? effective.allergies.map((allergen: string) => allergen.toLowerCase())
        : []);
      setDietaryRestrictions(effective?.dietary_restrictions ?? null);
    } catch { /* assessment may not exist yet */ }
    finally { setWorkflowLoading(false); }
  }, [ncpId, patientId]);

  useEffect(() => {
    if (isPlaceholder) return;
    if (localNavigation.current?.ncpId === ncpId) {
      if (localNavigation.current.query === searchParams.toString()) localNavigation.current = null;
      return;
    }
    void loadPlans();
  }, [isPlaceholder, loadPlans, ncpId, searchParams]);

  useEffect(() => {
    if (!isPlaceholder) void loadMetrics();
  }, [isPlaceholder, loadMetrics]);

  const calculationPayload = (form: PrescriptionForm): Partial<Intervention> => ({
    recalculate: form.recalculate ?? false,
    energy_adjustment_mode: form.energy_adjustment_mode ?? "none",
    energy_adjustment_kcal: form.energy_adjustment_mode === "none" ? 0 : Number(form.energy_adjustment_kcal),
    fluid_mode: form.fluid_mode,
    fluid_ml: form.fluid_ml ? Number(form.fluid_ml) : null,
    calculation_options: form.calculation_options,
  });

  const calculateTargets = async (goal: string, stage: string | null, form: PrescriptionForm) => {
    const available = goalsForPatient((ageAtMeasurement ?? patientMetrics?.ageYears ?? 18) < 18).find(item => item.value === goal);
    if (!available || (available.stages ? !available.stages.some(item => item.value === stage) : Boolean(stage))) {
      setCalculationWarning("Choose a current goal and type to recalculate. Saved targets remain editable.");
      return;
    }
    const requestId = ++calculationRequest.current;
    setSaving(true);
    setCalculationWarning(null);
    try {
      const result = await autofillIntervention(ncpId, goal, stage, calculationPayload(form));
      if (requestId !== calculationRequest.current) return;
      setPrescription({ ...form, ...(goal === "custom" ? {} : buildGoalPrescriptionForm(goal, result)),
        calculation_snapshot: result.calculation_snapshot,
        fluid_mode: result.fluid_mode,
        fluid_ml: result.fluid_ml == null ? form.fluid_ml : String(result.fluid_ml),
        micronutrient_limits: form.micronutrient_limits,
        displayed_nutrients: form.displayed_nutrients,
        nutrient_operators: { ...form.nutrient_operators, ...result.nutrient_operators },
        recalculate: true,
        energy_adjustment_mode: form.energy_adjustment_mode ?? "none",
        energy_adjustment_kcal: form.energy_adjustment_kcal,
        calculation_options: form.calculation_options,
        weight_target: form.weight_target,
      });
      setIntervention(current => current ? { ...current, source_monitoring_id: result.source_monitoring_id ?? null,
        source_monitoring_date: result.source_monitoring_date ?? null } : current);
      setPrescNote(prescriptionNote(result));
      setGuidance(result.guidance ?? null);
      setCalculationDirty(false);
      setDirty(true);
    } catch (error) {
      if (requestId !== calculationRequest.current) return;
      setCalculationDirty(true);
      setCalculationWarning(error instanceof Error ? error.message : "Calculation unavailable. Review the inputs.");
      try {
        const notes = await autofillIntervention(ncpId, goal, stage, calculationPayload(form), true);
        if (requestId === calculationRequest.current) setGuidance(notes.guidance ?? null);
      } catch { /* The calculation error remains visible. */ }
    } finally {
      if (requestId === calculationRequest.current) setSaving(false);
    }
  };

  const handleGoalConfirm = async (goalType: string, stage: string | null) => {
    if (editorMode !== "edit") return;
    setGoalError(null);
    setGoalModalOpen(false);
    setIntervention(current => ({ ...(current ?? emptyIntervention()), goal_type: goalType, disease_stage: stage }));
    const form: PrescriptionForm = {
      ...prescription, calculation_snapshot: null,
      energy_adjustment_mode: goalType === "custom" ? "none" : prescription.energy_adjustment_mode ?? "none",
      energy_adjustment_kcal: goalType === "custom" ? "" : prescription.energy_adjustment_kcal,
      calculation_options: { ...prescription.calculation_options, energy_per_kg: undefined, protein_per_kg: undefined },
    };
    setPrescription(form);
    setDirty(true);
    await calculateTargets(goalType, stage, form);
  };

  const handlePrescriptionChange = (form: PrescriptionForm) => {
    const options = (value: PrescriptionForm) => {
      const { refeeding_risk: _risk, malnutrition_note: _note, nutrition_status, ...clinical } = value.calculation_options ?? {};
      void _risk; void _note;
      return intervention?.goal_type === "liver_disease" ? { ...clinical, nutrition_status } : clinical;
    };
    const controlsChanged = form.energy_adjustment_mode !== prescription.energy_adjustment_mode ||
      form.energy_adjustment_kcal !== prescription.energy_adjustment_kcal ||
      JSON.stringify(options(form)) !== JSON.stringify(options(prescription));
    if (controlsChanged) setCalculationDirty(true);
    const notesChanged = JSON.stringify(form.calculation_options) !== JSON.stringify(prescription.calculation_options)
      || form.fluid_mode !== prescription.fluid_mode;
    setPrescription(form);
    setDirty(true);
    const energyEdited = form.energy_kcal !== prescription.energy_kcal;
    if ((notesChanged || energyEdited) && intervention?.goal_type && !controlsChanged) {
      const requestId = ++calculationRequest.current;
      const payload = calculationPayload(form);
      if (form.calculation_snapshot?.calculated.energy_kcal != null && Number(form.energy_kcal) !== form.calculation_snapshot.calculated.energy_kcal) {
        payload.energy_adjustment_mode = "none";
        payload.energy_adjustment_kcal = 0;
      }
      void autofillIntervention(ncpId, intervention.goal_type, intervention.disease_stage, payload, true)
        .then(result => { if (requestId === calculationRequest.current) setGuidance(result.guidance ?? null); })
        .catch(error => { if (requestId === calculationRequest.current) setGoalError(error instanceof Error ? error.message : "Could not update guidance."); });
    }
  };

  const savePlan = async (): Promise<string | null> => {
    if (calculationDirty) {
      setEditorError("Calculate targets after changing the goal or calculation inputs before saving.");
      setTab("nd");
      return null;
    }
    const targetError = weightTargetError(prescription.weight_target);
    if (targetError) { setEditorError(targetError); setTab('nd'); return null; }
    if (!intervention?.goal_type) {
      setEditorError("Select an intervention goal before saving.");
      setTab("nd");
      return null;
    }
    const guidanceChanged = educationNotes !== (intervention?.education_notes ?? "") || counselingGoals !== (intervention?.counseling_goals ?? "") || barriers !== (intervention?.barriers ?? "") || strategies !== (intervention?.strategies ?? "");
    if (guidanceChanged && guidanceCharacters > INTERVENTION_GUIDANCE_TOTAL_MAX) {
      setEditorError(`Shorten education and counseling guidance by ${guidanceCharacters - INTERVENTION_GUIDANCE_TOTAL_MAX} characters before saving.`);
      return null;
    }

    setEditorError(null);
    setSaving(true);
    try {
      const persist = selectedPlanId ? (id: string, payload: Partial<Intervention>) => updateIntervention(id, selectedPlanId, payload) : createIntervention;
      const created = await persist(ncpId, {
        nutrition_support: intervention.nutrition_support ?? assessmentContext?.nutrition_support ?? undefined,
        formula_items: formulaItemsPayload(intervention.formula_items ?? []) as unknown as Intervention['formula_items'],
        ...calculationPayload(prescription),
        goal_type: intervention.goal_type,
        disease_stage: intervention.disease_stage,
        energy_kcal: prescription.energy_kcal ? parseFloat(prescription.energy_kcal) : null,
        protein_g:   prescription.protein_g   ? parseFloat(prescription.protein_g)   : null,
        carbs_g:     prescription.carbs_g     ? parseFloat(prescription.carbs_g)     : null,
        fat_g:       prescription.fat_g       ? parseFloat(prescription.fat_g)       : null,
        fluid_ml:    prescription.fluid_ml    ? parseFloat(prescription.fluid_ml)    : null,
        micronutrient_limits: prescription.micronutrient_limits,
        nutrient_operators: prescription.nutrient_operators ?? {},
        weight_target: prescription.weight_target
          ? Object.fromEntries(Object.entries(prescription.weight_target).map(([key, value]) => [key, Number(value)])) : undefined,
        displayed_nutrients: microKeys(prescription.displayed_nutrients),
        education_notes: educationNotes || null,
        counseling_goals: counselingGoals || null,
        barriers: barriers || null,
        strategies: strategies || null,
      } as Partial<Intervention>);
      applyIntervention(created);
      setSelectedPlanId(created.id);
      setNewlySavedInterventionId(created.id);
      setLatestPlanId(created.id);
      setEditorMode("edit");
      markLocalNavigation(`plan=${created.id}`);
      router.replace(`?plan=${created.id}`);
      setPlansPage(1);
      try {
        const refreshed = await fetchInterventionPlans(ncpId, 1);
        setPlans(refreshed.data);
        setPlansMeta(refreshed.meta);
      } catch {
        setPlansError("The intervention was saved. Reload the Plans list to refresh it.");
      }
      return created.id;
    } catch (err) {
      setEditorError(err instanceof Error ? err.message : "Failed to save intervention plan.");
      return null;
    } finally { setSaving(false); }
  };

  const guard = useUnsavedChanges({ dirty, onSave: async () => Boolean(await savePlan()) });
  const handleChangePatient = () => guard.requestLeave(() => router.push("/ncp/patients"));

  const startNewPlan = async () => {
    setTab("nd");
    markLocalNavigation("mode=new");
    router.push("?mode=new");
    try {
      await prepareNewPlan(await fetchIntervention(ncpId));
    } catch (error) {
      setEditorError(error instanceof Error ? error.message : "Failed to prepare a new plan.");
    }
  };

  const selectPlan = async (planId: string) => {
    setPlansLoading(true);
    setEditorError(null);
    try {
      applyIntervention(await fetchInterventionPlan(ncpId, planId));
      setSelectedPlanId(planId);
      setEditorMode(planId === latestPlanId ? "edit" : "readonly");
      setTab("nd");
      markLocalNavigation(`plan=${planId}`);
      router.push(`?plan=${planId}`);
    } catch (error) {
      setPlansError(error instanceof Error ? error.message : "Failed to load intervention plan.");
    } finally {
      setPlansLoading(false);
    }
  };

  const cancelNewPlan = async () => {
    setEditorError(null);
    setNewlySavedInterventionId(null);
    try {
      const latest = await fetchIntervention(ncpId);
      if (latest) {
        applyIntervention(latest);
        setSelectedPlanId(latest.id);
        setLatestPlanId(latest.id);
        setEditorMode("edit");
        markLocalNavigation(`plan=${latest.id}`);
        router.replace(`?plan=${latest.id}`);
        setTab("plans");
      } else {
        applyIntervention(null);
        setSelectedPlanId(null);
        setTab("plans");
        markLocalNavigation("");
        router.replace("?");
      }
    } catch (error) {
      setEditorError(error instanceof Error ? error.message : "Failed to cancel the new plan.");
    }
  };

  const goalLabel = goalLabelFor(intervention?.goal_type);
  const stageLabel = typeLabelFor(intervention?.goal_type, intervention?.disease_stage);
  const requiredMicros: string[] = [];
  const isPediatric = (prescription.calculation_snapshot?.inputs.ageYears ?? ageAtMeasurement ?? patientMetrics?.ageYears ?? 18) < 18;
  const availableGoal = goalsForPatient(isPediatric).find(goal => goal.value === intervention?.goal_type);
  const calculationBlockedReason = intervention?.goal_type && (!availableGoal ||
    (availableGoal.stages ? !availableGoal.stages.some(stage => stage.value === intervention.disease_stage) : Boolean(intervention.disease_stage)))
    ? "Choose a current goal and type to recalculate. Saved targets remain editable." : undefined;
  const calculationTrace = intervention?.goal_type
    ? buildPrescriptionCalculationTrace({
        goalType: intervention.goal_type, stage: intervention.disease_stage, goalLabel, stageLabel,
        prescription, snapshot: prescription.calculation_snapshot,
        clinicalNotes: guidance?.education_notes ? [guidance.education_notes] : availableGoal ? [availableGoal.description] : [],
      }) : null;

  if (isPlaceholder) return <PlaceholderState />;
  if (!loading && !workflowLoading && workflowBlock) {
    const routeMismatch = workflowBlock.includes("does not belong");
    const nextHref = routeMismatch
      ? `/ncp/patients/${patientId}`
      : workflowBlock.includes("diagnosis")
        ? `/ncp/${patientId}/diagnosis/${ncpId}`
        : `/ncp/${patientId}/assessment/${ncpId}`;

    return (
      <div className="space-y-6 font-sans">
        <NcpBreadcrumb step="Nutrition Intervention" />
        <NcpPatientHeader
          patient={patient}
          ncpId={ncpId}
          physician={patient?.physician}
          riskScore={assessmentContext?.risk_score ?? assessmentContext?.computed_risk_score}
          foodDetails={[...allergens, ...foodDislikes, dietaryRestrictions]}
          interventionGoal={intervention?.goal_type}
          medicalDiagnosis={patient?.medical_diagnosis}
          onChangePatientClick={handleChangePatient}
          onBeforeUnselectPatient={guard.requestLeave}
        />
        <div className="mx-auto max-w-2xl rounded-none border-0 bg-transparent p-6 text-center shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-12 sm:shadow-sm">
          <div className="p-3.5 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
            <Lock className="h-8 w-8" />
          </div>
          <h3 className="text-base font-bold text-warm-800 mt-4 uppercase tracking-wider">Prior Step Required</h3>
          <p className="text-sm text-warm-500 mt-2 leading-relaxed">{workflowBlock}</p>
          <Link href={nextHref} className="inline-flex mt-6 px-4 py-2.5 bg-forest-900 hover:bg-forest-900 text-white text-sm font-bold uppercase tracking-wider rounded-lg transition-colors">
            {routeMismatch ? "Return to Patient" : "Continue Required Step"}
          </Link>
        </div>
      </div>
    );
  }
  if (loading || workflowLoading) return (
    <div className="flex items-center justify-center h-48 text-sm text-warm-400">Loading intervention…</div>
  );

  return (
    <div className="space-y-0 font-sans">
      {guard.dialog}
      {/* Breadcrumb + header */}
      <div className="space-y-4 mb-4">
        <NcpBreadcrumb step="Nutrition Intervention" />
        <NcpPatientHeader
          patient={patient}
          ncpId={ncpId}
          physician={patient?.physician}
          riskScore={assessmentContext?.risk_score ?? assessmentContext?.computed_risk_score}
          foodDetails={[...allergens, ...foodDislikes, dietaryRestrictions]}
          interventionGoal={intervention?.goal_type}
          medicalDiagnosis={patient?.medical_diagnosis}
          onChangePatientClick={handleChangePatient}
          onBeforeUnselectPatient={guard.requestLeave}
        />
        <div className="border-b border-warm-200 pb-4">
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight">
            Nutrition Intervention
          </h2>
          {dirty && <p className="mt-1 text-xs font-semibold text-amber-700">Unsaved changes</p>}
        </div>
      </div>

      {/* Tab bar */}
      <NcpSectionTabs
        items={TABS}
        value={tab}
        onChange={setTab}
        ariaLabel="Intervention sections"
        idPrefix="intervention"
        className="mb-5 w-full"
      />

      {/* Tab content */}
      <div id={`intervention-panel-${tab}`} role="tabpanel" aria-labelledby={`intervention-tab-${tab}`} className="pt-5 space-y-6">
        {tab === "plans" && (
          <InterventionPlansTab
            plans={plans}
            meta={plansMeta}
            page={plansPage}
            selectedPlanId={selectedPlanId}
            latestPlanId={latestPlanId}
            loading={plansLoading}
            error={plansError}
            onCreate={() => guard.requestLeave(() => void startNewPlan())}
            onSelect={(id) => guard.requestLeave(() => void selectPlan(id))}
            onDelete={async (id) => {
              await deleteIntervention(ncpId, id);
              const [result, latest] = await Promise.all([fetchInterventionPlans(ncpId, 1), fetchIntervention(ncpId)]);
              setPlans(result.data); setPlansMeta(result.meta); setPlansPage(1); setLatestPlanId(latest?.id ?? null);
              if (selectedPlanId === id) {
                applyIntervention(latest); setSelectedPlanId(latest?.id ?? null); setEditorMode('edit');
                markLocalNavigation(latest ? `plan=${latest.id}` : 'mode=new');
                router.replace(latest ? `?plan=${latest.id}` : '?mode=new');
              } else if (selectedPlanId === latest?.id) setEditorMode('edit');
            }}
            onPageChange={setPlansPage}
          />
        )}

        <div hidden={tab !== "nd"}>
          <InterventionPlanEditor
            mode={editorMode}
            plan={intervention}
            saving={saving}
            error={editorError}
            onSave={savePlan}
            onCancel={cancelNewPlan}
          >
          {/* Food / Nutrient Delivery */}
            <div hidden={tab !== "nd"} className="space-y-6">
            {/* [A] Goal selector */}
            <div className="rounded-none border-0 bg-transparent p-0 shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-5 sm:shadow-sm">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Intervention Goal</h3>
                {editorMode === "edit" && (
                  <Button variant="ghost" onClick={() => setGoalModalOpen(true)} className="px-3 py-1.5 text-xs">
                    {intervention?.goal_type ? "Change Goal" : "Set Goal"}
                  </Button>
                )}
              </div>
              {goalError && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-2">{goalError}</p>
              )}
              {calculationWarning && (
                <p className="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-2">{calculationWarning}</p>
              )}
              {intervention?.goal_type ? (
                <div className="px-4 py-3 border border-warm-200 rounded-xl">
                  <p className="text-sm font-bold text-warm-800">{goalLabel}</p>
                  {stageLabel && <p className="text-xs text-warm-600">{stageLabel}</p>}
                </div>
              ) : (
                <p className="text-sm text-warm-500">No goal selected.</p>
              )}
              {intervention?.goal_type === "liver_disease" && !isPediatric && <div className="mt-3">
                <label htmlFor="nutrition-status" className="mb-1 block text-sm font-semibold text-warm-700">Nutritional status</label>
                <select id="nutrition-status" disabled={editorMode === "readonly"} value={prescription.calculation_options?.nutrition_status ?? "unconfirmed"}
                  onChange={event => handlePrescriptionChange({ ...prescription, calculation_options: { ...prescription.calculation_options,
                    nutrition_status: event.target.value as "adequate" | "malnutrition_or_muscle_loss" | "unconfirmed" } })}
                  className="w-full rounded-lg border border-warm-200 bg-white px-3 py-2 text-base">
                  <option value="unconfirmed">Select assessed status</option><option value="adequate">No identified malnutrition or muscle loss</option>
                  <option value="malnutrition_or_muscle_loss">Malnutrition or muscle loss</option>
                </select>
              </div>}
              {!isPediatric && !["none", ""].includes(patientMetrics?.pregnancyLactationStatus ?? "none") &&
                ["renal_diet", "high_protein", "liver_disease"].includes(intervention?.goal_type ?? "") && <label className="mt-3 flex items-start gap-2 text-sm text-warm-700">
                  <input type="checkbox" disabled={editorMode === "readonly"} checked={prescription.calculation_options?.maternal_confirmed ?? false}
                    onChange={event => handlePrescriptionChange({ ...prescription, calculation_options: { ...prescription.calculation_options, maternal_confirmed: event.target.checked } })} />
                  Maternal additions reviewed for this clinical goal
                </label>}
            </div>

            {/* [B] Prescription */}
            <NutritionPrescriptionForm
              values={prescription}
              defaultWeightTarget={editorMode === 'edit' && patientMetrics?.isAdult ? Math.round(calcIBW(patientMetrics.heightCm, patientMetrics.sex) * 10) / 10 : null}
              onChange={handlePrescriptionChange}
              goalType={intervention?.goal_type}
              calculationBlockedReason={calculationBlockedReason}
              calculationDirty={calculationDirty}
              onCalculate={() => { if (intervention?.goal_type) void calculateTargets(intervention.goal_type, intervention.disease_stage, prescription); }}
              onSave={savePlan}
              saving={saving}
              note={prescNote}
              requiredMicros={requiredMicros}
              goalLabel={goalLabel}
              calculationTrace={calculationTrace}
              readOnly={editorMode === "readonly"}
              showSave={false}
            />

            {/* [C] Recommend / Avoid */}
            {intervention?.goal_type && (intervention.nutrition_support ?? assessmentContext?.nutrition_support) === 'oral' && (
              <div className="space-y-3 rounded-none border-0 bg-transparent p-0 shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-5 sm:shadow-sm">
                <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Food Recommendations</h3>
                <RecommendAvoidPanel guide={guidance?.foods} allergens={allergens} isYoungInfant={isPediatric && (prescription.calculation_snapshot?.inputs.ageMonths ?? (prescription.calculation_snapshot?.inputs.ageYears ?? patientMetrics?.ageYears ?? 18) * 12) < 6} />
              </div>
            )}

            {/* [D] Meal Plan */}
              <p className="text-sm font-semibold text-warm-700">Nutrition support: {nutritionSupportLabel(intervention?.nutrition_support ?? assessmentContext?.nutrition_support)}</p>
              {(intervention?.nutrition_support ?? assessmentContext?.nutrition_support) === 'tube_feeding' ? <TubeFormulaPlanner items={intervention?.formula_items ?? []} selectedMicros={microKeys(prescription.displayed_nutrients)} readOnly={editorMode !== 'edit'} onChange={items => { setIntervention(current => current ? { ...current, formula_items: items } : current); setDirty(true); }} onSavePlan={savePlan} /> : (intervention?.nutrition_support ?? assessmentContext?.nutrition_support) === 'no_food_menu' ? <p className="text-sm text-warm-600">No food menu is planned for this intervention.</p> : <MealPlanSection
                ncpId={ncpId}
                interventionPlanId={selectedPlanId ?? ""}
                newlySavedInterventionId={newlySavedInterventionId}
                readOnly={editorMode !== "edit" && selectedPlanId !== latestPlanId}
                onSaveIntervention={editorMode === "edit" ? savePlan : undefined}
                prescriptionTargets={{
                  energy:   parseFloat(prescription.energy_kcal) || 0,
                  protein:  parseFloat(prescription.protein_g)   || 0,
                  carbs:    parseFloat(prescription.carbs_g)     || 0,
                  fat:      parseFloat(prescription.fat_g)       || 0,
                  fluid:    parseFloat(prescription.fluid_ml)    || 0,
                }}
                foodDislikes={foodDislikes}
                allergens={allergens}
                displayedMicros={microKeys(prescription.displayed_nutrients)}
                micronutrientLimits={prescription.micronutrient_limits}
                nutrientOperators={prescription.nutrient_operators}
                interventionGoal={intervention?.goal_type}
              />}
          </div>
          </InterventionPlanEditor>
        </div>

        {/* Education */}
        {tab === "education" && (
          <EducationTab
            value={educationNotes}
            suggestion={guidance?.education_notes}
            onChange={(v) => { setEducationNotes(v); setDirty(true); }}
            onSave={savePlan}
            saving={saving}
            readOnly={editorMode === "readonly"}
            showSave={false}
            totalCharacters={guidanceCharacters}
          />
        )}

        {/* Counseling */}
        {tab === "counseling" && (
          <CounselingTab
            goals={counselingGoals} barriers={barriers} strategies={strategies}
            goalsSuggestion={guidance?.counseling_goals} strategiesSuggestion={guidance?.strategies}
            onChange={(field, val) => {
              setDirty(true);
              if (field === 'counseling_goals') { setCounselingGoals(val); }
              if (field === 'barriers') setBarriers(val);
              if (field === 'strategies') { setStrategies(val); }
            }}
            onSave={savePlan}
            saving={saving}
            readOnly={editorMode === "readonly"}
            showSave={false}
            totalCharacters={guidanceCharacters}
          />
        )}

      </div>

      {/* Goal selector modal */}
      {goalModalOpen && editorMode === "edit" && (
        <GoalSelectorModal
          onConfirm={handleGoalConfirm}
          isPediatric={isPediatric}
          onClose={() => setGoalModalOpen(false)}
          initialGoal={intervention?.goal_type}
          initialStage={intervention?.disease_stage}
        />
      )}
    </div>
  );
}

function PlaceholderState() {
  return (
    <div className="space-y-6 font-sans">
      <div className="border-b border-warm-200 pb-5">
        <h2 className="text-xl font-extrabold text-warm-900 tracking-tight">Nutrition Intervention</h2>
      </div>
      <div className="bg-white border border-warm-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
          <User className="h-8 w-8" />
        </div>
        <h3 className="text-base font-bold text-warm-800 mt-4 uppercase tracking-wider">No Patient Selected</h3>
        <p className="text-sm text-warm-500 mt-2 leading-relaxed">Navigate to the NCP Patients directory and select a patient.</p>
        <div className="mt-6">
          <Link href="/ncp/patients"
            className="inline-flex px-4 py-2.5 bg-forest-900 hover:bg-forest-900 text-white text-sm font-bold uppercase tracking-wider rounded-lg transition-colors">
            Go to Patients Directory
          </Link>
        </div>
      </div>
    </div>
  );
}
