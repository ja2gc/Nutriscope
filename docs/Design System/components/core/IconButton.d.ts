import * as React from "react";

export interface IconButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  size?: "sm" | "md" | "lg";
  tone?: "neutral" | "brand" | "accent" | "danger";
  active?: boolean;
  "aria-label": string;
}

/** Square icon-only button for header actions and row tools. */
export function IconButton(props: IconButtonProps): React.JSX.Element;
