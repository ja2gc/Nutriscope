import * as React from "react";

export interface TabItem { id: string; label: string; }

export interface TabsProps {
  tabs: TabItem[];
  value: string;
  onChange?: (id: string) => void;
  style?: React.CSSProperties;
}

/** Underline tab bar with a brand-green active indicator. */
export function Tabs(props: TabsProps): React.JSX.Element;
