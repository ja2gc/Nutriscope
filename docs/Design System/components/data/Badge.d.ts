import * as React from "react";

export interface BadgeProps {
  tone?: "emerald" | "amber" | "red" | "sky" | "lime" | "neutral";
  children: React.ReactNode;
  style?: React.CSSProperties;
}

/** Small uppercase status/category pill. Tone carries meaning; always include text. */
export function Badge(props: BadgeProps): React.JSX.Element;
