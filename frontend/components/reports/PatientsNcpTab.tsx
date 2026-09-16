"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Loader2 } from "lucide-react";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import SearchInput from "@/components/ui/SearchInput";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import { fetchPatients, type Patient } from "@/services/patientService";

export function PatientsNcpTab() {
  const [patients, setPatients] = useState<Patient[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await fetchPatients(debouncedSearch, "All", page, 10);
      setPatients(result.data);
      setMeta(result.meta ?? null);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to load patients.");
    } finally {
      setLoading(false);
    }
  }, [debouncedSearch, page]);

  useEffect(() => { void load(); }, [load]);

  return (
    <Card className="overflow-hidden">
      <div className="px-5 py-4 border-b border-warm-100">
        <h2 className="text-base font-bold text-warm-800">Patients NCP</h2>
        <p className="text-xs text-warm-500 mt-0.5">Choose a patient, then select one ADIME cycle to view only its reports.</p>
      </div>
      <div className="px-5 py-3 border-b border-warm-100">
        <SearchInput
          label="Search patients"
          value={search}
          onChange={(value) => { setSearch(value); setPage(1); }}
          loading={loading && search !== debouncedSearch}
        />
      </div>
      {error ? (
        <div role="alert" className="px-5 py-8 text-sm font-semibold text-red-700">{error}</div>
      ) : loading ? (
        <div className="py-14 flex items-center justify-center gap-2 text-sm text-warm-500">
          <Loader2 className="h-4 w-4 animate-spin" /> Loading patients…
        </div>
      ) : patients.length === 0 ? (
        <div className="py-12"><EmptyState title="No patients found" message="No patient records match this search." /></div>
      ) : (
        <ul className="divide-y divide-zinc-100">
          {patients.map((patient) => (
            <li key={patient.id}>
              <Link
                href={`/reports/patients/${patient.id}`}
                className="block px-5 py-3.5 hover:bg-warm-50 focus-visible:outline-none focus-visible:bg-emerald-50/50"
              >
                <span className="block text-base font-semibold text-warm-900">{patient.display_name}</span>
                <span className="block text-xs text-warm-500 mt-0.5">
                  {[patient.hospital_number, patient.status].filter(Boolean).join(" · ")}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
      {!loading && !error && <Pagination meta={meta} page={page} onPageChange={setPage} />}
    </Card>
  );
}
