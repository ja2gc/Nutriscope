import * as React from "react";

export interface LogoProps {
  /** "light" for white surfaces, "forest" for the dark green nav. @default "light" */
  variant?: "light" | "forest";
  /** Hide the wordmark, show mark only. @default false */
  collapsed?: boolean;
  /** Mark pixel size; wordmark scales from it. @default 30 */
  size?: number;
}

/** NutriScope brand lockup: leaf+scope mark + Nutri (green) / Scope (orange) wordmark. */
export function Logo(props: LogoProps): React.JSX.Element;
