"use client";

import React, { Suspense, use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { User, Lock } from "lucide-react";
import {
  fetchIntervention, fetchInterventionPlan, fetchInterventionPlans, createIntervention, autofillIntervention,
  AutofillError, Intervention, type AutofillResult, type InterventionPlanSummary,
} from "@/services/interventionService";
import type { PaginationMeta } from "@/components/ui/Pagination";
import { EDUCATION_TEMPLATES } from "@/lib/educationTemplates";
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
  autofillPrescription, Prescription, PatientMetrics, ACTIVITY_FACTORS, microKeys,
} from "@/lib/nutritionCalculations";
import {
  buildGoalPrescriptionForm,
  emptyPrescriptionForm,
  nutrientKeysWithValues,
  type PrescriptionFormState,
} from "@/lib/interventionGoalState";
import { buildPrescriptionCalculationTrace } from "@/lib/prescriptionCalculationTrace";
import GoalSelectorModal, { GOALS } from "./_components/GoalSelectorModal";
import { Button } from "@/components/ui/Button";
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
    energy_kcal: iv.energy_kcal != null ? String(iv.energy_kcal) : "",
    protein_g:   iv.protein_g   != null ? String(iv.protein_g)   : "",
    carbs_g:     iv.carbs_g     != null ? String(iv.carbs_g)     : "",
    fat_g:       iv.fat_g       != null ? String(iv.fat_g)       : "",
    fluid_ml:    iv.fluid_ml    != null ? String(iv.fluid_ml)    : "",
    micronutrient_limits: micronutrientLimits,
    displayed_nutrients: nutrientKeysWithValues(micronutrientLimits),
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
  return {
    ...(latest ?? emptyIntervention()),
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
  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const [tab, setTab]                           = useState<Tab>("plans");
  const [intervention, setIntervention]         = useState<Intervention | null>(null);
  const [plans, setPlans]                       = useState<InterventionPlanSummary[]>([]);
  const [plansMeta, setPlansMeta]               = useState<PaginationMeta | null>(null);
  const [plansPage, setPlansPage]               = useState(1);
  const [plansLoading, setPlansLoading]         = useState(false);
  const [plansError, setPlansError]             = useState<string | null>(null);
  const [selectedPlanId, setSelectedPlanId]     = useState<string | null>(null);
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
    setPrescNote(undefined);
    setCalculationWarning(null);
    setGoalError(null);
    setDirty(false);
  }, []);

  const prepareNewPlan = useCallback(async (latest: Intervention | null) => {
    const draft = newPlanDraft(latest);
    applyIntervention(draft);
    setSelectedPlanId(null);
    setEditorMode("edit");
    setEditorError(null);

    if (!draft.goal_type) return;

    try {
      const filled = await autofillIntervention(ncpId, draft.goal_type, draft.disease_stage);
      setIntervention((current) => current ? {
        ...current,
        source_monitoring_id: filled.source_monitoring_id ?? null,
        source_monitoring_date: filled.source_monitoring_date ?? null,
      } : current);
      setPrescription(buildGoalPrescriptionForm(draft.goal_type, filled));
      setPrescNote(prescriptionNote(filled));
    } catch (error) {
      const missing = error instanceof AutofillError ? error.missingFields : [];
      const missingText = missing.length ? ` Missing: ${missing.map(formatMissingField).join(", ")}.` : "";
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

      applyIntervention(await fetchInterventionPlan(ncpId, planId));
      setSelectedPlanId(planId);
      setEditorMode("readonly");
    } catch (error) {
      setPlansError(error instanceof Error ? error.message : "Failed to load intervention plans.");
    } finally {
      setPlansLoading(false);
      setLoading(false);
    }
  }, [applyIntervention, ncpId, plansPage, prepareNewPlan, searchParams]);

  // Warn on browser unload (refresh / close / hard nav) when there are unsaved edits.
  useEffect(() => {
    if (!dirty) return;
    const onBeforeUnload = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = ""; // required for the native "Leave site?" prompt
    };
    window.addEventListener("beforeunload", onBeforeUnload);
    return () => window.removeEventListener("beforeunload", onBeforeUnload);
  }, [dirty]);

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

      // Derive age from patient DOB
      let ageYears = 30;
      if (p?.dob) {
        const b = new Date(p.dob);
        const now = new Date();
        let age = now.getFullYear() - b.getFullYear();
        const m = now.getMonth() - b.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < b.getDate())) age -= 1;
        ageYears = Math.max(0, age);
      }
      const sex = (p?.sex as "Male" | "Female") ?? "Male";

      const calculationWeight = effective?.edema_present ? effective?.dry_weight_kg : effective?.weight;
      if (calculationWeight && effective?.height) {
        const palKey = effective.physical_activity_level ?? "sedentary";
        const activityFactor = ACTIVITY_FACTORS[palKey]?.factor ?? 1.2;
        setPatientMetrics({
          weightKg: parseFloat(String(calculationWeight)),
          heightCm: parseFloat(String(effective.height)),
          ageYears: effective && "age_years" in effective && effective.age_years != null ? effective.age_years : ageYears,
          sex: effective && "sex" in effective && effective.sex ? effective.sex as "Male" | "Female" : sex,
          isAdult: (effective && "age_years" in effective && effective.age_years != null ? effective.age_years : ageYears) >= 18,
          activityFactor,
          // PDRI pregnancy/lactation add-on — keeps the live preview in step with
          // the backend engine (which reads the same assessment field).
          pregnancyLactationStatus:
            effective.pregnancy_lactation_status ?? "none",
        });
      }
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
    if (!isPlaceholder) {
      loadPlans();
      loadMetrics();
    }
  }, [isPlaceholder, loadPlans, loadMetrics]);

  const canLeavePatient = () => !dirty || window.confirm("You have unsaved changes. Leave without saving?");
  const handleChangePatient = () => {
    if (!canLeavePatient()) return;
    router.push("/ncp/patients");
  };

  const handleGoalConfirm = async (goalType: string, stage: string | null) => {
    setGoalError(null);
    setCalculationWarning(null);
    setGoalModalOpen(false);
    if (editorMode !== "edit") return;

    setIntervention((current) => ({
      ...(current ?? emptyIntervention()),
      goal_type: goalType,
      disease_stage: stage,
    }));
    setDirty(true);

    let preview: Prescription | null = null;
    let form = buildGoalPrescriptionForm(goalType, null);
    if (patientMetrics && patientMetrics.pregnancyLactationStatus !== "pregnant_unspecified") {
      preview = autofillPrescription(goalType, stage, patientMetrics);
      form = buildGoalPrescriptionForm(goalType, preview);
      setPrescNote(prescriptionNote(preview));
    }
    setPrescription(form);

    if (!educationNotes.trim() && EDUCATION_TEMPLATES[goalType]) {
      setEducationNotes(EDUCATION_TEMPLATES[goalType]);
    }

    setSaving(true);
    try {
      const authoritative = await autofillIntervention(ncpId, goalType, stage);
      setPrescription(buildGoalPrescriptionForm(goalType, authoritative));
      setPrescNote(prescriptionNote(authoritative));
      setIntervention((current) => current ? {
        ...current,
        source_monitoring_id: authoritative.source_monitoring_id ?? null,
        source_monitoring_date: authoritative.source_monitoring_date ?? null,
      } : current);
    } catch (err) {
      const missing = err instanceof AutofillError ? err.missingFields : [];
      const missingText = missing.length ? ` Missing: ${missing.map(formatMissingField).join(", ")}.` : "";
      setCalculationWarning(`Prescription calculation incomplete.${missingText}`);
      if (!preview) setPrescNote("Complete the required assessment values before calculation.");
    } finally {
      setSaving(false);
    }
  };

  const savePlan = async () => {
    if (!intervention?.goal_type) {
      setEditorError("Select an intervention goal before saving.");
      setTab("nd");
      return;
    }
    if (guidanceCharacters > INTERVENTION_GUIDANCE_TOTAL_MAX) {
      setEditorError(`Shorten education and counseling guidance by ${guidanceCharacters - INTERVENTION_GUIDANCE_TOTAL_MAX} characters before saving.`);
      return;
    }

    setEditorError(null);
    setSaving(true);
    try {
      const created = await createIntervention(ncpId, {
        goal_type: intervention.goal_type,
        disease_stage: intervention.disease_stage,
        energy_kcal: prescription.energy_kcal ? parseFloat(prescription.energy_kcal) : null,
        protein_g:   prescription.protein_g   ? parseFloat(prescription.protein_g)   : null,
        carbs_g:     prescription.carbs_g     ? parseFloat(prescription.carbs_g)     : null,
        fat_g:       prescription.fat_g       ? parseFloat(prescription.fat_g)       : null,
        fluid_ml:    prescription.fluid_ml    ? parseFloat(prescription.fluid_ml)    : null,
        micronutrient_limits: prescription.micronutrient_limits,
        displayed_nutrients: nutrientKeysWithValues(prescription.micronutrient_limits),
        education_notes: educationNotes || null,
        counseling_goals: counselingGoals || null,
        barriers: barriers || null,
        strategies: strategies || null,
      } as Partial<Intervention>);
      applyIntervention(created);
      setSelectedPlanId(created.id);
      setLatestPlanId(created.id);
      setEditorMode("readonly");
      router.replace(`?plan=${created.id}`);
      setPlansPage(1);
      const refreshed = await fetchInterventionPlans(ncpId, 1);
      setPlans(refreshed.data);
      setPlansMeta(refreshed.meta);
    } catch (err) {
      setEditorError(err instanceof Error ? err.message : "Failed to save intervention plan.");
    } finally { setSaving(false); }
  };

  const startNewPlan = async () => {
    if (dirty && !window.confirm("Discard unsaved changes?")) return;
    setTab("nd");
    router.push("?mode=new");
    try {
      await prepareNewPlan(await fetchIntervention(ncpId));
    } catch (error) {
      setEditorError(error instanceof Error ? error.message : "Failed to prepare a new plan.");
    }
  };

  const selectPlan = async (planId: string) => {
    if (dirty && !window.confirm("Discard unsaved changes?")) return;
    setPlansLoading(true);
    setEditorError(null);
    try {
      applyIntervention(await fetchInterventionPlan(ncpId, planId));
      setSelectedPlanId(planId);
      setEditorMode("readonly");
      setTab("nd");
      router.push(`?plan=${planId}`);
    } catch (error) {
      setPlansError(error instanceof Error ? error.message : "Failed to load intervention plan.");
    } finally {
      setPlansLoading(false);
    }
  };

  const cancelNewPlan = async () => {
    setEditorError(null);
    try {
      const latest = await fetchIntervention(ncpId);
      if (latest) {
        applyIntervention(latest);
        setSelectedPlanId(latest.id);
        setLatestPlanId(latest.id);
        setEditorMode("readonly");
        router.replace(`?plan=${latest.id}`);
      } else {
        applyIntervention(null);
        setSelectedPlanId(null);
        setTab("plans");
        router.replace("?");
      }
    } catch (error) {
      setEditorError(error instanceof Error ? error.message : "Failed to cancel the new plan.");
    }
  };

  const goalLabel  = GOALS.find((g) => g.value === intervention?.goal_type)?.label;
  const stageLabel = GOALS
    .find((g) => g.value === intervention?.goal_type)
    ?.stages?.find((s) => s.value === intervention?.disease_stage)?.label;
  const recommendedPrescription = intervention?.goal_type && intervention.goal_type !== "custom" && patientMetrics
    && patientMetrics.pregnancyLactationStatus !== "pregnant_unspecified"
    ? autofillPrescription(intervention.goal_type, intervention.disease_stage, patientMetrics)
    : null;
  const requiredMicros = recommendedPrescription
    ? buildGoalPrescriptionForm(intervention?.goal_type ?? "", recommendedPrescription).displayed_nutrients
    : [];
  const calculationTrace = intervention?.goal_type && patientMetrics
    && patientMetrics.pregnancyLactationStatus !== "pregnant_unspecified"
    ? buildPrescriptionCalculationTrace({
        goalType: intervention.goal_type,
        stage: intervention.disease_stage,
        goalLabel,
        stageLabel,
        metrics: patientMetrics,
        prescription,
        requiredMicros,
      })
    : null;

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
          onBeforeUnselectPatient={canLeavePatient}
        />
        <div className="bg-white border border-warm-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
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
          onBeforeUnselectPatient={canLeavePatient}
        />
        <div className="border-b border-warm-200 pb-4">
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight">
            Nutrition Intervention
          </h2>
          {dirty && <p className="mt-1 text-xs font-semibold text-amber-700">Unsaved changes</p>}
        </div>
      </div>

      {/* Tab bar */}
      <div className="flex flex-wrap border-b border-warm-200 mb-5">
        {TABS.map(({ key, label }) => (
          <button key={key} onClick={() => setTab(key)}
            className={`px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 whitespace-nowrap transition-colors cursor-pointer ${
              tab === key ? "border-emerald-600 text-emerald-700" : "border-transparent text-warm-400 hover:text-warm-600"
            }`}>
            {label}
          </button>
        ))}
      </div>

      {/* Tab content */}
      <div className="pt-5 space-y-6">
        {tab === "plans" && (
          <InterventionPlansTab
            plans={plans}
            meta={plansMeta}
            page={plansPage}
            selectedPlanId={selectedPlanId}
            loading={plansLoading}
            error={plansError}
            onCreate={startNewPlan}
            onSelect={selectPlan}
            onPageChange={setPlansPage}
          />
        )}

        {tab !== "plans" && (
          <InterventionPlanEditor
            mode={editorMode}
            plan={intervention}
            saving={saving}
            error={editorError}
            onSave={savePlan}
            onCancel={cancelNewPlan}
          >
          {/* Food / Nutrient Delivery */}
          {tab === "nd" && (
            <div className="space-y-6">
            {/* [A] Goal selector */}
            <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm">
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
            </div>

            {/* [B] Prescription */}
            <NutritionPrescriptionForm
              values={prescription}
              onChange={(v) => { setPrescription(v); setDirty(true); }}
              onSave={savePlan}
              saving={saving}
              note={prescNote}
              requiredMicros={requiredMicros}
              goalLabel={goalLabel}
              calculationTrace={editorMode === "edit" ? calculationTrace : null}
              readOnly={editorMode === "readonly"}
              showSave={false}
            />

            {/* [C] Recommend / Avoid */}
            {intervention?.goal_type && (
              <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm space-y-3">
                <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Food Recommendations</h3>
                <RecommendAvoidPanel goalType={intervention.goal_type} />
              </div>
            )}

            {/* [D] Meal Plan */}
            {selectedPlanId && (
              <MealPlanSection
                ncpId={ncpId}
                interventionPlanId={selectedPlanId}
                readOnly={selectedPlanId !== latestPlanId}
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
                interventionGoal={intervention?.goal_type}
              />
            )}
          </div>
        )}

        {/* Education */}
        {tab === "education" && (
          <EducationTab
            value={educationNotes}
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
            onChange={(field, val) => {
              setDirty(true);
              if (field === 'counseling_goals') setCounselingGoals(val);
              if (field === 'barriers') setBarriers(val);
              if (field === 'strategies') setStrategies(val);
            }}
            onSave={savePlan}
            saving={saving}
            readOnly={editorMode === "readonly"}
            showSave={false}
            totalCharacters={guidanceCharacters}
          />
        )}

          </InterventionPlanEditor>
        )}
      </div>

      {/* Goal selector modal */}
      {goalModalOpen && editorMode === "edit" && (
        <GoalSelectorModal
          onConfirm={handleGoalConfirm}
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
