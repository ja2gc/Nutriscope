import { INTERVENTION_GUIDANCE_TOTAL_MAX } from "@/lib/interventionGuidance";

export default function GuidanceCharacterCount({ total }: { total: number }) {
  const over = Math.max(0, total - INTERVENTION_GUIDANCE_TOTAL_MAX);

  return (
    <p
      role={over ? "alert" : undefined}
      className={`text-right text-xs font-semibold ${over ? "text-red-700" : "text-warm-500"}`}
    >
      {over
        ? `${over.toLocaleString()} ${over === 1 ? "character" : "characters"} over`
        : `${total.toLocaleString()} / ${INTERVENTION_GUIDANCE_TOTAL_MAX.toLocaleString()} characters`}
    </p>
  );
}
