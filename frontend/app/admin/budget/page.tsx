"use client";

import { BudgetPageShell } from "@/components/budget/BudgetPageShell";

export default function AdminBudgetPage() {
  return (
    <BudgetPageShell
      apiPrefix="admin"
      canMutate={true}
      crumbs={[["Admin", "/admin/dashboard"], ["Budget"]]}
      homeHref="/admin/dashboard"
    />
  );
}
