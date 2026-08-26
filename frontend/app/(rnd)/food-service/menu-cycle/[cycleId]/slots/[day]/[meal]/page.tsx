import { MenuSlotRecipePage } from "@/components/foodservice/MenuSlotRecipePage";

export default async function RndMenuSlotPage({ params }: { params: Promise<{ cycleId: string }> }) {
  const { cycleId } = await params;
  return <MenuSlotRecipePage backHref={`/food-service/menu-cycle?cycle=${cycleId}`} />;
}
