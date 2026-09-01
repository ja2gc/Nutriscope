"use client";

import React, { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Salad, User, Settings2, CheckCircle2, Lock } from "lucide-react";
import {
  fetchIntervention, createIntervention, updateIntervention, autofillIntervention,
  AutofillError, Intervention, type AutofillResult,
} from "@/services/interventionService";
import { EDUCATION_TEMPLATES } from "@/lib/educationTemplates";
import { fetchAssessment, type Assessment } from "@/services/assessmentService";
import {
  fetchPatientById,
  fetchPatientNcpRecords,
  ncpRecordMatchesRoute,
  type Patient,
} from "@/services/patientService";
import { fetchDiagnoses } from "@/services/diagnosisService";
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
import GoalPlanningTab from "./_components/GoalPlanningTab";
import EncounterContextTab from "./_components/EncounterContextTab";
import MealPlanSection from "./_components/MealPlanSection";
import NcpPatientHeader from "../../../_components/NcpPatientHeader";

type Tab = "nd" | "education" | "counseling" | "goals" | "encounter";
type PageParams = { patientId: string; ncpId: string };

const TABS: { key: Tab; label: string }[] = [
  { key: "nd",         label: "Food / Nutrient Delivery" },
  { key: "education",  label: "Education" },
  { key: "counseling", label: "Counseling" },
  { key: "goals",      label: "Goal Planning" },
  { key: "encounter",  label: "Encounter Context" },
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

export default function InterventionPage({ params }: { params: Promise<PageParams> }) {
  const { patientId, ncpId } = use(params);
  const router = useRouter();
  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const [tab, setTab]                           = useState<Tab>("nd");
  const [intervention, setIntervention]         = useState<Intervention | null>(null);
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
  const [assessmentContext, setAssessmentContext] = useState<Assessment | null>(null);
  const [goalError, setGoalError]               = useState<string | null>(null);
  const [calculationWarning, setCalculationWarning] = useState<string | null>(null);

  const [educationNotes, setEducationNotes]   = useState("");
  const [counselingGoals, setCounselingGoals] = useState("");
  const [barriers, setBarriers]               = useState("");
  const [strategies, setStrategies]           = useState("");
  const [sessionType, setSessionType]         = useState("");
  const [nextFollowup, setNextFollowup]       = useState("");

  const loadIntervention = useCallback(async () => {
    setLoading(true);
    try {
      const iv = await fetchIntervention(ncpId);
      if (iv) {
        setIntervention(iv);
        setPrescription(interventionToForm(iv));
        setEducationNotes(iv.education_notes ?? "");
        setCounselingGoals(iv.counseling_goals ?? "");
        setBarriers(iv.barriers ?? "");
        setStrategies(iv.strategies ?? "");
        setSessionType(iv.session_type ?? "");
        setNextFollowup(iv.next_followup_date ?? "");
      }
      setDirty(false); // fresh server state = no unsaved changes
    } finally { setLoading(false); }
  }, [ncpId]);

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
      const [assessment, patientData, diagnoses, ncpRecords] = await Promise.allSettled([
        fetchAssessment(ncpId),
        fetchPatientById(patientId),
        fetchDiagnoses(ncpId),
        fetchPatientNcpRecords(patientId, 1, ncpId),
      ]);

      const a = assessment.status === "fulfilled" ? assessment.value : null;
      const p = patientData.status === "fulfilled" ? patientData.value : null;
      const hasDiagnosis = diagnoses.status === "fulfilled" && diagnoses.value.length > 0;
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

      const calculationWeight = a?.edema_present ? a?.dry_weight_kg : a?.weight;
      if (calculationWeight && a?.height) {
        const palKey = a.physical_activity_level ?? "sedentary";
        const activityFactor = ACTIVITY_FACTORS[palKey]?.factor ?? 1.2;
        setPatientMetrics({
          weightKg: parseFloat(String(calculationWeight)),
          heightCm: parseFloat(String(a.height)),
          ageYears,
          sex,
          isAdult: ageYears >= 18,
          activityFactor,
          // PDRI pregnancy/lactation add-on — keeps the live preview in step with
          // the backend engine (which reads the same assessment field).
          pregnancyLactationStatus:
            (a.pregnancy_lactation_status as "none" | "pregnant" | "lactating") ?? "none",
          stressFactor: a.stress_factor != null ? parseFloat(String(a.stress_factor)) : undefined,
        });
      }
      if (a?.food_dislikes && Array.isArray(a.food_dislikes)) {
        setFoodDislikes(a.food_dislikes.map((d: string) => d.toLowerCase()));
      }
      if (a?.allergies && Array.isArray(a.allergies)) {
        setAllergens(a.allergies.map((al: string) => al.toLowerCase()));
      }
    } catch { /* assessment may not exist yet */ }
    finally { setWorkflowLoading(false); }
  }, [ncpId, patientId]);

  useEffect(() => {
    if (!isPlaceholder) {
      loadIntervention();
      loadMetrics();
    }
  }, [isPlaceholder, loadIntervention, loadMetrics]);

  const handleChangePatient = () => {
    if (dirty && !window.confirm("You have unsaved changes. Leave without saving?")) return;
    router.push("/ncp/patients");
  };

  /**
   * Ensure an Intervention row exists in the DB for this NCP record.
   * Handles 409 gracefully: if the backend says the row already exists but our
   * local state is null (e.g. loadIntervention failed silently on page mount),
   * we re-fetch and use the existing record instead of crashing.
   */
  const ensureIntervention = async (): Promise<Intervention> => {
    if (intervention) return intervention;
    try {
      const iv = await createIntervention(ncpId, {});
      setIntervention(iv);
      return iv;
    } catch (err) {
      // 409 = Intervention already exists in DB but wasn't reflected in local state.
      // Re-fetch and recover gracefully.
      if (err instanceof Error && err.message.includes("already exists")) {
        const existing = await fetchIntervention(ncpId);
        if (existing) { setIntervention(existing); return existing; }
      }
      throw err;
    }
  };

  const handleGoalConfirm = async (goalType: string, stage: string | null) => {
    setGoalError(null);
    setCalculationWarning(null);
    setGoalModalOpen(false);
    try {
      const currentIntervention = await ensureIntervention();
      setIntervention({
        ...currentIntervention,
        goal_type: goalType,
        disease_stage: stage,
      });

      // [1] Instant TS preview (frontend mirror — for responsiveness only).
      let preview: Prescription | null = null;
      let finalForm = buildGoalPrescriptionForm(goalType, null);
      if (patientMetrics) {
        preview = autofillPrescription(goalType, stage, patientMetrics);
        setPrescNote(prescriptionNote(preview));
        finalForm = buildGoalPrescriptionForm(goalType, preview);
      }
      setPrescription(finalForm);

      // Auto-populate education template if notes currently empty
      if (!educationNotes.trim() && EDUCATION_TEMPLATES[goalType]) {
        setEducationNotes(EDUCATION_TEMPLATES[goalType]);
      }

      // [2] Authoritative values come from the backend engine (Phase 2.4 source of
      //     truth). If it succeeds, persist & display the BE numbers; the TS preview
      //     above just avoids a flash of empty fields. If it fails (e.g. no assessment
      //     yet), fall back to persisting the TS preview so the goal still saves.
      setSaving(true);
      try {
        let authoritative: {
          energy_kcal: number; protein_g: number; carbs_g: number; fat_g: number; fluid_ml: number;
        } | null = preview
          ? { energy_kcal: preview.energy_kcal, protein_g: preview.protein_g, carbs_g: preview.carbs_g, fat_g: preview.fat_g, fluid_ml: preview.fluid_ml }
          : null;
        let autofillError: string | null = null;

        try {
          const be = await autofillIntervention(ncpId, goalType, stage);
          authoritative = { energy_kcal: be.energy_kcal, protein_g: be.protein_g, carbs_g: be.carbs_g, fat_g: be.fat_g, fluid_ml: be.fluid_ml };
          finalForm = buildGoalPrescriptionForm(goalType, be);
          setCalculationWarning(null);
          setPrescNote(prescriptionNote(be));
          setPrescription(finalForm);
          // Dev-only drift guard: FE preview must match the authoritative BE value.
          if (process.env.NODE_ENV !== "production" && preview) {
            for (const [k, fe, beVal] of [
              ["energy_kcal", preview.energy_kcal, be.energy_kcal],
              ["protein_g",   preview.protein_g,   be.protein_g],
              ["carbs_g",     preview.carbs_g,     be.carbs_g],
              ["fat_g",       preview.fat_g,       be.fat_g],
              ["fluid_ml",    preview.fluid_ml,    be.fluid_ml],
            ] as const) {
              if (Math.abs(Number(fe) - Number(beVal)) > 1) {
                console.warn(`[prescription drift] ${k}: FE=${fe} BE=${beVal} — frontend mirror is out of sync with the backend engine.`);
              }
            }
          }
        } catch (err) {
          // Missing required clinical inputs must not persist preview values.
          const missing = err instanceof AutofillError ? err.missingFields : [];
          if (missing.length > 0) authoritative = null;
          const missingText = missing.length ? ` Missing: ${missing.map(formatMissingField).join(", ")}.` : "";
          autofillError = err instanceof Error ? `${err.message}${missingText}` : "Failed to autofill prescription.";
          setCalculationWarning(`Goal saved. Prescription calculation incomplete.${missingText} Values may be blank or off until assessment is completed.`);
          console.warn("Backend autofill failed; using frontend preview values.", err);
        }

        if (!authoritative) {
          const updated = await updateIntervention(ncpId, {
            goal_type: goalType,
            disease_stage: stage,
          } as Partial<Intervention>);
          setIntervention(updated);
          setPrescription(interventionToForm(updated));
          setPrescNote(autofillError ?? "Complete patient demographics and assessment anthropometrics before autofill.");
          setDirty(false);
          return;
        }

        const updated = await updateIntervention(ncpId, {
          goal_type: goalType,
          disease_stage: stage,
          displayed_nutrients: finalForm.displayed_nutrients,
          micronutrient_limits: finalForm.micronutrient_limits,
          ...(authoritative ?? {}),
        } as Partial<Intervention>);
        setIntervention(updated);
        setPrescription(interventionToForm(updated));
        setDirty(false);
      } finally { setSaving(false); }
    } catch (err) {
      // Surface any failure (ensureIntervention, updateIntervention, etc.) as a
      // visible error banner rather than silently swallowing it.
      setGoalError(err instanceof Error ? err.message : "Failed to apply goal. Please try again.");
    }
  };

  const savePrescription = async () => {
    setSaving(true);
    try {
      await ensureIntervention();
      const updated = await updateIntervention(ncpId, {
        energy_kcal: prescription.energy_kcal ? parseFloat(prescription.energy_kcal) : null,
        protein_g:   prescription.protein_g   ? parseFloat(prescription.protein_g)   : null,
        carbs_g:     prescription.carbs_g     ? parseFloat(prescription.carbs_g)     : null,
        fat_g:       prescription.fat_g       ? parseFloat(prescription.fat_g)       : null,
        fluid_ml:    prescription.fluid_ml    ? parseFloat(prescription.fluid_ml)    : null,
        micronutrient_limits: prescription.micronutrient_limits,
        displayed_nutrients: nutrientKeysWithValues(prescription.micronutrient_limits),
      } as Partial<Intervention>);
      setIntervention(updated);
      setPrescription(interventionToForm(updated));
      setDirty(false);
    } catch (err) {
      setPrescNote(err instanceof Error ? err.message : "Failed to save prescription.");
    } finally { setSaving(false); }
  };

  const saveTextField = async (fields: Partial<Intervention>) => {
    setSaving(true);
    try {
      await ensureIntervention();
      const updated = await updateIntervention(ncpId, fields);
      setIntervention(updated);
      setDirty(false);
    } finally { setSaving(false); }
  };

  const goalLabel  = GOALS.find((g) => g.value === intervention?.goal_type)?.label;
  const stageLabel = GOALS
    .find((g) => g.value === intervention?.goal_type)
    ?.stages?.find((s) => s.value === intervention?.disease_stage)?.label;
  const recommendedPrescription = intervention?.goal_type && intervention.goal_type !== "custom" && patientMetrics
    ? autofillPrescription(intervention.goal_type, intervention.disease_stage, patientMetrics)
    : null;
  const requiredMicros = recommendedPrescription
    ? buildGoalPrescriptionForm(intervention?.goal_type ?? "", recommendedPrescription).displayed_nutrients
    : [];
  const calculationTrace = intervention?.goal_type && patientMetrics
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
        <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
          <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
          <span className="text-warm-300">/</span>
          <span className="text-warm-600 font-bold">Intervention</span>
        </div>
        <NcpPatientHeader
          patient={patient}
          physician={patient?.physician}
          riskScore={assessmentContext?.risk_score ?? assessmentContext?.computed_risk_score}
          foodDetails={[...allergens, ...foodDislikes, assessmentContext?.dietary_restrictions]}
          interventionGoal={intervention?.goal_type}
          medicalDiagnosis={patient?.medical_diagnosis}
          onChangePatientClick={handleChangePatient}
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
        <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
          <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors"
            onClick={(e) => {
              if (dirty && !window.confirm("You have unsaved changes. Leave without saving?")) e.preventDefault();
            }}>Directory</Link>
          <span className="text-warm-300">/</span>
          <span className="font-bold text-zinc-650">Nutrition Intervention</span>
        </div>
        <NcpPatientHeader
          patient={patient}
          physician={patient?.physician}
          riskScore={assessmentContext?.risk_score ?? assessmentContext?.computed_risk_score}
          foodDetails={[...allergens, ...foodDislikes, assessmentContext?.dietary_restrictions]}
          interventionGoal={intervention?.goal_type}
          medicalDiagnosis={patient?.medical_diagnosis}
          onChangePatientClick={handleChangePatient}
        />
        <div className="border-b border-warm-200 pb-4">
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <Salad className="h-5 w-5 text-emerald-600" />
            Step 3: Nutrition Intervention
            {dirty && (
              <span className="ml-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-xs font-bold text-amber-700 uppercase tracking-wide">
                Unsaved changes
              </span>
            )}
          </h2>
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

        {/* TAB 1 — Food / Nutrient Delivery */}
        {tab === "nd" && (
          <div className="space-y-6">
            {/* [A] Goal selector */}
            <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Intervention Goal</h3>
                <Button variant="ghost" onClick={() => setGoalModalOpen(true)} className="px-3 py-1.5 text-xs gap-1.5">
                  <Settings2 className="h-3 w-3" />
                  {intervention?.goal_type ? "Change Goal" : "Set Goal"}
                </Button>
              </div>
              {goalError && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-2">{goalError}</p>
              )}
              {calculationWarning && (
                <p className="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-2">{calculationWarning}</p>
              )}
              {intervention?.goal_type ? (
                <div className="flex items-center gap-2 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                  <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                  <div>
                    <p className="text-sm font-bold text-emerald-800">{goalLabel}</p>
                    {stageLabel && <p className="text-xs text-emerald-600">{stageLabel}</p>}
                  </div>
                </div>
              ) : (
                <p className="text-sm text-warm-400 italic">No goal set. Click &ldquo;Set Goal&rdquo; to begin.</p>
              )}
            </div>

            {/* [B] Prescription */}
            <NutritionPrescriptionForm
              values={prescription}
              onChange={(v) => { setPrescription(v); setDirty(true); }}
              onSave={savePrescription}
              saving={saving}
              note={prescNote}
              requiredMicros={requiredMicros}
              goalLabel={goalLabel}
              calculationTrace={calculationTrace}
            />

            {/* [C] Recommend / Avoid */}
            {intervention?.goal_type && (
              <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm space-y-3">
                <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Food Recommendations</h3>
                <RecommendAvoidPanel goalType={intervention.goal_type} />
              </div>
            )}

            {/* [D] Meal Plan */}
            <MealPlanSection
              ncpId={ncpId}
              prescriptionTargets={{
                energy:   parseFloat(prescription.energy_kcal) || 0,
                protein:  parseFloat(prescription.protein_g)   || 0,
                carbs:    parseFloat(prescription.carbs_g)     || 0,
                fat:      parseFloat(prescription.fat_g)       || 0,
                fluid_ml: parseFloat(prescription.fluid_ml)   || 0,
              }}
              foodDislikes={foodDislikes}
              allergens={allergens}
              displayedMicros={microKeys(prescription.displayed_nutrients)}
              micronutrientLimits={prescription.micronutrient_limits}
            />
          </div>
        )}

        {/* TAB 2 — Education */}
        {tab === "education" && (
          <EducationTab
            value={educationNotes}
            onChange={(v) => { setEducationNotes(v); setDirty(true); }}
            onSave={() => saveTextField({ education_notes: educationNotes } as Partial<Intervention>)}
            saving={saving}
          />
        )}

        {/* TAB 3 — Counseling */}
        {tab === "counseling" && (
          <CounselingTab
            goals={counselingGoals} barriers={barriers} strategies={strategies}
            onChange={(field, val) => {
              setDirty(true);
              if (field === 'counseling_goals') setCounselingGoals(val);
              if (field === 'barriers') setBarriers(val);
              if (field === 'strategies') setStrategies(val);
            }}
            onSave={() => saveTextField({
              counseling_goals: counselingGoals, barriers, strategies,
            } as Partial<Intervention>)}
            saving={saving}
          />
        )}

        {/* TAB 4 — Goal Planning */}
        {tab === "goals" && (
          <GoalPlanningTab
            goals={counselingGoals}
            energy={prescription.energy_kcal}
            protein={prescription.protein_g}
            carbs={prescription.carbs_g}
            fat={prescription.fat_g}
            displayedMicros={prescription.displayed_nutrients}
            micronutrientLimits={prescription.micronutrient_limits}
          />
        )}

        {/* TAB 5 — Encounter Context */}
        {tab === "encounter" && (
          <EncounterContextTab
            sessionType={sessionType} nextFollowup={nextFollowup}
            onChange={(field, val) => {
              setDirty(true);
              if (field === 'session_type') setSessionType(val);
              if (field === 'next_followup_date') setNextFollowup(val);
            }}
            onSave={() => saveTextField({
              session_type: sessionType, next_followup_date: nextFollowup || null,
            } as Partial<Intervention>)}
            saving={saving}
          />
        )}
      </div>

      {/* Goal selector modal */}
      {goalModalOpen && (
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
        <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
          <Salad className="h-5 w-5 text-emerald-600 animate-pulse" />
          Step 3: Nutrition Intervention
        </h2>
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
