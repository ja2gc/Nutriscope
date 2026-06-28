import * as React from "react";

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Add the standard 20px inset. @default false */
  padded?: boolean;
  /** Lift shadow on hover (for clickable cards). @default false */
  interactive?: boolean;
}

/** The canonical white surface: subtle border, 16px radius, soft shadow. */
export function Card(props: CardProps): React.JSX.Element;
