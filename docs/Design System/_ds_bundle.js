/* @ds-bundle: {"format":3,"namespace":"NutriScopeDesignSystem_c4cce8","components":[{"name":"Avatar","sourcePath":"components/core/Avatar.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Card","sourcePath":"components/core/Card.jsx"},{"name":"IconButton","sourcePath":"components/core/IconButton.jsx"},{"name":"Logo","sourcePath":"components/core/Logo.jsx"},{"name":"Badge","sourcePath":"components/data/Badge.jsx"},{"name":"KpiCard","sourcePath":"components/data/KpiCard.jsx"},{"name":"StatusBadge","sourcePath":"components/data/StatusBadge.jsx"},{"name":"Tabs","sourcePath":"components/data/Tabs.jsx"},{"name":"Input","sourcePath":"components/forms/Input.jsx"}],"sourceHashes":{"components/core/Avatar.jsx":"d41003a83119","components/core/Button.jsx":"e6ee31494061","components/core/Card.jsx":"a31783172377","components/core/IconButton.jsx":"01a3336ec322","components/core/Logo.jsx":"a6d0d510cddd","components/data/Badge.jsx":"1106a696dfdc","components/data/KpiCard.jsx":"e7bd61513dc0","components/data/StatusBadge.jsx":"e280de7784ce","components/data/Tabs.jsx":"1b730a6bad6e","components/forms/Input.jsx":"0be107dafd92","ui_kits/mobile/MobileApp.jsx":"d11323432070","ui_kits/web/AppShell.jsx":"c1b5f71fbc93","ui_kits/web/DashboardScreen.jsx":"69685c3ef61b","ui_kits/web/FoodServiceScreen.jsx":"6812a7a4fd44","ui_kits/web/LoginScreen.jsx":"e46420a4fd48"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.NutriScopeDesignSystem_c4cce8 = window.NutriScopeDesignSystem_c4cce8 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/core/Avatar.jsx
try { (() => {
/** Avatar — initials or photo, circular, brand-green soft fill by default. */
function Avatar({
  name = "",
  src = null,
  size = 36,
  style = {}
}) {
  const initials = name.split(" ").filter(Boolean).slice(0, 2).map(p => p[0]?.toUpperCase()).join("");
  return /*#__PURE__*/React.createElement("div", {
    style: {
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
      ...style
    }
  }, src ? /*#__PURE__*/React.createElement("img", {
    src: src,
    alt: name,
    style: {
      width: "100%",
      height: "100%",
      objectFit: "cover"
    }
  }) : initials || "?");
}
Object.assign(__ds_scope, { Avatar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Avatar.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const sizes = {
  sm: {
    padding: "6px 12px",
    fontSize: "13px",
    gap: "6px"
  },
  md: {
    padding: "9px 16px",
    fontSize: "14px",
    gap: "8px"
  },
  lg: {
    padding: "12px 22px",
    fontSize: "15px",
    gap: "8px"
  }
};
const variants = {
  primary: {
    background: "var(--brand-primary)",
    color: "var(--text-onbrand)",
    border: "1px solid transparent",
    boxShadow: "var(--shadow-xs)",
    "--hover-bg": "var(--brand-primary-hover)"
  },
  secondary: {
    background: "var(--surface-card)",
    color: "var(--text-body)",
    border: "1px solid var(--border-strong)",
    "--hover-bg": "var(--surface-sunken)"
  },
  ghost: {
    background: "transparent",
    color: "var(--text-muted)",
    border: "1px solid transparent",
    "--hover-bg": "var(--surface-hover)"
  },
  danger: {
    background: "var(--status-danger)",
    color: "#fff",
    border: "1px solid transparent",
    "--hover-bg": "#b91c1c"
  },
  accent: {
    background: "var(--brand-accent)",
    color: "#fff",
    border: "1px solid transparent",
    "--hover-bg": "var(--orange-700)"
  }
};

/**
 * Button — the primary interactive control.
 * variant: primary | secondary | ghost | danger | accent
 * size: sm | md | lg
 */
function Button({
  children,
  variant = "primary",
  size = "md",
  fullWidth = false,
  loading = false,
  disabled = false,
  leftIcon = null,
  style = {},
  ...props
}) {
  const [hover, setHover] = React.useState(false);
  const v = variants[variant] || variants.primary;
  const s = sizes[size] || sizes.md;
  const isDisabled = disabled || loading;
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    disabled: isDisabled,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      gap: s.gap,
      padding: s.padding,
      fontSize: s.fontSize,
      fontFamily: "var(--font-sans)",
      fontWeight: 600,
      lineHeight: 1.2,
      borderRadius: "var(--radius-md)",
      cursor: isDisabled ? "not-allowed" : "pointer",
      opacity: isDisabled ? 0.55 : 1,
      width: fullWidth ? "100%" : "auto",
      transition: "background var(--dur-fast) var(--ease-out), box-shadow var(--dur-fast) var(--ease-out)",
      background: hover && !isDisabled && v["--hover-bg"] ? v["--hover-bg"] : v.background,
      color: v.color,
      border: v.border,
      boxShadow: v.boxShadow || "none",
      ...style
    }
  }, props), loading && /*#__PURE__*/React.createElement("span", {
    style: {
      width: "14px",
      height: "14px",
      border: "2px solid currentColor",
      borderTopColor: "transparent",
      borderRadius: "50%",
      display: "inline-block",
      animation: "ns-spin 0.6s linear infinite"
    }
  }), !loading && leftIcon, loading ? "Processing…" : children, /*#__PURE__*/React.createElement("style", null, `@keyframes ns-spin{to{transform:rotate(360deg)}}`));
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Card — the canonical NutriScope surface. White, subtle border, 16px radius,
 * soft shadow. `padded` adds the standard 20px inset.
 */
function Card({
  children,
  padded = false,
  interactive = false,
  style = {},
  ...props
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", _extends({
    onMouseEnter: () => interactive && setHover(true),
    onMouseLeave: () => interactive && setHover(false),
    style: {
      background: "var(--surface-card)",
      border: "1px solid var(--border-subtle)",
      borderRadius: "var(--radius-xl)",
      boxShadow: hover ? "var(--shadow-md)" : "var(--shadow-sm)",
      padding: padded ? "var(--space-5)" : 0,
      transition: "box-shadow var(--dur-base) var(--ease-out)",
      cursor: interactive ? "pointer" : "default",
      ...style
    }
  }, props), children);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Card.jsx", error: String((e && e.message) || e) }); }

// components/core/IconButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * IconButton — square, icon-only affordance (header actions, table row tools).
 * Pass a Lucide (or any) icon node as children.
 */
function IconButton({
  children,
  size = "md",
  tone = "neutral",
  active = false,
  style = {},
  "aria-label": ariaLabel,
  ...props
}) {
  const [hover, setHover] = React.useState(false);
  const dim = size === "sm" ? 30 : size === "lg" ? 42 : 36;
  const tones = {
    neutral: {
      color: "var(--text-muted)",
      hover: "var(--surface-hover)"
    },
    brand: {
      color: "var(--brand-primary)",
      hover: "var(--brand-primary-soft)"
    },
    accent: {
      color: "var(--brand-accent)",
      hover: "var(--brand-accent-soft)"
    },
    danger: {
      color: "var(--status-danger)",
      hover: "var(--status-danger-bg)"
    }
  };
  const t = tones[tone] || tones.neutral;
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    "aria-label": ariaLabel,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      width: dim,
      height: dim,
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      borderRadius: "var(--radius-md)",
      border: "none",
      cursor: "pointer",
      color: t.color,
      background: active || hover ? t.hover : "transparent",
      transition: "background var(--dur-fast) var(--ease-out)",
      ...style
    }
  }, props), children);
}
Object.assign(__ds_scope, { IconButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/IconButton.jsx", error: String((e && e.message) || e) }); }

// components/core/Logo.jsx
try { (() => {
function Mark({
  size = 30
}) {
  return /*#__PURE__*/React.createElement("svg", {
    width: size,
    height: size,
    viewBox: "0 0 32 32",
    fill: "none",
    style: {
      flexShrink: 0
    }
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "16",
    cy: "16",
    r: "12",
    stroke: "#ea580c",
    strokeWidth: "1.5",
    strokeDasharray: "4 2",
    opacity: "0.75"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "16",
    cy: "16",
    r: "6",
    stroke: "#ea580c",
    strokeWidth: "1",
    opacity: "0.4"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "16",
    y1: "2",
    x2: "16",
    y2: "6",
    stroke: "#ea580c",
    strokeWidth: "1.5"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "16",
    y1: "26",
    x2: "16",
    y2: "30",
    stroke: "#ea580c",
    strokeWidth: "1.5"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "2",
    y1: "16",
    x2: "6",
    y2: "16",
    stroke: "#ea580c",
    strokeWidth: "1.5"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "26",
    y1: "16",
    x2: "30",
    y2: "16",
    stroke: "#ea580c",
    strokeWidth: "1.5"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M16 8C16 8 10 13 10 18C10 21.3137 12.6863 24 16 24C19.3137 24 22 21.3137 22 18C22 13 16 8 16 8Z",
    fill: "#059669"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M16 8C16 13 18.5 17 21 19.5",
    stroke: "#d1fae5",
    strokeWidth: "1",
    strokeLinecap: "round",
    opacity: "0.9"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M16 24V14",
    stroke: "#10b981",
    strokeWidth: "1.2",
    strokeLinecap: "round"
  }));
}

/**
 * Logo — leaf + diagnostic-scope mark with the Nutri/Scope wordmark.
 * variant "light" for white surfaces, "forest" for the dark green nav.
 */
function Logo({
  variant = "light",
  collapsed = false,
  size = 30
}) {
  const nutri = variant === "forest" ? "#34d399" : "var(--brand-nutri)";
  const scope = variant === "forest" ? "#fb923c" : "var(--brand-scope)";
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: "10px",
      userSelect: "none"
    }
  }, /*#__PURE__*/React.createElement(Mark, {
    size: size
  }), !collapsed && /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "baseline",
      fontFamily: "var(--font-sans)"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: size * 0.55,
      fontWeight: 800,
      letterSpacing: "-0.03em",
      color: nutri
    }
  }, "Nutri"), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: size * 0.55,
      fontWeight: 800,
      letterSpacing: "-0.03em",
      color: scope
    }
  }, "Scope")));
}
Object.assign(__ds_scope, { Logo });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Logo.jsx", error: String((e && e.message) || e) }); }

// components/data/Badge.jsx
try { (() => {
const TONES = {
  emerald: {
    bg: "var(--green-50)",
    fg: "var(--green-700)",
    bd: "var(--green-200)"
  },
  amber: {
    bg: "var(--orange-50)",
    fg: "var(--orange-700)",
    bd: "var(--orange-200)"
  },
  red: {
    bg: "var(--status-danger-bg)",
    fg: "var(--status-danger)",
    bd: "#fecaca"
  },
  sky: {
    bg: "var(--status-info-bg)",
    fg: "var(--status-info)",
    bd: "#bae6fd"
  },
  lime: {
    bg: "#f7fee7",
    fg: "var(--lime-600)",
    bd: "#d9f99d"
  },
  neutral: {
    bg: "var(--neutral-100)",
    fg: "var(--neutral-600)",
    bd: "var(--neutral-200)"
  }
};

/** Badge — small status/category pill. Pair tone with text, never color alone. */
function Badge({
  children,
  tone = "neutral",
  style = {}
}) {
  const t = TONES[tone] || TONES.neutral;
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: "5px",
      padding: "3px 9px",
      borderRadius: "var(--radius-full)",
      background: t.bg,
      color: t.fg,
      border: `1px solid ${t.bd}`,
      fontFamily: "var(--font-sans)",
      fontSize: "11px",
      fontWeight: 700,
      textTransform: "uppercase",
      letterSpacing: "0.04em",
      whiteSpace: "nowrap",
      ...style
    }
  }, children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Badge.jsx", error: String((e && e.message) || e) }); }

// components/data/KpiCard.jsx
try { (() => {
const TONES = {
  neutral: {
    bg: "var(--neutral-50)",
    bd: "var(--neutral-200)",
    fg: "var(--neutral-700)"
  },
  emerald: {
    bg: "var(--green-50)",
    bd: "var(--green-200)",
    fg: "var(--green-700)"
  },
  amber: {
    bg: "var(--orange-50)",
    bd: "var(--orange-200)",
    fg: "var(--orange-700)"
  },
  red: {
    bg: "var(--status-danger-bg)",
    bd: "#fecaca",
    fg: "var(--status-danger)"
  },
  sky: {
    bg: "var(--status-info-bg)",
    bd: "#bae6fd",
    fg: "var(--status-info)"
  }
};

/**
 * KpiCard — metric tile: uppercase label, big tabular value, optional hint.
 * Tone tints the whole tile for at-a-glance scanning.
 */
function KpiCard({
  label,
  value,
  hint,
  tone = "neutral",
  icon = null,
  style = {}
}) {
  const t = TONES[tone] || TONES.neutral;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "14px 16px",
      borderRadius: "var(--radius-xl)",
      background: t.bg,
      border: `1px solid ${t.bd}`,
      fontFamily: "var(--font-sans)",
      color: t.fg,
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      gap: "8px"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: "10.5px",
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.07em",
      opacity: 0.72
    }
  }, label), icon), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: "26px",
      fontWeight: 800,
      marginTop: "4px",
      fontFamily: "var(--font-mono)",
      letterSpacing: "-0.01em",
      fontVariantNumeric: "tabular-nums"
    }
  }, value), hint && /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: "11px",
      marginTop: "3px",
      opacity: 0.66
    }
  }, hint));
}
Object.assign(__ds_scope, { KpiCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/KpiCard.jsx", error: String((e && e.message) || e) }); }

// components/data/StatusBadge.jsx
try { (() => {
const STATUS = {
  success: {
    bg: "var(--status-success-bg)",
    fg: "var(--status-success)"
  },
  warning: {
    bg: "var(--status-warning-bg)",
    fg: "var(--status-warning)"
  },
  error: {
    bg: "var(--status-danger-bg)",
    fg: "var(--status-danger)"
  },
  info: {
    bg: "var(--status-info-bg)",
    fg: "var(--status-info)"
  },
  neutral: {
    bg: "var(--status-neutral-bg)",
    fg: "var(--status-neutral)"
  }
};

/** StatusBadge — pill with an optional leading dot. Semantic status states. */
function StatusBadge({
  label,
  status = "neutral",
  showDot = true,
  style = {}
}) {
  const s = STATUS[status] || STATUS.neutral;
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: "6px",
      padding: "4px 10px",
      borderRadius: "var(--radius-full)",
      background: s.bg,
      color: s.fg,
      fontFamily: "var(--font-sans)",
      fontSize: "11px",
      fontWeight: 700,
      ...style
    }
  }, showDot && /*#__PURE__*/React.createElement("span", {
    style: {
      width: "6px",
      height: "6px",
      borderRadius: "50%",
      background: s.fg
    }
  }), label);
}
Object.assign(__ds_scope, { StatusBadge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/StatusBadge.jsx", error: String((e && e.message) || e) }); }

// components/data/Tabs.jsx
try { (() => {
/**
 * Tabs — underline tab bar. `tabs` is [{id,label}]; controlled via `value`/`onChange`.
 */
function Tabs({
  tabs = [],
  value,
  onChange,
  style = {}
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: "2px",
      borderBottom: "1px solid var(--border-subtle)",
      fontFamily: "var(--font-sans)",
      ...style
    }
  }, tabs.map(t => {
    const active = t.id === value;
    return /*#__PURE__*/React.createElement("button", {
      key: t.id,
      type: "button",
      onClick: () => onChange?.(t.id),
      style: {
        appearance: "none",
        background: "transparent",
        border: "none",
        borderBottom: `2px solid ${active ? "var(--brand-primary)" : "transparent"}`,
        padding: "10px 14px",
        marginBottom: "-1px",
        fontSize: "13px",
        fontWeight: active ? 700 : 500,
        color: active ? "var(--green-700)" : "var(--text-muted)",
        cursor: "pointer",
        transition: "color var(--dur-fast) var(--ease-out)"
      }
    }, t.label);
  }));
}
Object.assign(__ds_scope, { Tabs });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Tabs.jsx", error: String((e && e.message) || e) }); }

// components/forms/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Input — labelled text field. Brand-green focus ring, error state.
 * Always pass a `label`; pass `error` to show a validation message.
 */
function Input({
  label,
  error,
  hint,
  id,
  style = {},
  ...props
}) {
  const [focus, setFocus] = React.useState(false);
  const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, "-") : undefined);
  const borderColor = error ? "var(--status-danger)" : focus ? "var(--brand-primary)" : "var(--border-strong)";
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "6px",
      width: "100%",
      fontFamily: "var(--font-sans)"
    }
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: inputId,
    style: {
      fontSize: "13px",
      fontWeight: 600,
      color: "var(--text-body)"
    }
  }, label), /*#__PURE__*/React.createElement("input", _extends({
    id: inputId,
    onFocus: e => {
      setFocus(true);
      props.onFocus?.(e);
    },
    onBlur: e => {
      setFocus(false);
      props.onBlur?.(e);
    },
    style: {
      width: "100%",
      boxSizing: "border-box",
      padding: "9px 13px",
      fontSize: "14px",
      fontFamily: "var(--font-sans)",
      color: "var(--text-strong)",
      background: "var(--surface-card)",
      border: `1px solid ${borderColor}`,
      borderRadius: "var(--radius-md)",
      outline: "none",
      boxShadow: focus ? "var(--ring-focus)" : "none",
      transition: "border-color var(--dur-fast) var(--ease-out), box-shadow var(--dur-fast) var(--ease-out)",
      ...style
    }
  }, props)), error ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: "12px",
      fontWeight: 600,
      color: "var(--status-danger)"
    }
  }, error) : hint ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: "12px",
      color: "var(--text-muted)"
    }
  }, hint) : null);
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Input.jsx", error: String((e && e.message) || e) }); }

// ui_kits/mobile/MobileApp.jsx
try { (() => {
// NutriScope FSS Mobile — improved, on-brand recreation.
// Fixes vs shipped app: warm green-tinted surfaces (not cold gray), brand
// green/orange accents replacing the off-brand purple announcements, softer
// cards, lively KPI chips, green active tabs.

function MIcon({
  n,
  size = 22,
  color = "currentColor",
  style
}) {
  const r = React.useRef(null);
  React.useEffect(() => {
    if (r.current) {
      r.current.innerHTML = `<i data-lucide="${n}"></i>`;
      window.lucide && lucide.createIcons({
        attrs: {
          width: size,
          height: size,
          stroke: color
        }
      });
    }
  }, [n, size, color]);
  return /*#__PURE__*/React.createElement("span", {
    ref: r,
    style: {
      display: "inline-flex",
      color,
      ...style
    }
  });
}

/* ---------------- Header ---------------- */
function Header({
  title
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--surface-card)",
      borderBottom: "1px solid var(--border-subtle)",
      padding: "12px 16px",
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 9
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/mark.svg",
    width: "24",
    height: "24",
    alt: ""
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 16,
      fontWeight: 700,
      color: "var(--text-strong)"
    }
  }, title)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 4
    }
  }, /*#__PURE__*/React.createElement("button", {
    style: iconBtn,
    "aria-label": "Announcements"
  }, /*#__PURE__*/React.createElement(MIcon, {
    n: "megaphone",
    size: 20,
    color: "var(--neutral-600)"
  })), /*#__PURE__*/React.createElement("button", {
    style: {
      ...iconBtn,
      position: "relative"
    },
    "aria-label": "Notifications"
  }, /*#__PURE__*/React.createElement(MIcon, {
    n: "bell",
    size: 20,
    color: "var(--neutral-600)"
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      top: 4,
      right: 4,
      minWidth: 15,
      height: 15,
      borderRadius: 999,
      background: "var(--brand-accent)",
      color: "#fff",
      fontSize: 9,
      fontWeight: 800,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      padding: "0 3px"
    }
  }, "2")), /*#__PURE__*/React.createElement("button", {
    style: iconBtn,
    "aria-label": "Account"
  }, /*#__PURE__*/React.createElement(MIcon, {
    n: "circle-user-round",
    size: 20,
    color: "var(--neutral-600)"
  }))));
}
const iconBtn = {
  width: 38,
  height: 38,
  display: "flex",
  alignItems: "center",
  justifyContent: "center",
  background: "transparent",
  border: "none",
  cursor: "pointer",
  borderRadius: 10
};

/* ---------------- Dashboard ---------------- */
function Dashboard() {
  const kpis = [{
    icon: "clipboard-list",
    label: "Meals to log today",
    value: 18,
    fg: "var(--green-700)",
    bg: "var(--green-50)",
    bd: "var(--green-200)"
  }, {
    icon: "shopping-bag",
    label: "POs awaiting receipt",
    value: 3,
    fg: "var(--orange-700)",
    bg: "var(--orange-50)",
    bd: "var(--orange-200)"
  }, {
    icon: "package",
    label: "Items out of stock",
    value: 5,
    fg: "var(--status-danger)",
    bg: "var(--status-danger-bg)",
    bd: "#fecaca"
  }];
  const service = [{
    meal: "Breakfast",
    name: "Arroz caldo · soft diet",
    prepped: true
  }, {
    meal: "Lunch",
    name: "Tilapia, brown rice, malunggay",
    prepped: true,
    shortfall: true
  }, {
    meal: "Dinner",
    name: "Chicken tinola, low-sodium",
    prepped: false
  }];
  const anns = [{
    cat: "Kitchen",
    title: "Supplier change for fresh produce",
    body: "New vendor starts Monday. Check procurement for updated prices.",
    time: "2h ago",
    pinned: true
  }, {
    cat: "Policy",
    title: "Low-sodium menu rollout",
    body: "Applies to all cardiac-ward trays from next cycle.",
    time: "1d ago"
  }];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "16px 14px 90px",
      display: "flex",
      flexDirection: "column",
      gap: 20
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: 10
    }
  }, kpis.map(k => /*#__PURE__*/React.createElement("div", {
    key: k.label,
    style: {
      display: "flex",
      alignItems: "center",
      gap: 14,
      background: "var(--surface-card)",
      border: `1px solid ${k.bd}`,
      borderRadius: 16,
      padding: "15px 16px",
      boxShadow: "var(--shadow-xs)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 44,
      height: 44,
      borderRadius: 12,
      background: k.bg,
      display: "flex",
      alignItems: "center",
      justifyContent: "center"
    }
  }, /*#__PURE__*/React.createElement(MIcon, {
    n: k.icon,
    size: 22,
    color: k.fg
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12.5,
      color: "var(--text-muted)"
    }
  }, k.label), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 26,
      fontWeight: 800,
      color: k.fg,
      fontFamily: "var(--font-mono)"
    }
  }, k.value)), /*#__PURE__*/React.createElement(MIcon, {
    n: "chevron-right",
    size: 18,
    color: "var(--text-faint)"
  })))), /*#__PURE__*/React.createElement(Section, {
    title: "Today's service",
    icon: "utensils"
  }, /*#__PURE__*/React.createElement("div", {
    style: cardWrap
  }, service.map((s, i) => /*#__PURE__*/React.createElement("div", {
    key: s.meal,
    style: {
      display: "flex",
      alignItems: "center",
      padding: "13px 15px",
      borderBottom: i < service.length - 1 ? "1px solid var(--border-subtle)" : "none"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 10.5,
      color: "var(--text-faint)",
      textTransform: "uppercase",
      letterSpacing: "0.06em",
      fontWeight: 700
    }
  }, s.meal), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13.5,
      fontWeight: 600,
      color: "var(--text-strong)",
      marginTop: 2
    }
  }, s.name)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 6
    }
  }, s.prepped && /*#__PURE__*/React.createElement(Pill, {
    label: "Prepped",
    tone: "green"
  }), s.shortfall && /*#__PURE__*/React.createElement(Pill, {
    label: "Shortfall",
    tone: "red"
  }), !s.prepped && /*#__PURE__*/React.createElement(Pill, {
    label: "To prep",
    tone: "amber"
  })))))), /*#__PURE__*/React.createElement(Section, {
    title: "Announcements",
    icon: "megaphone",
    iconColor: "var(--orange-600)"
  }, /*#__PURE__*/React.createElement("div", {
    style: cardWrap
  }, anns.map((a, i) => /*#__PURE__*/React.createElement("div", {
    key: a.title,
    style: {
      padding: "13px 15px",
      borderBottom: i < anns.length - 1 ? "1px solid var(--border-subtle)" : "none",
      background: a.pinned ? "var(--orange-50)" : "transparent"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 7,
      marginBottom: 5
    }
  }, a.pinned && /*#__PURE__*/React.createElement(MIcon, {
    n: "pin",
    size: 12,
    color: "var(--orange-600)"
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 10,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.05em",
      color: a.cat === "Kitchen" ? "var(--lime-600)" : "var(--green-700)",
      background: a.cat === "Kitchen" ? "#f7fee7" : "var(--green-50)",
      padding: "2px 7px",
      borderRadius: 999
    }
  }, a.cat), /*#__PURE__*/React.createElement("span", {
    style: {
      marginLeft: "auto",
      fontSize: 11,
      color: "var(--text-faint)",
      fontFamily: "var(--font-mono)"
    }
  }, a.time)), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13.5,
      fontWeight: 700,
      color: "var(--text-strong)"
    }
  }, a.title), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12.5,
      color: "var(--text-muted)",
      marginTop: 3,
      lineHeight: 1.45
    }
  }, a.body))))));
}

/* ---------------- Inventory ---------------- */
function Inventory() {
  const [filter, setFilter] = React.useState("all");
  const items = [{
    name: "Chicken breast, skinless",
    type: "ingredient",
    qty: "42.0",
    unit: "kg",
    ok: true
  }, {
    name: "Brown rice",
    type: "ingredient",
    qty: "0",
    unit: "kg",
    ok: false
  }, {
    name: "Malunggay leaves",
    type: "ingredient",
    qty: "8.5",
    unit: "kg",
    ok: true
  }, {
    name: "Low-sodium broth",
    type: "supply",
    qty: "0",
    unit: "L",
    ok: false
  }, {
    name: "Banana, lakatan",
    type: "ingredient",
    qty: "120",
    unit: "pcs",
    ok: true
  }, {
    name: "Tilapia fillet",
    type: "ingredient",
    qty: "16.2",
    unit: "kg",
    ok: true
  }];
  const filters = [["all", "All"], ["ingredient", "Ingredients"], ["supply", "Supplies"], ["recipe", "Recipes"]];
  const rows = items.filter(i => filter === "all" || i.type === filter);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      paddingBottom: 90
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--surface-card)",
      borderBottom: "1px solid var(--border-subtle)",
      padding: "12px 14px",
      display: "flex",
      flexDirection: "column",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 9,
      background: "var(--surface-sunken)",
      borderRadius: 12,
      padding: "10px 12px"
    }
  }, /*#__PURE__*/React.createElement(MIcon, {
    n: "search",
    size: 16,
    color: "var(--text-faint)"
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 13.5,
      color: "var(--text-faint)"
    }
  }, "Search inventory\u2026")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 7
    }
  }, filters.map(([v, l]) => /*#__PURE__*/React.createElement("button", {
    key: v,
    onClick: () => setFilter(v),
    style: {
      padding: "6px 12px",
      borderRadius: 999,
      fontSize: 12,
      fontWeight: 700,
      cursor: "pointer",
      border: filter === v ? "1px solid var(--brand-primary)" : "1px solid var(--border-subtle)",
      background: filter === v ? "var(--brand-primary)" : "var(--surface-card)",
      color: filter === v ? "#fff" : "var(--text-muted)"
    }
  }, l)))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 16,
      padding: "10px 16px",
      background: "var(--surface-card)",
      borderBottom: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement(Stat, {
    label: "Total",
    value: "248",
    color: "var(--text-strong)"
  }), /*#__PURE__*/React.createElement(Stat, {
    label: "In stock",
    value: "243",
    color: "var(--green-600)"
  }), /*#__PURE__*/React.createElement(Stat, {
    label: "No stock",
    value: "5",
    color: "var(--status-danger)"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--surface-card)"
    }
  }, rows.map((it, i) => /*#__PURE__*/React.createElement("div", {
    key: it.name,
    style: {
      display: "flex",
      alignItems: "center",
      padding: "13px 16px",
      borderBottom: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 14,
      fontWeight: 600,
      color: "var(--text-strong)"
    }
  }, it.name), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11.5,
      color: "var(--text-faint)",
      textTransform: "capitalize",
      marginTop: 2
    }
  }, it.type)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      alignItems: "flex-end",
      gap: 5
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 14,
      fontWeight: 700,
      color: "var(--text-strong)",
      fontFamily: "var(--font-mono)"
    }
  }, it.qty, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 11,
      fontWeight: 400,
      color: "var(--text-muted)"
    }
  }, " ", it.unit)), /*#__PURE__*/React.createElement(Pill, {
    label: it.ok ? "In stock" : "No stock",
    tone: it.ok ? "green" : "red"
  }))))));
}

/* ---------------- Menu ---------------- */
function Menu() {
  const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
  const [active, setActive] = React.useState("Tue");
  const meals = [{
    meal: "Breakfast",
    item: "Arroz caldo, boiled egg",
    kcal: 420,
    cost: "18.50"
  }, {
    meal: "Lunch",
    item: "Tilapia, brown rice, malunggay",
    kcal: 640,
    cost: "27.00"
  }, {
    meal: "Snack",
    item: "Banana, lakatan",
    kcal: 110,
    cost: "6.00"
  }, {
    meal: "Dinner",
    item: "Chicken tinola, low-sodium",
    kcal: 520,
    cost: "22.90"
  }];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      paddingBottom: 90
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--surface-card)",
      borderBottom: "1px solid var(--border-subtle)",
      padding: "12px 10px",
      display: "flex",
      gap: 6,
      justifyContent: "space-between"
    }
  }, days.map(d => /*#__PURE__*/React.createElement("button", {
    key: d,
    onClick: () => setActive(d),
    style: {
      flex: 1,
      padding: "8px 0",
      borderRadius: 10,
      fontSize: 12,
      fontWeight: 700,
      cursor: "pointer",
      border: "none",
      background: active === d ? "var(--brand-primary)" : "transparent",
      color: active === d ? "#fff" : "var(--text-muted)"
    }
  }, d))), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "14px 14px 0",
      display: "flex",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      background: "var(--green-50)",
      border: "1px solid var(--green-200)",
      borderRadius: 14,
      padding: "11px 13px"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 10.5,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.06em",
      color: "var(--green-700)",
      opacity: 0.7
    }
  }, "Cost / head"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 22,
      fontWeight: 800,
      color: "var(--green-700)",
      fontFamily: "var(--font-mono)"
    }
  }, "\u20B174.40")), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      background: "var(--surface-card)",
      border: "1px solid var(--border-subtle)",
      borderRadius: 14,
      padding: "11px 13px"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 10.5,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.06em",
      color: "var(--text-muted)"
    }
  }, "Total kcal"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 22,
      fontWeight: 800,
      color: "var(--text-strong)",
      fontFamily: "var(--font-mono)"
    }
  }, "1,690"))), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 14
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: cardWrap
  }, meals.map((m, i) => /*#__PURE__*/React.createElement("div", {
    key: m.meal,
    style: {
      display: "flex",
      alignItems: "center",
      padding: "13px 15px",
      borderBottom: i < meals.length - 1 ? "1px solid var(--border-subtle)" : "none"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 10.5,
      color: "var(--text-faint)",
      textTransform: "uppercase",
      letterSpacing: "0.06em",
      fontWeight: 700
    }
  }, m.meal), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13.5,
      fontWeight: 600,
      color: "var(--text-strong)",
      marginTop: 2
    }
  }, m.item)), /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: "right"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      fontWeight: 700,
      color: "var(--green-700)",
      fontFamily: "var(--font-mono)"
    }
  }, "\u20B1", m.cost), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      color: "var(--text-faint)",
      fontFamily: "var(--font-mono)"
    }
  }, m.kcal, " kcal")))))));
}

/* ---------------- shared bits ---------------- */
const cardWrap = {
  background: "var(--surface-card)",
  border: "1px solid var(--border-subtle)",
  borderRadius: 16,
  overflow: "hidden",
  boxShadow: "var(--shadow-xs)"
};
function Section({
  title,
  icon,
  iconColor = "var(--green-700)",
  children
}) {
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 8,
      marginBottom: 10,
      padding: "0 2px"
    }
  }, /*#__PURE__*/React.createElement(MIcon, {
    n: icon,
    size: 17,
    color: iconColor
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 15,
      fontWeight: 700,
      color: "var(--text-strong)"
    }
  }, title)), children);
}
function Pill({
  label,
  tone
}) {
  const tones = {
    green: ["var(--green-50)", "var(--green-700)"],
    red: ["var(--status-danger-bg)", "var(--status-danger)"],
    amber: ["var(--orange-50)", "var(--orange-700)"]
  };
  const [bg, fg] = tones[tone] || tones.green;
  return /*#__PURE__*/React.createElement("span", {
    style: {
      padding: "3px 9px",
      borderRadius: 999,
      background: bg,
      color: fg,
      fontSize: 11,
      fontWeight: 700
    }
  }, label);
}
function Stat({
  label,
  value,
  color
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "baseline",
      gap: 5
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 16,
      fontWeight: 800,
      color,
      fontFamily: "var(--font-mono)"
    }
  }, value), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 12,
      color: "var(--text-faint)"
    }
  }, label));
}

/* ---------------- Tab bar + app ---------------- */
const TABS = [{
  id: "dashboard",
  label: "Dashboard",
  icon: "layout-dashboard",
  title: "Dashboard"
}, {
  id: "menu",
  label: "Menu",
  icon: "calendar-days",
  title: "Menu Cycle"
}, {
  id: "prep",
  label: "Prep",
  icon: "bar-chart-3",
  title: "Prep"
}, {
  id: "inventory",
  label: "Inventory",
  icon: "package",
  title: "Inventory"
}];
function MobileApp() {
  const [tab, setTab] = React.useState("dashboard");
  React.useEffect(() => {
    window.lucide && lucide.createIcons();
  });
  const current = TABS.find(t => t.id === tab);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      height: "100%",
      background: "var(--surface-page)"
    }
  }, /*#__PURE__*/React.createElement(Header, {
    title: current.title
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      overflowY: "auto"
    }
  }, tab === "dashboard" && /*#__PURE__*/React.createElement(Dashboard, null), tab === "inventory" && /*#__PURE__*/React.createElement(Inventory, null), tab === "menu" && /*#__PURE__*/React.createElement(Menu, null), tab === "prep" && /*#__PURE__*/React.createElement(Placeholder, {
    label: "Prep tracking"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      background: "var(--surface-card)",
      borderTop: "1px solid var(--border-subtle)",
      paddingBottom: 6
    }
  }, TABS.map(t => {
    const on = t.id === tab;
    return /*#__PURE__*/React.createElement("button", {
      key: t.id,
      onClick: () => setTab(t.id),
      style: {
        flex: 1,
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        gap: 3,
        padding: "9px 0",
        background: "transparent",
        border: "none",
        cursor: "pointer"
      }
    }, /*#__PURE__*/React.createElement(MIcon, {
      n: t.icon,
      size: 22,
      color: on ? "var(--brand-primary)" : "var(--neutral-400)"
    }), /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 10.5,
        fontWeight: on ? 700 : 500,
        color: on ? "var(--brand-primary)" : "var(--neutral-400)"
      }
    }, t.label));
  })));
}
function Placeholder({
  label
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 40,
      textAlign: "center",
      color: "var(--text-faint)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 60
    }
  }, /*#__PURE__*/React.createElement(MIcon, {
    n: "bar-chart-3",
    size: 40,
    color: "var(--neutral-300)"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 12,
      fontSize: 14
    }
  }, label));
}
window.MobileApp = MobileApp;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/mobile/MobileApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/web/AppShell.jsx
try { (() => {
// NutriScope — App shell: forest-green sidebar + top bar.
// The brand uplift: nav is deep forest green (not near-black), with a green/lime
// active accent. Composes Logo + Avatar from the DS bundle.
const {
  Logo,
  Avatar
} = window.NutriScopeDesignSystem_c4cce8;
const NAV = [{
  id: "dashboard",
  label: "Dashboard",
  icon: "compass"
}, {
  id: "announcements",
  label: "Announcements",
  icon: "megaphone"
}, {
  id: "food-library",
  label: "Food Library",
  icon: "cooking-pot"
}, {
  id: "nutrition",
  label: "Nutrition Care",
  icon: "heart-handshake",
  children: ["Patients", "Assessment", "Diagnosis", "Intervention", "Monitoring"]
}, {
  id: "food-service",
  label: "Food Service",
  icon: "salad",
  children: ["Inventory", "Menu Cycle", "Budget", "Procurement"]
}, {
  id: "reports",
  label: "Reports",
  icon: "trending-up"
}, {
  id: "settings",
  label: "Settings",
  icon: "sliders"
}];
const TITLES = {
  dashboard: "Overview & Operations Center",
  "food-service": "Food Service & Kitchen Operations"
};
function Icon({
  n,
  size = 18,
  color
}) {
  const r = React.useRef(null);
  React.useEffect(() => {
    if (r.current) {
      r.current.innerHTML = `<i data-lucide="${n}"></i>`;
      window.lucide && lucide.createIcons({
        attrs: {
          width: size,
          height: size
        }
      });
    }
  }, [n, size]);
  return /*#__PURE__*/React.createElement("span", {
    ref: r,
    style: {
      display: "inline-flex",
      color
    }
  });
}
function AppShell({
  active,
  onNavigate,
  children
}) {
  const [openGroup, setOpenGroup] = React.useState("food-service");
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      minHeight: "100%",
      background: "var(--surface-page)"
    }
  }, /*#__PURE__*/React.createElement("aside", {
    style: {
      width: 248,
      flexShrink: 0,
      background: "linear-gradient(180deg, var(--forest-900) 0%, var(--forest-950) 100%)",
      borderRight: "1px solid var(--forest-line)",
      display: "flex",
      flexDirection: "column",
      position: "sticky",
      top: 0,
      height: "100vh"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      height: 60,
      display: "flex",
      alignItems: "center",
      padding: "0 20px",
      borderBottom: "1px solid var(--forest-line)"
    }
  }, /*#__PURE__*/React.createElement(Logo, {
    variant: "forest",
    size: 28
  })), /*#__PURE__*/React.createElement("nav", {
    style: {
      flex: 1,
      padding: "14px 12px",
      display: "flex",
      flexDirection: "column",
      gap: 3,
      overflowY: "auto"
    }
  }, NAV.map(item => {
    const isActive = active === item.id;
    const hasChildren = !!item.children;
    const isOpen = openGroup === item.id;
    return /*#__PURE__*/React.createElement("div", {
      key: item.id
    }, /*#__PURE__*/React.createElement("button", {
      onClick: () => hasChildren ? setOpenGroup(isOpen ? null : item.id) : onNavigate?.(item.id),
      style: {
        width: "100%",
        display: "flex",
        alignItems: "center",
        gap: 11,
        padding: "9px 11px",
        border: "none",
        borderRadius: "var(--radius-md)",
        cursor: "pointer",
        textAlign: "left",
        fontFamily: "var(--font-sans)",
        fontSize: 12,
        fontWeight: 700,
        textTransform: "uppercase",
        letterSpacing: "0.04em",
        background: isActive ? "rgba(52,211,153,0.14)" : "transparent",
        color: isActive ? "#86efac" : "rgba(255,255,255,0.66)",
        borderLeft: isActive ? "2px solid var(--green-400)" : "2px solid transparent",
        transition: "background var(--dur-fast) var(--ease-out), color var(--dur-fast) var(--ease-out)"
      },
      onMouseEnter: e => {
        if (!isActive) e.currentTarget.style.background = "rgba(255,255,255,0.05)";
      },
      onMouseLeave: e => {
        if (!isActive) e.currentTarget.style.background = "transparent";
      }
    }, /*#__PURE__*/React.createElement(Icon, {
      n: item.icon,
      size: 17,
      color: isActive ? "#34d399" : "rgba(255,255,255,0.5)"
    }), /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 1
      }
    }, item.label), hasChildren && /*#__PURE__*/React.createElement(Icon, {
      n: "chevron-down",
      size: 13,
      color: "rgba(255,255,255,0.4)"
    })), hasChildren && isOpen && /*#__PURE__*/React.createElement("div", {
      style: {
        padding: "4px 0 4px 30px",
        display: "flex",
        flexDirection: "column",
        gap: 1
      }
    }, item.children.map((c, i) => /*#__PURE__*/React.createElement("a", {
      key: c,
      href: "#",
      onClick: e => {
        e.preventDefault();
        onNavigate?.(item.id);
      },
      style: {
        display: "flex",
        alignItems: "center",
        gap: 9,
        padding: "6px 10px",
        borderRadius: "var(--radius-sm)",
        textDecoration: "none",
        fontSize: 11,
        fontWeight: 700,
        textTransform: "uppercase",
        letterSpacing: "0.04em",
        color: item.id === active && i === 0 ? "#34d399" : "rgba(255,255,255,0.5)"
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 5,
        height: 5,
        borderRadius: "50%",
        background: item.id === active && i === 0 ? "var(--green-400)" : "rgba(255,255,255,0.25)"
      }
    }), c))));
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 16,
      borderTop: "1px solid var(--forest-line)",
      textAlign: "center"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 9.5,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.1em",
      color: "rgba(255,255,255,0.4)"
    }
  }, "Active Session"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12.5,
      fontWeight: 700,
      color: "rgba(255,255,255,0.9)",
      marginTop: 4
    }
  }, "Maria Santos"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 9.5,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.12em",
      color: "#fb923c",
      marginTop: 2
    }
  }, "RND \xB7 Dietitian"))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      display: "flex",
      flexDirection: "column",
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("header", {
    style: {
      height: 60,
      background: "var(--surface-card)",
      borderBottom: "1px solid var(--border-subtle)",
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      padding: "0 26px",
      position: "sticky",
      top: 0,
      zIndex: 5
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      fontSize: 13.5,
      fontWeight: 700,
      textTransform: "uppercase",
      letterSpacing: "0.05em",
      color: "var(--text-strong)",
      margin: 0
    }
  }, TITLES[active] || "Nutrition Operations Console"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 18
    }
  }, /*#__PURE__*/React.createElement("button", {
    style: {
      position: "relative",
      background: "transparent",
      border: "none",
      cursor: "pointer",
      color: "var(--text-muted)",
      display: "inline-flex"
    },
    "aria-label": "Notifications"
  }, /*#__PURE__*/React.createElement(Icon, {
    n: "bell",
    size: 19
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      top: -4,
      right: -4,
      minWidth: 16,
      height: 16,
      padding: "0 4px",
      borderRadius: 999,
      background: "var(--brand-accent)",
      color: "#fff",
      fontSize: 9,
      fontWeight: 800,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      border: "2px solid var(--surface-card)"
    }
  }, "3")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10,
      paddingLeft: 18,
      borderLeft: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: "right"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12.5,
      fontWeight: 700,
      color: "var(--text-strong)",
      lineHeight: 1.2
    }
  }, "Maria Santos"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 9,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.12em",
      color: "var(--brand-accent)"
    }
  }, "RND")), /*#__PURE__*/React.createElement(Avatar, {
    name: "Maria Santos",
    size: 34
  })))), /*#__PURE__*/React.createElement("main", {
    style: {
      flex: 1,
      padding: 26,
      overflowY: "auto"
    }
  }, children)));
}
window.AppShell = AppShell;
window.NSIcon = Icon;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/web/AppShell.jsx", error: String((e && e.message) || e) }); }

// ui_kits/web/DashboardScreen.jsx
try { (() => {
// NutriScope — RND Dashboard screen.
const {
  Card,
  KpiCard,
  Badge,
  StatusBadge,
  Button
} = window.NutriScopeDesignSystem_c4cce8;
const Icon = window.NSIcon;
const FOLLOWUPS = [{
  name: "Ramon Dela Cruz",
  id: "NS-00231",
  goal: "Weight gain",
  due: "Today",
  tone: "warning"
}, {
  name: "Liwayway Reyes",
  id: "NS-00198",
  goal: "Glycemic control",
  due: "In 2 days",
  tone: "neutral"
}, {
  name: " Benigno Aquino",
  id: "NS-00204",
  goal: "Renal diet",
  due: "1 day overdue",
  tone: "error"
}, {
  name: "Corazon Lim",
  id: "NS-00250",
  goal: "Post-op recovery",
  due: "In 4 days",
  tone: "neutral"
}];
const ANNOUNCEMENTS = [{
  cat: "Policy",
  title: "Updated low-sodium menu rolls out Monday",
  time: "2h ago",
  pinned: true
}, {
  cat: "Kitchen",
  title: "Supplier change for fresh produce — see procurement",
  time: "5h ago"
}, {
  cat: "Clinical",
  title: "New NCP monitoring template now available",
  time: "1d ago"
}];
function DashboardScreen() {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: 22,
      maxWidth: 1180,
      margin: "0 auto"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      borderRadius: "var(--radius-2xl)",
      overflow: "hidden",
      padding: "30px 34px",
      color: "#fff",
      minHeight: 150,
      display: "flex",
      flexDirection: "column",
      justifyContent: "center"
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "https://images.pexels.com/photos/1660027/pexels-photo-1660027.jpeg?auto=compress&cs=tinysrgb&w=1400",
    alt: "",
    style: {
      position: "absolute",
      inset: 0,
      width: "100%",
      height: "100%",
      objectFit: "cover"
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "absolute",
      inset: 0,
      background: "linear-gradient(100deg, rgba(6,32,17,0.9) 0%, rgba(5,150,105,0.66) 100%)"
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.12em",
      color: "#86efac"
    }
  }, "Tuesday \xB7 June 26"), /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 27,
      fontWeight: 800,
      letterSpacing: "-0.02em",
      margin: "8px 0 0"
    }
  }, "Good morning, Maria"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 14.5,
      color: "rgba(255,255,255,0.85)",
      margin: "6px 0 0"
    }
  }, "4 follow-ups need attention and today's menu is within budget."), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 16,
      display: "flex",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    size: "sm",
    style: {
      background: "#fff",
      color: "var(--green-700)"
    }
  }, "Start a care cycle"), /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    size: "sm",
    style: {
      color: "#fff",
      border: "1px solid rgba(255,255,255,0.4)"
    }
  }, "View schedule")))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(4,1fr)",
      gap: 14
    }
  }, /*#__PURE__*/React.createElement(KpiCard, {
    label: "Active patients",
    value: "38",
    tone: "emerald",
    hint: "6 new this week",
    icon: /*#__PURE__*/React.createElement(Icon, {
      n: "heart-handshake",
      size: 16
    })
  }), /*#__PURE__*/React.createElement(KpiCard, {
    label: "Cost / head",
    value: "\u20B162.40",
    tone: "neutral",
    hint: "Limit \u20B165.00 \xB7 within budget",
    icon: /*#__PURE__*/React.createElement(Icon, {
      n: "wallet",
      size: 16
    })
  }), /*#__PURE__*/React.createElement(KpiCard, {
    label: "Meals today",
    value: "312",
    tone: "sky",
    hint: "Breakfast \xB7 Lunch \xB7 Dinner",
    icon: /*#__PURE__*/React.createElement(Icon, {
      n: "utensils",
      size: 16
    })
  }), /*#__PURE__*/React.createElement(KpiCard, {
    label: "Out of stock",
    value: "5",
    tone: "red",
    hint: "Needs restock",
    icon: /*#__PURE__*/React.createElement(Icon, {
      n: "package",
      size: 16
    })
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "1.4fr 1fr",
      gap: 20
    }
  }, /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      padding: "16px 20px",
      borderBottom: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 9
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    n: "calendar-clock",
    size: 17,
    color: "var(--green-700)"
  }), /*#__PURE__*/React.createElement("h3", {
    style: {
      fontSize: 15,
      fontWeight: 700,
      color: "var(--text-strong)",
      margin: 0
    }
  }, "Upcoming follow-ups")), /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => e.preventDefault(),
    style: {
      fontSize: 12.5,
      fontWeight: 600,
      color: "var(--brand-primary)",
      textDecoration: "none"
    }
  }, "View all")), /*#__PURE__*/React.createElement("div", null, FOLLOWUPS.map((f, i) => /*#__PURE__*/React.createElement("div", {
    key: f.id,
    style: {
      display: "flex",
      alignItems: "center",
      gap: 12,
      padding: "13px 20px",
      borderBottom: i < FOLLOWUPS.length - 1 ? "1px solid var(--border-subtle)" : "none"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 36,
      height: 36,
      borderRadius: "50%",
      background: "var(--brand-primary-soft)",
      color: "var(--green-700)",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      fontWeight: 700,
      fontSize: 13,
      flexShrink: 0
    }
  }, f.name.split(" ").map(p => p[0]).slice(0, 2).join("").toUpperCase()), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13.5,
      fontWeight: 600,
      color: "var(--text-strong)"
    }
  }, f.name), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11.5,
      color: "var(--text-muted)",
      fontFamily: "var(--font-mono)"
    }
  }, f.id, " \xB7 ", f.goal)), /*#__PURE__*/React.createElement(StatusBadge, {
    label: f.due,
    status: f.tone
  }))))), /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 9,
      padding: "16px 20px",
      borderBottom: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    n: "megaphone",
    size: 17,
    color: "var(--orange-600)"
  }), /*#__PURE__*/React.createElement("h3", {
    style: {
      fontSize: 15,
      fontWeight: 700,
      color: "var(--text-strong)",
      margin: 0
    }
  }, "Announcements")), /*#__PURE__*/React.createElement("div", null, ANNOUNCEMENTS.map((a, i) => /*#__PURE__*/React.createElement("div", {
    key: a.title,
    style: {
      padding: "13px 20px",
      borderBottom: i < ANNOUNCEMENTS.length - 1 ? "1px solid var(--border-subtle)" : "none",
      background: a.pinned ? "var(--orange-50)" : "transparent"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 7,
      marginBottom: 5
    }
  }, a.pinned && /*#__PURE__*/React.createElement(Icon, {
    n: "pin",
    size: 11,
    color: "var(--orange-600)"
  }), /*#__PURE__*/React.createElement(Badge, {
    tone: a.cat === "Clinical" ? "emerald" : a.cat === "Kitchen" ? "lime" : "amber"
  }, a.cat), /*#__PURE__*/React.createElement("span", {
    style: {
      marginLeft: "auto",
      fontSize: 11,
      color: "var(--text-faint)",
      fontFamily: "var(--font-mono)"
    }
  }, a.time)), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13.5,
      fontWeight: 600,
      color: "var(--text-strong)",
      lineHeight: 1.4
    }
  }, a.title)))))));
}
window.DashboardScreen = DashboardScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/web/DashboardScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/web/FoodServiceScreen.jsx
try { (() => {
// NutriScope — Food Service / Inventory screen.
const {
  Card,
  KpiCard,
  Badge,
  StatusBadge,
  Button,
  Input,
  Tabs
} = window.NutriScopeDesignSystem_c4cce8;
const Icon = window.NSIcon;
const ITEMS = [{
  name: "Chicken breast, skinless",
  type: "Ingredient",
  qty: "42.0",
  unit: "kg",
  ok: true
}, {
  name: "Brown rice",
  type: "Ingredient",
  qty: "0",
  unit: "kg",
  ok: false
}, {
  name: "Malunggay leaves",
  type: "Ingredient",
  qty: "8.5",
  unit: "kg",
  ok: true
}, {
  name: "Low-sodium broth",
  type: "Supply",
  qty: "0",
  unit: "L",
  ok: false
}, {
  name: "Banana, lakatan",
  type: "Ingredient",
  qty: "120",
  unit: "pcs",
  ok: true
}, {
  name: "Tilapia fillet",
  type: "Ingredient",
  qty: "16.2",
  unit: "kg",
  ok: true
}, {
  name: "Iodized salt",
  type: "Supply",
  qty: "3.0",
  unit: "kg",
  ok: true
}, {
  name: "Diabetic meal pack",
  type: "Recipe",
  qty: "0",
  unit: "serv",
  ok: false
}];
function FoodServiceScreen() {
  const [tab, setTab] = React.useState("inventory");
  const [filter, setFilter] = React.useState("all");
  const filters = [["all", "All"], ["ingredient", "Ingredients"], ["supply", "Supplies"], ["recipe", "Recipes"]];
  const rows = ITEMS.filter(i => filter === "all" || i.type.toLowerCase() === filter);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: 18,
      maxWidth: 1180,
      margin: "0 auto"
    }
  }, /*#__PURE__*/React.createElement(Tabs, {
    value: tab,
    onChange: setTab,
    tabs: [{
      id: 'inventory',
      label: 'Inventory'
    }, {
      id: 'menu',
      label: 'Menu Cycle'
    }, {
      id: 'budget',
      label: 'Budget'
    }, {
      id: 'procurement',
      label: 'Procurement'
    }]
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(3,1fr)",
      gap: 14
    }
  }, /*#__PURE__*/React.createElement(KpiCard, {
    label: "Total items",
    value: "248",
    tone: "neutral"
  }), /*#__PURE__*/React.createElement(KpiCard, {
    label: "In stock",
    value: "243",
    tone: "emerald"
  }), /*#__PURE__*/React.createElement(KpiCard, {
    label: "No stock",
    value: "5",
    tone: "red",
    hint: "Restock needed before lunch service"
  })), /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 12,
      padding: "14px 18px",
      borderBottom: "1px solid var(--border-subtle)",
      flexWrap: "wrap"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 220,
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      left: 12,
      top: "50%",
      transform: "translateY(-50%)",
      color: "var(--text-faint)",
      display: "inline-flex"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    n: "search",
    size: 16
  })), /*#__PURE__*/React.createElement("input", {
    placeholder: "Search inventory\u2026",
    style: {
      width: "100%",
      boxSizing: "border-box",
      padding: "9px 13px 9px 36px",
      fontSize: 13.5,
      fontFamily: "var(--font-sans)",
      color: "var(--text-strong)",
      background: "var(--surface-sunken)",
      border: "1px solid var(--border-subtle)",
      borderRadius: "var(--radius-md)",
      outline: "none"
    }
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 6
    }
  }, filters.map(([v, l]) => /*#__PURE__*/React.createElement("button", {
    key: v,
    onClick: () => setFilter(v),
    style: {
      padding: "7px 13px",
      borderRadius: "var(--radius-full)",
      fontSize: 12,
      fontWeight: 700,
      cursor: "pointer",
      border: filter === v ? "1px solid var(--brand-primary)" : "1px solid var(--border-subtle)",
      background: filter === v ? "var(--brand-primary)" : "var(--surface-card)",
      color: filter === v ? "#fff" : "var(--text-muted)"
    }
  }, l))), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    size: "sm",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      n: "plus",
      size: 15
    })
  }, "Add Item")), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "2.4fr 1fr 1fr 1fr",
      padding: "10px 20px",
      borderBottom: "1px solid var(--border-subtle)",
      fontSize: 10.5,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.06em",
      color: "var(--text-faint)"
    }
  }, /*#__PURE__*/React.createElement("span", null, "Item"), /*#__PURE__*/React.createElement("span", null, "Type"), /*#__PURE__*/React.createElement("span", {
    style: {
      textAlign: "right"
    }
  }, "In stock"), /*#__PURE__*/React.createElement("span", {
    style: {
      textAlign: "right"
    }
  }, "Status")), rows.map((it, i) => /*#__PURE__*/React.createElement("div", {
    key: it.name,
    style: {
      display: "grid",
      gridTemplateColumns: "2.4fr 1fr 1fr 1fr",
      alignItems: "center",
      padding: "12px 20px",
      borderBottom: i < rows.length - 1 ? "1px solid var(--border-subtle)" : "none"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 13.5,
      fontWeight: 600,
      color: "var(--text-strong)"
    }
  }, it.name), /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement(Badge, {
    tone: "neutral"
  }, it.type)), /*#__PURE__*/React.createElement("span", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)",
      fontSize: 13,
      fontWeight: 600,
      color: it.ok ? "var(--text-strong)" : "var(--status-danger)"
    }
  }, it.qty, " ", /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--text-faint)",
      fontWeight: 400
    }
  }, it.unit)), /*#__PURE__*/React.createElement("span", {
    style: {
      textAlign: "right"
    }
  }, /*#__PURE__*/React.createElement(StatusBadge, {
    label: it.ok ? "In stock" : "No stock",
    status: it.ok ? "success" : "error"
  })))))));
}
window.FoodServiceScreen = FoodServiceScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/web/FoodServiceScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/web/LoginScreen.jsx
try { (() => {
// NutriScope — Split-screen login landing.
// Left: forest brand panel with fresh-produce imagery + value props.
// Right: the sign-in card (composes Input, Button, Logo from the DS bundle).
const {
  Input,
  Button,
  Logo
} = window.NutriScopeDesignSystem_c4cce8;
function LoginScreen({
  onSignIn
}) {
  const [email, setEmail] = React.useState("m.santos@hospital.ph");
  const [password, setPassword] = React.useState("••••••••");
  const [loading, setLoading] = React.useState(false);
  const submit = e => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => {
      setLoading(false);
      onSignIn?.();
    }, 850);
  };
  const points = [["heart-pulse", "Run the full Nutrition Care Process — assess, diagnose, intervene, monitor."], ["salad", "Plan menus and track cost-per-head down to the last ₱."], ["shield-check", "Every action logged. Built for hospital-grade accountability."]];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      minHeight: "100%",
      background: "var(--surface-card)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      flex: "1.05",
      display: "flex",
      flexDirection: "column",
      justifyContent: "space-between",
      padding: "44px 48px",
      overflow: "hidden",
      color: "#fff"
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?auto=compress&cs=tinysrgb&w=1400",
    alt: "",
    style: {
      position: "absolute",
      inset: 0,
      width: "100%",
      height: "100%",
      objectFit: "cover"
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "absolute",
      inset: 0,
      background: "linear-gradient(155deg, rgba(6,32,17,0.86) 0%, rgba(10,38,26,0.82) 45%, rgba(5,150,105,0.62) 100%)"
    }
  }), /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 200 200",
    style: {
      position: "absolute",
      right: -40,
      bottom: -30,
      width: 320,
      height: 320,
      opacity: 0.12
    }
  }, /*#__PURE__*/React.createElement("path", {
    d: "M100 40C100 40 60 75 60 120C60 142 78 160 100 160C122 160 140 142 140 120C140 75 100 40 100 40Z",
    fill: "#a3e635"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      display: "flex",
      alignItems: "center",
      gap: 11
    }
  }, /*#__PURE__*/React.createElement(Logo, {
    variant: "forest",
    size: 32
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.14em",
      color: "#86efac",
      marginBottom: 18
    }
  }, "Clinical & Operational Care"), /*#__PURE__*/React.createElement("h1", {
    style: {
      fontSize: 46,
      lineHeight: 1.04,
      fontWeight: 800,
      letterSpacing: "-0.03em",
      margin: 0,
      maxWidth: 520
    }
  }, "Eat well,", /*#__PURE__*/React.createElement("br", null), "heal well."), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 16,
      lineHeight: 1.6,
      color: "rgba(255,255,255,0.82)",
      maxWidth: 440,
      marginTop: 18
    }
  }, "One console for the dietitians and kitchen teams who nourish every patient, every day."), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 28,
      display: "flex",
      flexDirection: "column",
      gap: 14
    }
  }, points.map(([icon, text]) => /*#__PURE__*/React.createElement("div", {
    key: text,
    style: {
      display: "flex",
      gap: 12,
      alignItems: "flex-start",
      maxWidth: 460
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      flexShrink: 0,
      width: 30,
      height: 30,
      borderRadius: 9,
      background: "rgba(163,230,53,0.18)",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      color: "#bef264"
    }
  }, /*#__PURE__*/React.createElement("i", {
    "data-lucide": icon,
    style: {
      width: 16,
      height: 16
    }
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 14.5,
      lineHeight: 1.45,
      color: "rgba(255,255,255,0.9)",
      paddingTop: 5
    }
  }, text))))), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      fontSize: 12,
      color: "rgba(255,255,255,0.55)",
      display: "flex",
      gap: 8,
      alignItems: "center"
    }
  }, /*#__PURE__*/React.createElement("i", {
    "data-lucide": "lock",
    style: {
      width: 13,
      height: 13
    }
  }), "Secure connection \xB7 Activity logs active")), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: "0.95",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      padding: "40px 32px"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: "100%",
      maxWidth: 380
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 26
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 28,
      fontWeight: 800,
      letterSpacing: "-0.02em",
      color: "var(--text-strong)",
      margin: 0
    }
  }, "Welcome back"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 14.5,
      color: "var(--text-muted)",
      marginTop: 7
    }
  }, "Sign in to access your workspace.")), /*#__PURE__*/React.createElement("form", {
    onSubmit: submit,
    style: {
      display: "flex",
      flexDirection: "column",
      gap: 16
    }
  }, /*#__PURE__*/React.createElement(Input, {
    label: "Email Address",
    type: "email",
    value: email,
    onChange: e => setEmail(e.target.value),
    autoComplete: "email"
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(Input, {
    label: "Password",
    type: "password",
    value: password,
    onChange: e => setPassword(e.target.value),
    autoComplete: "current-password"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: "right",
      marginTop: 8
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => e.preventDefault(),
    style: {
      fontSize: 12.5,
      fontWeight: 600,
      color: "var(--brand-primary)",
      textDecoration: "none"
    }
  }, "Forgot password?"))), /*#__PURE__*/React.createElement(Button, {
    type: "submit",
    fullWidth: true,
    size: "lg",
    loading: loading,
    style: {
      boxShadow: "var(--shadow-brand)"
    }
  }, "Sign In")), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 22,
      paddingTop: 18,
      borderTop: "1px solid var(--border-subtle)",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 10.5,
      fontWeight: 800,
      textTransform: "uppercase",
      letterSpacing: "0.12em",
      color: "var(--text-faint)"
    }
  }, "RND \xB7 Admin \xB7 Food Service")))));
}
window.LoginScreen = LoginScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/web/LoginScreen.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Avatar = __ds_scope.Avatar;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.IconButton = __ds_scope.IconButton;

__ds_ns.Logo = __ds_scope.Logo;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.KpiCard = __ds_scope.KpiCard;

__ds_ns.StatusBadge = __ds_scope.StatusBadge;

__ds_ns.Tabs = __ds_scope.Tabs;

__ds_ns.Input = __ds_scope.Input;

})();
