"use client";

import React from "react";
import Link from "next/link";
import { CookingPot, Plus, Search, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/Button";

export default function RecipesPage() {
  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-650 font-bold">Recipes Database</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <CookingPot className="h-5 w-5 text-emerald-600 animate-pulse" />
            Clinical Recipes & Food Library
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            Create, search, and analyze recipe macros, allergens, and production costs.
          </p>
        </div>
        <Button variant="primary" className="sm:w-auto px-4 py-2.5 shrink-0 flex items-center justify-center gap-2">
          <Plus className="h-4 w-4" />
          Create Recipe
        </Button>
      </div>

      {/* Placeholder Body */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
          <CookingPot className="h-8 w-8 text-emerald-600" />
        </div>
        <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">Recipes System Under Construction</h3>
        <p className="text-xs text-zinc-500 mt-2 leading-relaxed">
          The upcoming clinical recipe engine will integrate directly with the USDA FoodData Central API (backed by Redis caching) to allow real-time nutrient calculations, allergen alerts, and disease restriction filtering.
        </p>
        <div className="mt-6 flex flex-wrap justify-center gap-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 border border-emerald-100 text-emerald-750 text-[10px] font-extrabold uppercase rounded-lg">
            <Sparkles className="h-3 w-3 animate-spin" />
            USDA API Sync Coming in M7
          </span>
        </div>
      </div>
    </div>
  );
}
