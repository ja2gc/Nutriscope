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
  created_at?: string;
  updated_at?: string;
}

export interface ScreeningDocumentRecord {
  id?: number;
  patient_id?: number;
  assessment_id?: number;
  type?: "adult" | "pediatric";
  file_path?: string;
  extracted_data?: Record<string, unknown> | null;
  mapped_fields?: Record<string, unknown> | null;
  status?: "pending" | "processing" | "completed" | "failed";
  confidence_score?: number | string | null;
  reviewed_by?: number | null;
  reviewed_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface ScreeningDocumentApprovalPayload {
  mapped_fields: Record<string, unknown>;
}

export interface OcrDocumentRecord {
  id?: number;
  user_id?: number;
  assessment_id?: number;
  file_path?: string;
  extracted_text?: string | null;
  document_type?: "screening" | "lab" | string;
  extraction_template_id?: number | null;
  parsed_fields?: Record<string, unknown> | null;
  confidence_score?: number | string | null;
  processing_time_ms?: number | null;
  status?: "pending" | "completed" | "failed" | "processing";
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

export async function uploadScreeningDocument(
  ncpRecordId: number | string,
  file: File
): Promise<ScreeningDocumentRecord> {
  const formData = new FormData();
  formData.append("file", file);

  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/upload-screening`, {
    method: "POST",
    body: formData,
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to upload screening document.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function uploadLabsDocument(
  ncpRecordId: number | string,
  file: File
): Promise<OcrDocumentRecord> {
  const formData = new FormData();
  formData.append("file", file);

  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/upload-labs`, {
    method: "POST",
    body: formData,
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to upload labs document.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function fetchScreeningDocument(ncpRecordId: number | string): Promise<ScreeningDocumentRecord | null> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/screening-document`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch screening document.");
  }

  const responseData = await res.json();
  return responseData.data || responseData || null;
}

export async function fetchOcrDocuments(ncpRecordId: number | string): Promise<OcrDocumentRecord[]> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/ocr-documents`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch OCR documents.");
  }

  const responseData = await res.json();
  return responseData.data || [];
}

export async function approveScreeningDocument(
  screeningDocumentId: number | string,
  payload: ScreeningDocumentApprovalPayload
): Promise<ScreeningDocumentRecord> {
  const res = await apiFetch(`/api/rnd/screening-documents/${screeningDocumentId}/approve`, {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to approve screening document.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

// ─── File Preview URL Helpers ───────────────────────────────────────────────
// These return relative Next.js proxy URLs — auth token is forwarded server-side.

export function getScreeningDocumentFileUrl(id: number | string): string {
  return `/api/rnd/screening-documents/${id}/file`;
}

export function getOcrDocumentFileUrl(id: number | string): string {
  return `/api/rnd/ocr-documents/${id}/file`;
}
