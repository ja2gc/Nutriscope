import React from "react";

/** Avatar — initials or photo, circular, brand-green soft fill by default. */
export function Avatar({ name = "", src = null, size = 36, style = {} }) {
  const initials = name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join("");
  return (
    <div
      style={{
        width: size,
        height: size,
        borderRadius: "50%",
        overflow: "hidden",
        flexShrink: 0,
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        background: "var(--brand-primary-soft)",
        border: "1px solid var(--green-200)",
        color: "var(--green-700)",
        fontFamily: "var(--font-sans)",
        fontWeight: 700,
        fontSize: size * 0.4,
        ...style,
      }}
    >
      {src ? (
        <img src={src} alt={name} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
      ) : (
        initials || "?"
      )}
    </div>
  );
}
