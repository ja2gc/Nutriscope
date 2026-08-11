import { redirect } from "next/navigation";
import { cookies } from "next/headers";

export default async function Home() {
  const cookieStore = await cookies();
  const role = cookieStore.get("nutriscope_role")?.value;
  
  if (role === "Admin") {
    redirect("/admin/dashboard");
  } else if (role === "FSS") {
    redirect("/fss");
  } else {
    redirect("/dashboard");
  }
}
