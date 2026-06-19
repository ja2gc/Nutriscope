"use client";

import React, { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { Salad, User, Settings2, CheckCircle2 } from "lucide-react";
import {
  fetchIntervention, createIntervention, updateIntervention, autofillIntervention,
  Intervention,
} from "@/services/interventionService";
import { EDUCATION_TEMPLATES } from "@/lib/educationTemplates";
import { fetchAssessment } from "@/services/assessmentService";
import { fetchPatientById } from "@/services/patientService";
import {
  autofillPrescription, GOAL_MICRO_FLAGS, Prescription, PatientMetrics, ACTIVITY_FACTORS, microKeys, microLimitsFromRx,
} from "@/lib/nutritionCalculations";
import GoalSelectorModal, { GOALS } from "./_components/GoalSelectorModal";
import { Button } from "@/components/ui/Button";
import NutritionPrescriptionForm from "./_components/NutritionPrescriptionForm";
import RecommendAvoidPanel from "./_components/RecommendAvoidPanel";
import EducationTab from "./_components/EducationTab";
import CounselingTab from "./_components/CounselingTab";
import GoalPlanningTab from "./_components/GoalPlanningTab";
import EncounterContextTab from "./_components/EncounterContextTab";
import MealPlanSection from "./_components/MealPlanSection";

type Tab = "nd" | "education" | "counseling" | "goals" | "encounter";
type PageParams = { patientId: string; ncpId: string };

const TABS: { key: Tab; label: string }[] = [
  { key: "nd",         label: "Food / Nutrient Delivery" },
  { key: "education",  label: "Education" },
  { key: "counseling", label: "Counseling" },
  { key: "goals",      label: "Goal Planning" },
  { key: "encounter",  label: "Encounter Context" },
];

interface PrescriptionForm {
  energy_kcal: string;
  protein_g: string;
  carbs_g: string;
  fat_g: string;
  fluid_ml: string;
  micronutrient_limits: Record<string, { max?: number; min?: number; unit: string }>;
  displayed_nutrients: string[];
}

const emptyPrescription = (): PrescriptionForm => ({
  energy_kcal: "", protein_g: "", carbs_g: "", fat_g: "", fluid_ml: "",
  micronutrient_limits: {}, displayed_nutrients: [],
});

function interventionToForm(iv: Intervention): PrescriptionForm {
  return {
    energy_kcal: iv.energy_kcal ?? "",
    protein_g:   iv.protein_g   ?? "",
    carbs_g:     iv.carbs_g     ?? "",
    fat_g:       iv.fat_g       ?? "",
    fluid_ml:    iv.fluid_ml    ?? "",
    micronutrient_limits: iv.micronutrient_limits ?? {},
    displayed_nutrients:  iv.displayed_nutrients  ?? [],
  };
}

export default function InterventionPage({ params }: { params: Promise<PageParams> }) {
  const { patientId, ncpId } = use(params);
  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const [tab, setTab]                           = useState<Tab>("nd");
  const [intervention, setIntervention]         = useState<Intervention | null>(null);
  const [loading, setLoading]                   = useState(true);
  const [goalModalOpen, setGoalModalOpen]       = useState(false);
  const [prescription, setPrescription]         = useState<PrescriptionForm>(emptyPrescription());
  const [prescNote, setPrescNote]               = useState<string | undefined>(undefined);
  const [saving, setSaving]                     = useState(false);
  // Unsaved-changes tracking: true once the RND edits a field, false after a
  // successful save or a fresh load. Drives the "save before leaving?" guard.
  const [dirty, setDirty]                       = useState(false);
  const [patientMetrics, setPatientMetrics]     = useState<PatientMetrics | null>(null);
  const [foodDislikes, setFoodDislikes]         = useState<string[]>([]);
  const [allergens, setAllergens]               = useState<string[]>([]);

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
    try {
      const [assessment, patient] = await Promise.allSettled([
        fetchAssessment(ncpId),
        fetchPatientById(patientId),
      ]);

      const a = assessment.status === "fulfilled" ? assessment.value : null;
      const p = patient.status === "fulfilled" ? patient.value : null;

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

      if (a?.weight && a?.height) {
        const palKey = a.physical_activity_level ?? "sedentary";
        const activityFactor = ACTIVITY_FACTORS[palKey]?.factor ?? 1.2;
        setPatientMetrics({
          weightKg: parseFloat(String(a.weight)),
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
  }, [ncpId, patientId]);

  useEffect(() => {
    if (!isPlaceholder) {
      loadIntervention();
      loadMetrics();
    }
  }, [isPlaceholder, loadIntervention, loadMetrics]);

  const ensureIntervention = async (): Promise<Intervention> => {
    if (intervention) return intervention;
    const iv = await createIntervention(ncpId, {});
    setIntervention(iv);
    return iv;
  };

  const handleGoalConfirm = async (goalType: string, stage: string | null) => {
    setGoalModalOpen(false);
    await ensureIntervention();

    const flagged = GOAL_MICRO_FLAGS[goalType] ?? [];
    const newDisplayed = Array.from(new Set([...prescription.displayed_nutrients, ...flagged]));

    // [1] Instant TS preview (frontend mirror — for responsiveness only).
    let preview: Prescription | null = null;
    if (patientMetrics) {
      preview = autofillPrescription(goalType, stage, patientMetrics);
      setPrescNote(preview.note);
      setPrescription({
        ...prescription,
        displayed_nutrients: newDisplayed,
        energy_kcal: String(preview.energy_kcal),
        protein_g:   String(preview.protein_g),
        carbs_g:     String(preview.carbs_g),
        fat_g:       String(preview.fat_g),
        fluid_ml:    String(preview.fluid_ml),
        // Engine-derived micro limits fill blank values; existing user edits win.
        micronutrient_limits: { ...microLimitsFromRx(preview, preview.energy_kcal), ...prescription.micronutrient_limits },
      });
    } else {
      setPrescription({ ...prescription, displayed_nutrients: newDisplayed });
    }

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
      // Engine-derived micro limits (sodium/fiber/etc.) — preview first, BE overrides below.
      let rxLimits = preview ? microLimitsFromRx(preview, preview.energy_kcal) : {};

      try {
        const be = await autofillIntervention(ncpId, goalType, stage);
        authoritative = { energy_kcal: be.energy_kcal, protein_g: be.protein_g, carbs_g: be.carbs_g, fat_g: be.fat_g, fluid_ml: be.fluid_ml };
        rxLimits = microLimitsFromRx(be, be.energy_kcal);
        setPrescNote(be.edema_warning ? `${be.note ?? ""} ${be.edema_warning}`.trim() : be.note);
        setPrescription((prev) => ({
          ...prev,
          displayed_nutrients: newDisplayed,
          energy_kcal: String(be.energy_kcal),
          protein_g:   String(be.protein_g),
          carbs_g:     String(be.carbs_g),
          fat_g:       String(be.fat_g),
          fluid_ml:    String(be.fluid_ml),
          micronutrient_limits: { ...rxLimits, ...prev.micronutrient_limits },
        }));
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
        // Backend autofill unavailable — keep the TS preview values (already shown).
        console.warn("Backend autofill failed; using frontend preview values.", err);
      }

      const updated = await updateIntervention(ncpId, {
        goal_type: goalType,
        disease_stage: stage,
        displayed_nutrients: newDisplayed,
        micronutrient_limits: { ...rxLimits, ...prescription.micronutrient_limits },
        ...(authoritative ?? {}),
      } as Partial<Intervention>);
      setIntervention(updated);
      setDirty(false);
    } finally { setSaving(false); }
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
        displayed_nutrients:  microKeys(prescription.displayed_nutrients),
      } as Partial<Intervention>);
      setIntervention(updated);
      setDirty(false);
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

  if (isPlaceholder) return <PlaceholderState />;
  if (loading) return (
    <div className="flex items-center justify-center h-48 text-xs text-zinc-400">Loading intervention…</div>
  );

  return (
    <div className="space-y-0 font-sans">
      {/* Breadcrumb + header */}
      <div className="space-y-4 mb-4">
        <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
          <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors"
            onClick={(e) => {
              if (dirty && !window.confirm("You have unsaved changes. Leave without saving?")) e.preventDefault();
            }}>Directory</Link>
          <span className="text-zinc-300">/</span>
          <span className="font-bold text-zinc-650">Nutrition Intervention</span>
        </div>
        <div className="border-b border-zinc-200 pb-4">
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Salad className="h-5 w-5 text-emerald-600" />
            Step 3: Nutrition Intervention
            {dirty && (
              <span className="ml-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-[10px] font-bold text-amber-700 uppercase tracking-wide">
                Unsaved changes
              </span>
            )}
          </h2>
        </div>
      </div>

      {/* Tab bar */}
      <div className="flex flex-wrap border-b border-zinc-200 mb-5">
        {TABS.map(({ key, label }) => (
          <button key={key} onClick={() => setTab(key)}
            className={`px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider border-b-2 whitespace-nowrap transition-colors cursor-pointer ${
              tab === key ? "border-emerald-600 text-emerald-700" : "border-transparent text-zinc-400 hover:text-zinc-600"
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
            <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Intervention Goal</h3>
                <Button variant="ghost" onClick={() => setGoalModalOpen(true)} className="px-3 py-1.5 text-[10px] gap-1.5">
                  <Settings2 className="h-3 w-3" />
                  {intervention?.goal_type ? "Change Goal" : "Set Goal"}
                </Button>
              </div>
              {intervention?.goal_type ? (
                <div className="flex items-center gap-2 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                  <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                  <div>
                    <p className="text-xs font-bold text-emerald-800">{goalLabel}</p>
                    {stageLabel && <p className="text-[10px] text-emerald-600">{stageLabel}</p>}
                  </div>
                </div>
              ) : (
                <p className="text-xs text-zinc-400 italic">No goal set. Click &ldquo;Set Goal&rdquo; to begin.</p>
              )}
            </div>

            {/* [B] Prescription */}
            <NutritionPrescriptionForm
              values={prescription}
              onChange={(v) => { setPrescription(v); setDirty(true); }}
              onSave={savePrescription}
              saving={saving}
              note={prescNote}
              requiredMicros={GOAL_MICRO_FLAGS[intervention?.goal_type ?? ""] ?? []}
              goalLabel={goalLabel}
            />

            {/* [C] Recommend / Avoid */}
            {intervention?.goal_type && (
              <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-3">
                <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Food Recommendations</h3>
                <p className="text-[9px] text-zinc-400">Goal-specific food guidance. RND to individualise based on patient tolerance.</p>
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
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Salad className="h-5 w-5 text-emerald-600 animate-pulse" />
          Step 3: Nutrition Intervention
        </h2>
      </div>
      <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
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
