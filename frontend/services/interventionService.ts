export interface MicronutrientLimit {
  max?: number;
  min?: number;
  unit: string;
}

export interface Intervention {
  id: number;
  ncp_record_id: number;
  goal_type: string | null;
  disease_stage: string | null;
  displayed_nutrients: string[] | null;
  energy_kcal: string | null;
  protein_g: string | null;
  carbs_g: string | null;
  fat_g: string | null;
  fluid_ml: string | null;
  micronutrient_limits: Record<string, MicronutrientLimit> | null;
  education_notes: string | null;
  counseling_goals: string | null;
  barriers: string | null;
  strategies: string | null;
  session_type: string | null;
  next_followup_date: string | null;
}

export interface RecommendResult {
  recommend: { tag: string; condition: string; reason: string }[];
  avoid:     { tag: string; condition: string; reason: string }[];
  limits:    { tag: string; condition: string; reason: string; threshold: number; unit: string }[];
}

const base = (ncpId: string) => `/api/rnd/ncp-records/${ncpId}/intervention`;

export async function fetchIntervention(ncpId: string): Promise<Intervention | null> {
  const res = await fetch(base(ncpId), { headers: { Accept: 'application/json' } });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error('Failed to fetch intervention.');
  const data = await res.json();
  return data.data ?? null;
}

export async function createIntervention(ncpId: string, payload: Partial<Intervention>): Promise<Intervention> {
  const res = await fetch(base(ncpId), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'Failed to create intervention.');
  }
  return (await res.json()).data;
}

export async function updateIntervention(ncpId: string, payload: Partial<Intervention>): Promise<Intervention> {
  const res = await fetch(base(ncpId), {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'Failed to update intervention.');
  }
  return (await res.json()).data;
}

export async function fetchRecommendations(ncpId: string): Promise<RecommendResult> {
  const res = await fetch(`${base(ncpId)}/recommendations`, { headers: { Accept: 'application/json' } });
  if (!res.ok) return { recommend: [], avoid: [], limits: [] };
  return (await res.json()).data ?? { recommend: [], avoid: [], limits: [] };
}
