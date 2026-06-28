"use client";

import { BudgetPageShell } from "@/components/budget/BudgetPageShell";

export default function AdminBudgetPage() {
  return (
    <BudgetPageShell
      apiPrefix="admin"
      canMutate={false}
      crumbs={[["Admin", "/admin/dashboard"], ["Budget"]]}
      homeHref="/admin/dashboard"
    />
  );
}
