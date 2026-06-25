"use client";

import React from "react";
import { statusDotClasses, statusToneClasses } from "./theme";

type Status = "success" | "warning" | "error" | "info" | "neutral";

const STATUS_STYLES: Record<Status, string> = {
  success: statusToneClasses.success,
  warning: statusToneClasses.warning,
  error: statusToneClasses.error,
  info: statusToneClasses.info,
  neutral: statusToneClasses.neutral,
};

const STATUS_DOT: Record<Status, string> = {
  success: statusDotClasses.success,
  warning: statusDotClasses.warning,
  error: statusDotClasses.error,
  info: statusDotClasses.info,
  neutral: statusDotClasses.neutral,
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
