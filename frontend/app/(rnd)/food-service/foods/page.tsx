import { redirect } from "next/navigation";

export default function FoodServiceFoodsPage() {
  redirect("/food-service/inventory?tab=menu-items");
}
