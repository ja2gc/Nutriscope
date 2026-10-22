import type { FoodGuide } from "@/services/interventionService";

interface Props { guide?: FoodGuide; allergens?: string[]; isYoungInfant?: boolean }

export default function RecommendAvoidPanel({ guide, allergens = [], isYoungInfant = false }: Props) {
  if (!guide) return <p className="text-sm text-warm-500">Select a supported goal to load food guidance.</p>;
  if (isYoungInfant) return <p className="text-sm leading-relaxed text-warm-700">
    Follow the prescribed breast-milk or infant-formula feeding plan. Ordinary solid-food meal recommendations do not apply.
    Review feeding amounts and any allergy or medical needs with the pediatric team.
  </p>;
  const choose = allergens.length ? "Foods meeting the prescribed portions and excluding confirmed allergens." : guide.choose;
  return (
    <div className="space-y-3">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
          <h4 className="text-sm font-bold text-emerald-900">Choose more often</h4>
          <p className="mt-1 text-sm leading-relaxed text-warm-800">{choose}</p>
        </div>
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-3">
          <h4 className="text-sm font-bold text-amber-900">Limit</h4>
          <p className="mt-1 text-sm leading-relaxed text-warm-800">{guide.limit}</p>
        </div>
      </div>
      {allergens.length > 0 && <p className="text-sm font-semibold text-warm-800">Avoid confirmed allergens: {allergens.join(", ")}.</p>}
      <p className="text-xs leading-relaxed text-warm-500">{guide.context}</p>
    </div>
  );
}
