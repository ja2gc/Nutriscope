import * as React from "react";

export interface StatusBadgeProps {
  label: string;
  status?: "success" | "warning" | "error" | "info" | "neutral";
  showDot?: boolean;
  style?: React.CSSProperties;
}

/** Semantic status pill with an optional leading status dot. */
export function StatusBadge(props: StatusBadgeProps): React.JSX.Element;
