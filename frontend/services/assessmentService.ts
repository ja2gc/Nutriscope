import { apiFetch } from "@/lib/apiFetch";
export interface Assessment {
  id?: number;
  ncp_record_id?: number;
  dietary_intake: string | null;
  appetite_changes: string | null;
  dietary_restrictions: string | null;
  supplements: string | null;
  knowledge_notes: string | null;
  weight: number | string | null;
  height: number | string | null;
  bmi: number | string | null;
  body_composition: string | null;
  medical_history: string | null;
  social_history: string | null;
  religion: string | null;
  lifestyle: string | null;
  allergies: string[] | null;
  food_dislikes: string[] | null;
  medications: string[] | null;
  rnd_summary: string | null;
  usual_weight: number | string | null;
  nutritional_status: "Normal" | "Moderate Malnutrition" | "Severe Malnutrition" | null;
  weight_loss_percentage: number | string | null;
  weight_loss_period: string | null;
  functional_assessment: "Bed ridden" | "Needs assistance" | "Ambulatory" | null;
  energy_intake_status: "No change" | "Mostly liquids" | "Sub-optimal" | "Starvation" | "Poor intake prior to admission" | null;
  ibw_percentage: number | string | null;
  present_diet: string | null;
  physical_assessment: string | null;
  chewing_swallowing_difficulties: string | null;
  constipation: string | null;
  diarrhea_notes: string | null;
  food_intolerance: string | null;
  nutrient_drug_interaction: string | null;
  dietary_intake_method: "24_hour_recall" | "food_frequency" | "3_day_record" | "other" | null;
  dietary_record_file: string | null;
  physical_activity_level: "sedentary" | "light" | "moderate" | "very_active" | "extra_active" | null;
  muac_mm: number | string | null;
  waist_cm: number | string | null;
  hip_cm: number | string | null;
  // Phase 5 — nutrition engine inputs
  stress_factor: number | string | null;
  edema_present: boolean | null;
  pregnancy_lactation_status: "none" | "pregnant" | "lactating" | null;
  risk_score?: number | null;
  checked_factors?: string[] | null;
  created_at?: string;
  updated_at?: string;
  biochemical_data?: {
    albumin?: number | string | null;
    hematocrit?: number | string | null;
    bun?: number | string | null;
    hemoglobin?: number | string | null;
    calcium?: number | string | null;
    ldl?: number | string | null;
    cholesterol?: number | string | null;
    phosphate?: number | string | null;
    creatinine?: number | string | null;
    potassium?: number | string | null;
    glucose?: number | string | null;
    sodium?: number | string | null;
    hba1c?: number | string | null;
    triglycerides?: number | string | null;
    hdl?: number | string | null;
    urr?: number | string | null;
    bp?: string | null;
    abg?: string | null;
    others?: unknown[] | Record<string, unknown> | null;
  } | null;
}

/**
 * NCP supporting-document attachment (rnd.md §3.1) — plain file storage linked to
 * an NCP cycle. No OCR/extraction.
 */
export interface AttachmentRecord {
  id: number;
  patient_id?: number;
  assessment_id?: number;
  type?: string | null;
  file_path?: string;
  original_name?: string | null;
  created_at?: string;
  updated_at?: string;
}

export async function fetchAssessment(ncpRecordId: number | string): Promise<Assessment> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/assessment`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch assessment details.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function saveAssessment(
  ncpRecordId: number | string,
  data: Partial<Assessment>,
  exists: boolean
): Promise<Assessment> {
  const method = exists ? "PATCH" : "POST";
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/assessment`, {
    method,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to save assessment details.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

// ─── NCP Attachments (rnd.md §3.1) ──────────────────────────────────────────

export async function uploadAttachment(
  ncpRecordId: number | string,
  file: File,
  type?: string
): Promise<AttachmentRecord> {
  const formData = new FormData();
  formData.append("file", file);
  if (type) formData.append("type", type);

  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/attachments`, {
    method: "POST",
    body: formData,
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to upload attachment.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function fetchAttachments(ncpRecordId: number | string): Promise<AttachmentRecord[]> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/attachments`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch attachments.");
  }

  const responseData = await res.json();
  return responseData.data || [];
}

export async function deleteAttachment(attachmentId: number | string): Promise<void> {
  const res = await apiFetch(`/api/rnd/screening-documents/${attachmentId}`, {
    method: "DELETE",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to delete attachment.");
  }
}

// ─── File Preview URL Helper ────────────────────────────────────────────────
// Returns a relative Next.js proxy URL — auth token is forwarded server-side.

export function getAttachmentFileUrl(id: number | string): string {
  return `/api/rnd/screening-documents/${id}/file`;
}
