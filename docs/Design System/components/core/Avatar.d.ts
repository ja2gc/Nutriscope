import * as React from "react";

export interface AvatarProps {
  name?: string;
  src?: string | null;
  size?: number;
  style?: React.CSSProperties;
}

/** Circular avatar — initials fallback on brand-green soft fill, or a photo. */
export function Avatar(props: AvatarProps): React.JSX.Element;
