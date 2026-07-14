import type { ClinicalActionAttribution, ClinicalActor } from "@/services/patientService";

interface ClinicalAttributionProps {
  creator?: ClinicalActor | null;
  lastAction?: ClinicalActionAttribution | null;
  formatDate: (value: string) => string;
  className?: string;
}

export function ClinicalAttribution({
  creator,
  lastAction,
  formatDate,
  className = "",
}: ClinicalAttributionProps) {
  return (
    <div className={`text-xs text-warm-500 ${className}`}>
      <div>
        <span className="font-bold text-warm-600">Created by</span>{" "}
        {creator?.name || "Not recorded"}
      </div>
      <div className="mt-1">
        <span className="font-bold text-warm-600">Last clinical action by</span>{" "}
        {lastAction?.actor?.name || "No action recorded"}
        {lastAction?.occurred_at ? ` · ${formatDate(lastAction.occurred_at)}` : ""}
      </div>
    </div>
  );
}
