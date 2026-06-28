// NutriScope — App shell: forest-green sidebar + top bar.
// The brand uplift: nav is deep forest green (not near-black), with a green/lime
// active accent. Composes Logo + Avatar from the DS bundle.
const { Logo, Avatar } = window.NutriScopeDesignSystem_c4cce8;

const NAV = [
  { id: "dashboard", label: "Dashboard", icon: "compass" },
  { id: "announcements", label: "Announcements", icon: "megaphone" },
  { id: "food-library", label: "Food Library", icon: "cooking-pot" },
  { id: "nutrition", label: "Nutrition Care", icon: "heart-handshake", children: ["Patients", "Assessment", "Diagnosis", "Intervention", "Monitoring"] },
  { id: "food-service", label: "Food Service", icon: "salad", children: ["Inventory", "Menu Cycle", "Budget", "Procurement"] },
  { id: "reports", label: "Reports", icon: "trending-up" },
  { id: "settings", label: "Settings", icon: "sliders" },
];

const TITLES = {
  dashboard: "Overview & Operations Center",
  "food-service": "Food Service & Kitchen Operations",
};

function Icon({ n, size = 18, color }) {
  const r = React.useRef(null);
  React.useEffect(() => {
    if (r.current) { r.current.innerHTML = `<i data-lucide="${n}"></i>`; window.lucide && lucide.createIcons({ attrs: { width: size, height: size } }); }
  }, [n, size]);
  return <span ref={r} style={{ display: "inline-flex", color }} />;
}

function AppShell({ active, onNavigate, children }) {
  const [openGroup, setOpenGroup] = React.useState("food-service");

  return (
    <div style={{ display: "flex", minHeight: "100%", background: "var(--surface-page)" }}>
      {/* SIDEBAR */}
      <aside style={{ width: 248, flexShrink: 0, background: "linear-gradient(180deg, var(--forest-900) 0%, var(--forest-950) 100%)", borderRight: "1px solid var(--forest-line)", display: "flex", flexDirection: "column", position: "sticky", top: 0, height: "100vh" }}>
        <div style={{ height: 60, display: "flex", alignItems: "center", padding: "0 20px", borderBottom: "1px solid var(--forest-line)" }}>
          <Logo variant="forest" size={28} />
        </div>

        <nav style={{ flex: 1, padding: "14px 12px", display: "flex", flexDirection: "column", gap: 3, overflowY: "auto" }}>
          {NAV.map((item) => {
            const isActive = active === item.id;
            const hasChildren = !!item.children;
            const isOpen = openGroup === item.id;
            return (
              <div key={item.id}>
                <button
                  onClick={() => hasChildren ? setOpenGroup(isOpen ? null : item.id) : onNavigate?.(item.id)}
                  style={{
                    width: "100%", display: "flex", alignItems: "center", gap: 11, padding: "9px 11px",
                    border: "none", borderRadius: "var(--radius-md)", cursor: "pointer", textAlign: "left",
                    fontFamily: "var(--font-sans)", fontSize: 12, fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.04em",
                    background: isActive ? "rgba(52,211,153,0.14)" : "transparent",
                    color: isActive ? "#86efac" : "rgba(255,255,255,0.66)",
                    borderLeft: isActive ? "2px solid var(--green-400)" : "2px solid transparent",
                    transition: "background var(--dur-fast) var(--ease-out), color var(--dur-fast) var(--ease-out)",
                  }}
                  onMouseEnter={(e) => { if (!isActive) e.currentTarget.style.background = "rgba(255,255,255,0.05)"; }}
                  onMouseLeave={(e) => { if (!isActive) e.currentTarget.style.background = "transparent"; }}
                >
                  <Icon n={item.icon} size={17} color={isActive ? "#34d399" : "rgba(255,255,255,0.5)"} />
                  <span style={{ flex: 1 }}>{item.label}</span>
                  {hasChildren && <Icon n="chevron-down" size={13} color="rgba(255,255,255,0.4)" />}
                </button>
                {hasChildren && isOpen && (
                  <div style={{ padding: "4px 0 4px 30px", display: "flex", flexDirection: "column", gap: 1 }}>
                    {item.children.map((c, i) => (
                      <a key={c} href="#" onClick={(e) => { e.preventDefault(); onNavigate?.(item.id); }}
                        style={{ display: "flex", alignItems: "center", gap: 9, padding: "6px 10px", borderRadius: "var(--radius-sm)", textDecoration: "none",
                          fontSize: 11, fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.04em",
                          color: (item.id === active && i === 0) ? "#34d399" : "rgba(255,255,255,0.5)" }}>
                        <span style={{ width: 5, height: 5, borderRadius: "50%", background: (item.id === active && i === 0) ? "var(--green-400)" : "rgba(255,255,255,0.25)" }} />
                        {c}
                      </a>
                    ))}
                  </div>
                )}
              </div>
            );
          })}
        </nav>

        <div style={{ padding: 16, borderTop: "1px solid var(--forest-line)", textAlign: "center" }}>
          <div style={{ fontSize: 9.5, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em", color: "rgba(255,255,255,0.4)" }}>Active Session</div>
          <div style={{ fontSize: 12.5, fontWeight: 700, color: "rgba(255,255,255,0.9)", marginTop: 4 }}>Maria Santos</div>
          <div style={{ fontSize: 9.5, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.12em", color: "#fb923c", marginTop: 2 }}>RND · Dietitian</div>
        </div>
      </aside>

      {/* MAIN */}
      <div style={{ flex: 1, display: "flex", flexDirection: "column", minWidth: 0 }}>
        <header style={{ height: 60, background: "var(--surface-card)", borderBottom: "1px solid var(--border-subtle)", display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 26px", position: "sticky", top: 0, zIndex: 5 }}>
          <h1 style={{ fontSize: 13.5, fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.05em", color: "var(--text-strong)", margin: 0 }}>
            {TITLES[active] || "Nutrition Operations Console"}
          </h1>
          <div style={{ display: "flex", alignItems: "center", gap: 18 }}>
            <button style={{ position: "relative", background: "transparent", border: "none", cursor: "pointer", color: "var(--text-muted)", display: "inline-flex" }} aria-label="Notifications">
              <Icon n="bell" size={19} />
              <span style={{ position: "absolute", top: -4, right: -4, minWidth: 16, height: 16, padding: "0 4px", borderRadius: 999, background: "var(--brand-accent)", color: "#fff", fontSize: 9, fontWeight: 800, display: "flex", alignItems: "center", justifyContent: "center", border: "2px solid var(--surface-card)" }}>3</span>
            </button>
            <div style={{ display: "flex", alignItems: "center", gap: 10, paddingLeft: 18, borderLeft: "1px solid var(--border-subtle)" }}>
              <div style={{ textAlign: "right" }}>
                <div style={{ fontSize: 12.5, fontWeight: 700, color: "var(--text-strong)", lineHeight: 1.2 }}>Maria Santos</div>
                <div style={{ fontSize: 9, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.12em", color: "var(--brand-accent)" }}>RND</div>
              </div>
              <Avatar name="Maria Santos" size={34} />
            </div>
          </div>
        </header>
        <main style={{ flex: 1, padding: 26, overflowY: "auto" }}>{children}</main>
      </div>
    </div>
  );
}
window.AppShell = AppShell;
window.NSIcon = Icon;
