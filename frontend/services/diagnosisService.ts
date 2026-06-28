import { apiFetch } from "@/lib/apiFetch";
export interface Diagnosis {
  id?: number;
  ncp_record_id?: number;
  domain: "NI" | "NC" | "NB";
  problem: string;
  label?: string;
  etiology: string;
  signs_symptoms: string;
  pes_statement?: string;
  extra_notes?: string | null;
  ai_generated?: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface StoreDiagnosisPayload {
  domain: "NI" | "NC" | "NB";
  problem: string;
  etiology: string;
  signs_symptoms: string;
  /** RND's PES statement (manual override or builder-derived) — persisted as-is. */
  pes_statement?: string;
  extra_notes?: string | null;
  ai_generated?: boolean;
}

export interface AiSuggestPayload {
  conditions: string[];
  ibw_percentage?: number | null;
}

export interface AiSuggestion {
  domain: "NI" | "NC" | "NB";
  label: string;
  etiology: string;
  signs: string;
  priority?: number;
  confidence?: number;
  reasoning?: string;
}

export interface AiApprovePayload {
  domain: "NI" | "NC" | "NB";
  label: string;
  etiology: string;
  signs: string;
  priority?: number;
}

export async function fetchDiagnoses(ncpRecordId: number | string): Promise<Diagnosis[]> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/diagnoses`, {
    headers: { Accept: "application/json" },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to fetch diagnoses.");
  }
  const data = await res.json();
  return data.data ?? data;
}

export async function storeDiagnosis(ncpRecordId: number | string, payload: StoreDiagnosisPayload): Promise<Diagnosis> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/diagnoses`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to save diagnosis.");
  }
  const data = await res.json();
  return data.data ?? data;
}

export async function updateDiagnosis(
  ncpRecordId: number | string,
  diagnosisId: number | string,
  payload: Partial<StoreDiagnosisPayload>
): Promise<Diagnosis> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/diagnoses/${diagnosisId}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to update diagnosis.");
  }
  const data = await res.json();
  return data.data ?? data;
}

export async function deleteDiagnosis(ncpRecordId: number | string, diagnosisId: number | string): Promise<void> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/diagnoses/${diagnosisId}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });
  if (!res.ok && res.status !== 204) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to delete diagnosis.");
  }
}

export async function aiSuggestDiagnoses(ncpRecordId: number | string, payload: AiSuggestPayload): Promise<AiSuggestion[]> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/diagnoses/ai-suggest`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "AI suggestion failed.");
  }
  const data = await res.json();
  return data.data ?? data;
}

export async function aiApproveDiagnosis(ncpRecordId: number | string, payload: AiApprovePayload): Promise<Diagnosis> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/diagnoses/ai-approve`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to approve AI diagnosis.");
  }
  const data = await res.json();
  return data.data ?? data;
}
