"use client";

import React from "react";

interface Tab {
  key: string;
  label: string;
  icon?: React.ReactNode;
}

interface Props {
  tabs: Tab[];
  activeTab: string;
  onTabChange: (key: string) => void;
  variant?: "default" | "sticky";
  showIcon?: boolean;
}

export default function TabBar({
  tabs,
  activeTab,
  onTabChange,
  variant = "default",
  showIcon = false,
}: Props) {
  const containerClass =
    variant === "sticky"
      ? "sticky top-0 z-20 bg-white -mx-6 px-6 lg:-mx-8 lg:px-8"
      : "";

  return (
    <div
      className={`flex overflow-x-auto border-b border-zinc-200 ${containerClass}`}
    >
      {tabs.map((tab) => (
        <button
          key={tab.key}
          type="button"
          onClick={() => onTabChange(tab.key)}
          className={`relative flex items-center gap-1.5 px-4 py-3 text-[10px] font-bold uppercase tracking-wider whitespace-nowrap border-b-2 transition-all cursor-pointer ${
            activeTab === tab.key
              ? "text-emerald-700 border-emerald-600 bg-white"
              : "text-zinc-500 border-transparent hover:text-zinc-700 hover:bg-white/50"
          }`}
        >
          {showIcon && tab.icon && <span className="h-4 w-4">{tab.icon}</span>}
          {tab.label}
        </button>
      ))}
    </div>
  );
}
