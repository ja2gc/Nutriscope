import * as React from "react";

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  /** Field label (also derives the id). */
  label?: string;
  /** Validation message — switches the field to the error style. */
  error?: string;
  /** Neutral helper text below the field. */
  hint?: string;
}

/**
 * Labelled text input with brand-green focus ring and error state.
 *
 * @startingPoint section="Forms" subtitle="Labelled text field with focus & error states" viewport="700x140"
 */
export function Input(props: InputProps): React.JSX.Element;
