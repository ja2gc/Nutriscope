import { MenuSlotRecipePage } from "@/components/foodservice/MenuSlotRecipePage";

export default async function FssMenuSlotPage({ params }: { params: Promise<{ cycleId: string }> }) {
  const { cycleId } = await params;
  return <MenuSlotRecipePage readOnly backHref={`/fss/menu?cycle=${cycleId}`} />;
}
