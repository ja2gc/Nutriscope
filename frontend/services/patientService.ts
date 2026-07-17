import { apiFetch } from "@/lib/apiFetch";
export interface ClinicalActor {
  id: string | null;
  kind: "user" | "system" | "anonymous";
  name: string;
  role: string | null;
}

export interface ClinicalActionAttribution {
  actor: ClinicalActor | null;
  occurred_at: string;
}

export interface Patient {
  id: number;
  first_name: string | null;
  last_name: string | null;
  display_name: string;
  name: string;
  dob: string; // YYYY-MM-DD
  sex: "Male" | "Female";
  religion?: string;
  address?: string;
  contact?: string;
  physician?: string;
  admission_date: string; // YYYY-MM-DD
  medical_diagnosis?: string;
  ward?: string;
  status: "Active" | "Discharged" | "Transferred";
  screening_type?: "adult" | "pediatric";
  hospital_number?: string;
  age_group_category?: string;
  created_at: string;
  updated_at: string;
  ncp_records?: NcpRecord[];
  last_assessment_date?: string | null;
  next_followup_date?: string | null;
  risk_score?: number | string | null;
  latest_ncp_id?: number | null;
  latest_ncp_created_by?: ClinicalActor | null;
  last_clinical_action?: ClinicalActionAttribution | null;
}

export interface NcpRecord {
  id: number | string;
  patient_id: number | string;
  rnd_user_id: number;
  type?: string;
  status: string;
  created_at: string;
  updated_at: string;
  created_by?: ClinicalActor | null;
  last_clinical_action?: ClinicalActionAttribution | null;
  rnd?: {
    id: number;
    name: string;
  };
  assessment?: {
    rnd_summary?: string | null;
    allergies?: string[] | null;
  } | null;
  diagnoses?: Array<{
    pes_statement?: string | null;
  }> | null;
  intervention?: {
    goal_type?: string | null;
    next_followup_date?: string | null;
    meal_plans?: Array<{
      id: number;
      week_start_date: string;
      generation_type?: string | null;
    }> | null;
  } | null;
}

interface ModernPatientNameInput {
  first_name: string;
  last_name: string;
  name?: string;
}

export function ncpRecordMatchesRoute(records: NcpRecord[], ncpRecordId: number | string): boolean {
  return records.some((record) => String(record.id) === String(ncpRecordId));
}

interface LegacyPatientNameInput {
  name: string;
  first_name?: string;
  last_name?: string;
}

interface PatientStoreFields {
  dob: string;
  sex: "Male" | "Female";
  religion?: string;
  address?: string;
  contact?: string;
  physician?: string;
  admission_date: string;
  medical_diagnosis?: string;
  ward?: string;
  status?: "Active" | "Discharged" | "Transferred";
  screening_type?: "adult" | "pediatric";
  hospital_number?: string;
  age_group_category?: string;
}

export type PatientStoreData = (ModernPatientNameInput | LegacyPatientNameInput) & PatientStoreFields;

export type PatientUpdateData = Partial<PatientStoreFields> & {
  first_name?: string;
  last_name?: string;
  name?: string;
};

export interface PatientListResponse {
  data: Patient[];
  links?: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta?: {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
  };
}

export async function fetchPatients(
  search?: string,
  status?: string,
  page: number = 1,
  perPage: number = 10,
  upcomingFollowups = false,
): Promise<PatientListResponse> {
  const queryParams = new URLSearchParams();
  if (search) queryParams.append("search", search);
  if (status && status !== "All") queryParams.append("status", status);
  queryParams.append("page", page.toString());
  queryParams.append("per_page", perPage.toString());
  if (upcomingFollowups) queryParams.append("upcoming_followups", "1");

  const res = await apiFetch(`/api/patients?${queryParams.toString()}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch patients.");
  }

  return res.json();
}

export async function fetchPatientById(id: number | string): Promise<Patient> {
  const res = await apiFetch(`/api/patients/${id}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch patient details.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function createPatient(data: PatientStoreData): Promise<Patient> {
  const res = await apiFetch("/api/patients", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to create patient.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function updatePatient(
  id: number | string,
  data: PatientUpdateData
): Promise<Patient> {
  const res = await apiFetch(`/api/patients/${id}`, {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to update patient.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function fetchPatientNcpRecords(
  id: number | string,
  page = 1,
  ncpRecordId?: number | string,
): Promise<{ data: NcpRecord[]; meta: Pick<NonNullable<PatientListResponse["meta"]>, "current_page" | "per_page" | "total" | "last_page"> }> {
  const params = new URLSearchParams({ page: String(page), per_page: "10" });
  if (ncpRecordId !== undefined) params.set("ncp_record_id", String(ncpRecordId));
  const res = await apiFetch(`/api/patients/${id}/ncp-records?${params}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch patient NCP history.");
  }

  const responseData = await res.json();
  return { data: responseData.data || [], meta: responseData.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 } };
}

export async function deletePatient(id: number | string): Promise<void> {
  const res = await apiFetch(`/api/patients/${id}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });

  if (res.status === 204) return;

  const errorData = await res.json().catch(() => ({}));
  throw new Error(errorData.message || "Failed to delete patient.");
}

export async function deleteNcpRecord(ncpRecordId: number | string): Promise<void> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });

  if (res.status === 204) return;

  const errorData = await res.json().catch(() => ({}));
  throw new Error(errorData.message || "Failed to delete NCP record.");
}

export async function createNcpRecord(id: number | string): Promise<NcpRecord> {
  const res = await apiFetch(`/api/patients/${id}/ncp-records`, {
    method: "POST",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to create NCP record.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}
