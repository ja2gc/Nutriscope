"use client";

import React, { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { Salad, User, Settings2, CheckCircle2 } from "lucide-react";
import {
  fetchIntervention, createIntervention, updateIntervention,
  fetchRecommendations, Intervention, RecommendResult,
} from "@/services/interventionService";
import { fetchAssessment } from "@/services/assessmentService";
import {
  autofillPrescription, GOAL_MICRO_FLAGS, Prescription, PatientMetrics,
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
  const [recommend, setRecommend]               = useState<RecommendResult | null>(null);
  const [recommendLoading, setRecommendLoading] = useState(false);
  const [saving, setSaving]                     = useState(false);
  const [patientMetrics, setPatientMetrics]     = useState<PatientMetrics | null>(null);
  const [foodDislikes, setFoodDislikes]         = useState<string[]>([]);
  const [allergens, setAllergens]               = useState<string[]>([]);

  const [educationNotes, setEducationNotes]   = useState("");
  const [counselingGoals, setCounselingGoals] = useState("");
  const [barriers, setBarriers]               = useState("");
  const [strategies, setStrategies]           = useState("");
  const [sessionType, setSessionType]         = useState("");
  const [nextFollowup, setNextFollowup]       = useState("");

  const loadRecommendations = useCallback(async () => {
    setRecommendLoading(true);
    try {
      const data = await fetchRecommendations(ncpId);
      setRecommend(data);
    } finally { setRecommendLoading(false); }
  }, [ncpId]);

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
        if (iv.goal_type) loadRecommendations();
      }
    } finally { setLoading(false); }
  }, [ncpId, loadRecommendations]);

  const loadMetrics = useCallback(async () => {
    try {
      const assessment = await fetchAssessment(ncpId);
      if (assessment?.weight && assessment?.height) {
        setPatientMetrics({
          weightKg: parseFloat(String(assessment.weight)),
          heightCm: parseFloat(String(assessment.height)),
          ageYears: 40,   // TODO: wire from patient context (patient.dob)
          sex: "Male",    // TODO: wire from patient context (patient.sex)
          isAdult: true,
        });
      }
      if (assessment?.food_dislikes && Array.isArray(assessment.food_dislikes)) {
        setFoodDislikes(assessment.food_dislikes.map((d: string) => d.toLowerCase()));
      }
      if (assessment?.allergies && Array.isArray(assessment.allergies)) {
        setAllergens(assessment.allergies.map((a: string) => a.toLowerCase()));
      }
    } catch { /* assessment may not exist yet */ }
  }, [ncpId]);

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

    let autofilled: Prescription | null = null;
    if (patientMetrics) {
      autofilled = autofillPrescription(goalType, stage, patientMetrics);
      setPrescNote(autofilled.note);
    }

    const newPresc: PrescriptionForm = {
      ...prescription,
      displayed_nutrients: newDisplayed,
      ...(autofilled ? {
        energy_kcal: String(autofilled.energy_kcal),
        protein_g:   String(autofilled.protein_g),
        carbs_g:     String(autofilled.carbs_g),
        fat_g:       String(autofilled.fat_g),
        fluid_ml:    String(autofilled.fluid_ml),
      } : {}),
    };
    setPrescription(newPresc);

    setSaving(true);
    try {
      const updated = await updateIntervention(ncpId, {
        goal_type: goalType,
        disease_stage: stage,
        displayed_nutrients: newDisplayed,
        ...(autofilled ? {
          energy_kcal: autofilled.energy_kcal,
          protein_g:   autofilled.protein_g,
          carbs_g:     autofilled.carbs_g,
          fat_g:       autofilled.fat_g,
          fluid_ml:    autofilled.fluid_ml,
        } : {}),
      } as Partial<Intervention>);
      setIntervention(updated);
      await loadRecommendations();
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
        displayed_nutrients:  prescription.displayed_nutrients,
      } as Partial<Intervention>);
      setIntervention(updated);
    } finally { setSaving(false); }
  };

  const saveTextField = async (fields: Partial<Intervention>) => {
    setSaving(true);
    try {
      await ensureIntervention();
      const updated = await updateIntervention(ncpId, fields);
      setIntervention(updated);
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
          <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
          <span className="text-zinc-300">/</span>
          <span className="font-bold text-zinc-650">Nutrition Intervention</span>
        </div>
        <div className="border-b border-zinc-200 pb-4">
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Salad className="h-5 w-5 text-emerald-600" />
            Step 3: Nutrition Intervention
          </h2>
        </div>
      </div>

      {/* Tab bar */}
      <div className="flex flex-wrap border-b border-zinc-200 sticky top-0 z-20 bg-white -mx-6 px-6 lg:-mx-8 lg:px-8">
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
              onChange={setPrescription}
              onSave={savePrescription}
              saving={saving}
              note={prescNote}
            />

            {/* [C] Recommend / Avoid */}
            {intervention?.goal_type && (
              <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-3">
                <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Food Recommendations</h3>
                <p className="text-[9px] text-zinc-400">Algorithm-driven based on goal and clinical rules. Not AI-generated.</p>
                <RecommendAvoidPanel data={recommend} loading={recommendLoading} />
              </div>
            )}

            {/* [D] Meal Plan */}
            <MealPlanSection
              ncpId={ncpId}
              prescriptionTargets={{
                energy:  parseFloat(prescription.energy_kcal) || 0,
                protein: parseFloat(prescription.protein_g)   || 0,
                carbs:   parseFloat(prescription.carbs_g)     || 0,
                fat:     parseFloat(prescription.fat_g)       || 0,
              }}
              foodDislikes={foodDislikes}
              allergens={allergens}
              displayedMicros={prescription.displayed_nutrients}
              micronutrientLimits={prescription.micronutrient_limits}
            />
          </div>
        )}

        {/* TAB 2 — Education */}
        {tab === "education" && (
          <EducationTab
            value={educationNotes}
            onChange={setEducationNotes}
            onSave={() => saveTextField({ education_notes: educationNotes } as Partial<Intervention>)}
            saving={saving}
          />
        )}

        {/* TAB 3 — Counseling */}
        {tab === "counseling" && (
          <CounselingTab
            goals={counselingGoals} barriers={barriers} strategies={strategies}
            onChange={(field, val) => {
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
