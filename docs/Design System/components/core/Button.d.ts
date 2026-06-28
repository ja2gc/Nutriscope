import * as React from "react";

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Visual style. @default "primary" */
  variant?: "primary" | "secondary" | "ghost" | "danger" | "accent";
  /** Control size. @default "md" */
  size?: "sm" | "md" | "lg";
  /** Stretch to fill the container width. @default false */
  fullWidth?: boolean;
  /** Show a spinner and disable. @default false */
  loading?: boolean;
  /** Optional icon node placed before the label. */
  leftIcon?: React.ReactNode;
}

/**
 * The primary interactive control for NutriScope. Green primary for positive
 * actions, secondary/ghost for neutral, danger for destructive, accent (orange)
 * for occasional emphasis.
 *
 * @startingPoint section="Core" subtitle="Primary action control with variants & sizes" viewport="700x160"
 */
export function Button(props: ButtonProps): React.JSX.Element;
