import { MenuSlotRecipePage } from "@/components/foodservice/MenuSlotRecipePage";
import { InventoryCorrectionEditor } from "@/components/foodservice/InventoryCorrectionEditor";

export default async function RndMenuLinePage({ params, searchParams }: { params: Promise<{ cycleId: string; lineId: string }>; searchParams: Promise<{ inventoryCorrection?: string }> }) {
  const { cycleId, lineId } = await params;
  const { inventoryCorrection } = await searchParams;
  if (inventoryCorrection) return <InventoryCorrectionEditor kind="slot" id={lineId} cycleId={cycleId} token={inventoryCorrection} />;
  return <MenuSlotRecipePage backHref={`/food-service/menu-cycle?cycle=${cycleId}`} lineId={lineId} />;
}
