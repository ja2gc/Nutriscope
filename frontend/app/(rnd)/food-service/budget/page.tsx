"use client";

import { BudgetPageShell } from "@/components/budget/BudgetPageShell";

export default function BudgetPage() {
  return (
    <BudgetPageShell
      apiPrefix="fss"
      canMutate={true}
      crumbs={[["Home", "/dashboard"], ["Food Service"], ["Budget"]]}
      homeHref="/dashboard"
    />
  );
}
