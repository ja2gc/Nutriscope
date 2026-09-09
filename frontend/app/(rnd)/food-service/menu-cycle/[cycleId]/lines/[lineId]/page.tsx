import { MenuSlotRecipePage } from "@/components/foodservice/MenuSlotRecipePage";

export default async function RndMenuLinePage({ params }: { params: Promise<{ cycleId: string; lineId: string }> }) {
  const { cycleId, lineId } = await params;
  return <MenuSlotRecipePage backHref={`/food-service/menu-cycle?cycle=${cycleId}`} lineId={lineId} />;
}
