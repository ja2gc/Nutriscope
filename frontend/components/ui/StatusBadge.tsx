"use client";

import React from "react";

type Status = "success" | "warning" | "error" | "info" | "neutral";

const STATUS_STYLES: Record<Status, string> = {
  success: "bg-emerald-50 text-emerald-700 border-emerald-200",
  warning: "bg-amber-50 text-amber-700 border-amber-200",
  error: "bg-rose-50 text-rose-700 border-rose-200",
  info: "bg-blue-50 text-blue-700 border-blue-200",
  neutral: "bg-zinc-50 text-zinc-600 border-zinc-200",
};

const STATUS_DOT: Record<Status, string> = {
  success: "bg-emerald-500",
  warning: "bg-amber-500",
  error: "bg-rose-500",
  info: "bg-blue-500",
  neutral: "bg-zinc-300",
};

interface Props {
  label: string;
  status: Status;
  showDot?: boolean;
}

export default function StatusBadge({
  label,
  status = "neutral",
  showDot = false,
}: Props) {
  const styleClass = STATUS_STYLES[status];
  const dotClass = STATUS_DOT[status];

  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border ${styleClass}`}
    >
      {showDot && <span className={`h-1.5 w-1.5 rounded-full ${dotClass}`} />}
      {label}
    </span>
  );
}
