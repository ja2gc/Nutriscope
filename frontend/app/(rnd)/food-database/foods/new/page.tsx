import { redirect } from "next/navigation";

export default function Redirect() {
  redirect("/food-library/foods/new");
}
