import { redirect } from "next/navigation";

export default function FoodServiceRecipesPage() {
  redirect("/food-service/inventory?tab=menu-items");
}
