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
  created_at: string;
  updated_at: string;
  ncp_records?: NcpRecord[];
}

export interface NcpRecord {
  id: number;
  patient_id: number;
  rnd_user_id: number;
  type?: string;
  status: string;
  ai_risk_score?: number;
  created_at: string;
  updated_at: string;
  rnd?: {
    id: number;
    name: string;
  };
  assessment?: any;
  diagnoses?: any[];
  intervention?: any;
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
}

export interface PatientUpdateData extends Partial<PatientStoreData> {}

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

  const res = await fetch(`/api/patients?${queryParams.toString()}`, {
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
  const res = await fetch(`/api/patients/${id}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch patient details.");
  }

  const data = await res.json();
  return data.data || data;
}

export async function createPatient(data: PatientStoreData): Promise<Patient> {
  const res = await fetch("/api/patients", {
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

  return res.json();
}

export async function updatePatient(
  id: number | string,
  data: PatientUpdateData
): Promise<Patient> {
  const res = await fetch(`/api/patients/${id}`, {
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

  return res.json();
}

export async function fetchPatientNcpRecords(
  id: number | string
): Promise<NcpRecord[]> {
  const res = await fetch(`/api/patients/${id}/ncp-records`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch patient NCP history.");
  }

  const data = await res.json();
  return data.data || [];
}
