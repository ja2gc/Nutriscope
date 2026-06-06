import { apiFetch } from "@/lib/apiFetch";
export interface Patient {
  id: number;
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
}

export interface NcpRecord {
  id: number;
  patient_id: number;
  rnd_user_id: number;
  type?: string;
  status: string;
  created_at: string;
  updated_at: string;
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
  } | null;
}

export interface PatientStoreData {
  name: string;
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

export type PatientUpdateData = Partial<PatientStoreData>;

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
  page: number = 1
): Promise<PatientListResponse> {
  const queryParams = new URLSearchParams();
  if (search) queryParams.append("search", search);
  if (status && status !== "All") queryParams.append("status", status);
  queryParams.append("page", page.toString());

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
  id: number | string
): Promise<NcpRecord[]> {
  const res = await apiFetch(`/api/patients/${id}/ncp-records`, {
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
  return responseData.data || [];
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
