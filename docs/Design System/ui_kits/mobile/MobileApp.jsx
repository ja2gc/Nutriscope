// NutriScope FSS Mobile — improved, on-brand recreation.
// Fixes vs shipped app: warm green-tinted surfaces (not cold gray), brand
// green/orange accents replacing the off-brand purple announcements, softer
// cards, lively KPI chips, green active tabs.

function MIcon({ n, size = 22, color = "currentColor", style }) {
  const r = React.useRef(null);
  React.useEffect(() => {
    if (r.current) { r.current.innerHTML = `<i data-lucide="${n}"></i>`; window.lucide && lucide.createIcons({ attrs: { width: size, height: size, stroke: color } }); }
  }, [n, size, color]);
  return <span ref={r} style={{ display: "inline-flex", color, ...style }} />;
}

/* ---------------- Header ---------------- */
function Header({ title }) {
  return (
    <div style={{ background: "var(--surface-card)", borderBottom: "1px solid var(--border-subtle)", padding: "12px 16px", display: "flex", alignItems: "center", justifyContent: "space-between" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 9 }}>
        <img src="../../assets/mark.svg" width="24" height="24" alt="" />
        <span style={{ fontSize: 16, fontWeight: 700, color: "var(--text-strong)" }}>{title}</span>
      </div>
      <div style={{ display: "flex", gap: 4 }}>
        <button style={iconBtn} aria-label="Announcements"><MIcon n="megaphone" size={20} color="var(--neutral-600)" /></button>
        <button style={{ ...iconBtn, position: "relative" }} aria-label="Notifications">
          <MIcon n="bell" size={20} color="var(--neutral-600)" />
          <span style={{ position: "absolute", top: 4, right: 4, minWidth: 15, height: 15, borderRadius: 999, background: "var(--brand-accent)", color: "#fff", fontSize: 9, fontWeight: 800, display: "flex", alignItems: "center", justifyContent: "center", padding: "0 3px" }}>2</span>
        </button>
        <button style={iconBtn} aria-label="Account"><MIcon n="circle-user-round" size={20} color="var(--neutral-600)" /></button>
      </div>
    </div>
  );
}
const iconBtn = { width: 38, height: 38, display: "flex", alignItems: "center", justifyContent: "center", background: "transparent", border: "none", cursor: "pointer", borderRadius: 10 };

/* ---------------- Dashboard ---------------- */
function Dashboard() {
  const kpis = [
    { icon: "clipboard-list", label: "Meals to log today", value: 18, fg: "var(--green-700)", bg: "var(--green-50)", bd: "var(--green-200)" },
    { icon: "shopping-bag", label: "POs awaiting receipt", value: 3, fg: "var(--orange-700)", bg: "var(--orange-50)", bd: "var(--orange-200)" },
    { icon: "package", label: "Items out of stock", value: 5, fg: "var(--status-danger)", bg: "var(--status-danger-bg)", bd: "#fecaca" },
  ];
  const service = [
    { meal: "Breakfast", name: "Arroz caldo · soft diet", prepped: true },
    { meal: "Lunch", name: "Tilapia, brown rice, malunggay", prepped: true, shortfall: true },
    { meal: "Dinner", name: "Chicken tinola, low-sodium", prepped: false },
  ];
  const anns = [
    { cat: "Kitchen", title: "Supplier change for fresh produce", body: "New vendor starts Monday. Check procurement for updated prices.", time: "2h ago", pinned: true },
    { cat: "Policy", title: "Low-sodium menu rollout", body: "Applies to all cardiac-ward trays from next cycle.", time: "1d ago" },
  ];
  return (
    <div style={{ padding: "16px 14px 90px", display: "flex", flexDirection: "column", gap: 20 }}>
      <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
        {kpis.map(k => (
          <div key={k.label} style={{ display: "flex", alignItems: "center", gap: 14, background: "var(--surface-card)", border: `1px solid ${k.bd}`, borderRadius: 16, padding: "15px 16px", boxShadow: "var(--shadow-xs)" }}>
            <div style={{ width: 44, height: 44, borderRadius: 12, background: k.bg, display: "flex", alignItems: "center", justifyContent: "center" }}><MIcon n={k.icon} size={22} color={k.fg} /></div>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 12.5, color: "var(--text-muted)" }}>{k.label}</div>
              <div style={{ fontSize: 26, fontWeight: 800, color: k.fg, fontFamily: "var(--font-mono)" }}>{k.value}</div>
            </div>
            <MIcon n="chevron-right" size={18} color="var(--text-faint)" />
          </div>
        ))}
      </div>

      <Section title="Today's service" icon="utensils">
        <div style={cardWrap}>
          {service.map((s, i) => (
            <div key={s.meal} style={{ display: "flex", alignItems: "center", padding: "13px 15px", borderBottom: i < service.length - 1 ? "1px solid var(--border-subtle)" : "none" }}>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 10.5, color: "var(--text-faint)", textTransform: "uppercase", letterSpacing: "0.06em", fontWeight: 700 }}>{s.meal}</div>
                <div style={{ fontSize: 13.5, fontWeight: 600, color: "var(--text-strong)", marginTop: 2 }}>{s.name}</div>
              </div>
              <div style={{ display: "flex", gap: 6 }}>
                {s.prepped && <Pill label="Prepped" tone="green" />}
                {s.shortfall && <Pill label="Shortfall" tone="red" />}
                {!s.prepped && <Pill label="To prep" tone="amber" />}
              </div>
            </div>
          ))}
        </div>
      </Section>

      <Section title="Announcements" icon="megaphone" iconColor="var(--orange-600)">
        <div style={cardWrap}>
          {anns.map((a, i) => (
            <div key={a.title} style={{ padding: "13px 15px", borderBottom: i < anns.length - 1 ? "1px solid var(--border-subtle)" : "none", background: a.pinned ? "var(--orange-50)" : "transparent" }}>
              <div style={{ display: "flex", alignItems: "center", gap: 7, marginBottom: 5 }}>
                {a.pinned && <MIcon n="pin" size={12} color="var(--orange-600)" />}
                <span style={{ fontSize: 10, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.05em", color: a.cat === "Kitchen" ? "var(--lime-600)" : "var(--green-700)", background: a.cat === "Kitchen" ? "#f7fee7" : "var(--green-50)", padding: "2px 7px", borderRadius: 999 }}>{a.cat}</span>
                <span style={{ marginLeft: "auto", fontSize: 11, color: "var(--text-faint)", fontFamily: "var(--font-mono)" }}>{a.time}</span>
              </div>
              <div style={{ fontSize: 13.5, fontWeight: 700, color: "var(--text-strong)" }}>{a.title}</div>
              <div style={{ fontSize: 12.5, color: "var(--text-muted)", marginTop: 3, lineHeight: 1.45 }}>{a.body}</div>
            </div>
          ))}
        </div>
      </Section>
    </div>
  );
}

/* ---------------- Inventory ---------------- */
function Inventory() {
  const [filter, setFilter] = React.useState("all");
  const items = [
    { name: "Chicken breast, skinless", type: "ingredient", qty: "42.0", unit: "kg", ok: true },
    { name: "Brown rice", type: "ingredient", qty: "0", unit: "kg", ok: false },
    { name: "Malunggay leaves", type: "ingredient", qty: "8.5", unit: "kg", ok: true },
    { name: "Low-sodium broth", type: "supply", qty: "0", unit: "L", ok: false },
    { name: "Banana, lakatan", type: "ingredient", qty: "120", unit: "pcs", ok: true },
    { name: "Tilapia fillet", type: "ingredient", qty: "16.2", unit: "kg", ok: true },
  ];
  const filters = [["all","All"],["ingredient","Ingredients"],["supply","Supplies"],["recipe","Recipes"]];
  const rows = items.filter(i => filter === "all" || i.type === filter);
  return (
    <div style={{ paddingBottom: 90 }}>
      <div style={{ background: "var(--surface-card)", borderBottom: "1px solid var(--border-subtle)", padding: "12px 14px", display: "flex", flexDirection: "column", gap: 10 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 9, background: "var(--surface-sunken)", borderRadius: 12, padding: "10px 12px" }}>
          <MIcon n="search" size={16} color="var(--text-faint)" />
          <span style={{ fontSize: 13.5, color: "var(--text-faint)" }}>Search inventory…</span>
        </div>
        <div style={{ display: "flex", gap: 7 }}>
          {filters.map(([v, l]) => (
            <button key={v} onClick={() => setFilter(v)} style={{ padding: "6px 12px", borderRadius: 999, fontSize: 12, fontWeight: 700, cursor: "pointer", border: filter === v ? "1px solid var(--brand-primary)" : "1px solid var(--border-subtle)", background: filter === v ? "var(--brand-primary)" : "var(--surface-card)", color: filter === v ? "#fff" : "var(--text-muted)" }}>{l}</button>
          ))}
        </div>
      </div>
      <div style={{ display: "flex", gap: 16, padding: "10px 16px", background: "var(--surface-card)", borderBottom: "1px solid var(--border-subtle)" }}>
        <Stat label="Total" value="248" color="var(--text-strong)" />
        <Stat label="In stock" value="243" color="var(--green-600)" />
        <Stat label="No stock" value="5" color="var(--status-danger)" />
      </div>
      <div style={{ background: "var(--surface-card)" }}>
        {rows.map((it, i) => (
          <div key={it.name} style={{ display: "flex", alignItems: "center", padding: "13px 16px", borderBottom: "1px solid var(--border-subtle)" }}>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 14, fontWeight: 600, color: "var(--text-strong)" }}>{it.name}</div>
              <div style={{ fontSize: 11.5, color: "var(--text-faint)", textTransform: "capitalize", marginTop: 2 }}>{it.type}</div>
            </div>
            <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 5 }}>
              <span style={{ fontSize: 14, fontWeight: 700, color: "var(--text-strong)", fontFamily: "var(--font-mono)" }}>{it.qty}<span style={{ fontSize: 11, fontWeight: 400, color: "var(--text-muted)" }}> {it.unit}</span></span>
              <Pill label={it.ok ? "In stock" : "No stock"} tone={it.ok ? "green" : "red"} />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------------- Menu ---------------- */
function Menu() {
  const days = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];
  const [active, setActive] = React.useState("Tue");
  const meals = [
    { meal: "Breakfast", item: "Arroz caldo, boiled egg", kcal: 420, cost: "18.50" },
    { meal: "Lunch", item: "Tilapia, brown rice, malunggay", kcal: 640, cost: "27.00" },
    { meal: "Snack", item: "Banana, lakatan", kcal: 110, cost: "6.00" },
    { meal: "Dinner", item: "Chicken tinola, low-sodium", kcal: 520, cost: "22.90" },
  ];
  return (
    <div style={{ paddingBottom: 90 }}>
      <div style={{ background: "var(--surface-card)", borderBottom: "1px solid var(--border-subtle)", padding: "12px 10px", display: "flex", gap: 6, justifyContent: "space-between" }}>
        {days.map(d => (
          <button key={d} onClick={() => setActive(d)} style={{ flex: 1, padding: "8px 0", borderRadius: 10, fontSize: 12, fontWeight: 700, cursor: "pointer", border: "none", background: active === d ? "var(--brand-primary)" : "transparent", color: active === d ? "#fff" : "var(--text-muted)" }}>{d}</button>
        ))}
      </div>
      <div style={{ padding: "14px 14px 0", display: "flex", gap: 10 }}>
        <div style={{ flex: 1, background: "var(--green-50)", border: "1px solid var(--green-200)", borderRadius: 14, padding: "11px 13px" }}>
          <div style={{ fontSize: 10.5, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.06em", color: "var(--green-700)", opacity: 0.7 }}>Cost / head</div>
          <div style={{ fontSize: 22, fontWeight: 800, color: "var(--green-700)", fontFamily: "var(--font-mono)" }}>₱74.40</div>
        </div>
        <div style={{ flex: 1, background: "var(--surface-card)", border: "1px solid var(--border-subtle)", borderRadius: 14, padding: "11px 13px" }}>
          <div style={{ fontSize: 10.5, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.06em", color: "var(--text-muted)" }}>Total kcal</div>
          <div style={{ fontSize: 22, fontWeight: 800, color: "var(--text-strong)", fontFamily: "var(--font-mono)" }}>1,690</div>
        </div>
      </div>
      <div style={{ padding: 14 }}>
        <div style={cardWrap}>
          {meals.map((m, i) => (
            <div key={m.meal} style={{ display: "flex", alignItems: "center", padding: "13px 15px", borderBottom: i < meals.length - 1 ? "1px solid var(--border-subtle)" : "none" }}>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 10.5, color: "var(--text-faint)", textTransform: "uppercase", letterSpacing: "0.06em", fontWeight: 700 }}>{m.meal}</div>
                <div style={{ fontSize: 13.5, fontWeight: 600, color: "var(--text-strong)", marginTop: 2 }}>{m.item}</div>
              </div>
              <div style={{ textAlign: "right" }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: "var(--green-700)", fontFamily: "var(--font-mono)" }}>₱{m.cost}</div>
                <div style={{ fontSize: 11, color: "var(--text-faint)", fontFamily: "var(--font-mono)" }}>{m.kcal} kcal</div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

/* ---------------- shared bits ---------------- */
const cardWrap = { background: "var(--surface-card)", border: "1px solid var(--border-subtle)", borderRadius: 16, overflow: "hidden", boxShadow: "var(--shadow-xs)" };
function Section({ title, icon, iconColor = "var(--green-700)", children }) {
  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 10, padding: "0 2px" }}>
        <MIcon n={icon} size={17} color={iconColor} />
        <span style={{ fontSize: 15, fontWeight: 700, color: "var(--text-strong)" }}>{title}</span>
      </div>
      {children}
    </div>
  );
}
function Pill({ label, tone }) {
  const tones = { green: ["var(--green-50)","var(--green-700)"], red: ["var(--status-danger-bg)","var(--status-danger)"], amber: ["var(--orange-50)","var(--orange-700)"] };
  const [bg, fg] = tones[tone] || tones.green;
  return <span style={{ padding: "3px 9px", borderRadius: 999, background: bg, color: fg, fontSize: 11, fontWeight: 700 }}>{label}</span>;
}
function Stat({ label, value, color }) {
  return <div style={{ display: "flex", alignItems: "baseline", gap: 5 }}><span style={{ fontSize: 16, fontWeight: 800, color, fontFamily: "var(--font-mono)" }}>{value}</span><span style={{ fontSize: 12, color: "var(--text-faint)" }}>{label}</span></div>;
}

/* ---------------- Tab bar + app ---------------- */
const TABS = [
  { id: "dashboard", label: "Dashboard", icon: "layout-dashboard", title: "Dashboard" },
  { id: "menu", label: "Menu", icon: "calendar-days", title: "Menu Cycle" },
  { id: "prep", label: "Prep", icon: "bar-chart-3", title: "Prep" },
  { id: "inventory", label: "Inventory", icon: "package", title: "Inventory" },
];

function MobileApp() {
  const [tab, setTab] = React.useState("dashboard");
  React.useEffect(() => { window.lucide && lucide.createIcons(); });
  const current = TABS.find(t => t.id === tab);
  return (
    <div style={{ display: "flex", flexDirection: "column", height: "100%", background: "var(--surface-page)" }}>
      <Header title={current.title} />
      <div style={{ flex: 1, overflowY: "auto" }}>
        {tab === "dashboard" && <Dashboard />}
        {tab === "inventory" && <Inventory />}
        {tab === "menu" && <Menu />}
        {tab === "prep" && <Placeholder label="Prep tracking" />}
      </div>
      <div style={{ display: "flex", background: "var(--surface-card)", borderTop: "1px solid var(--border-subtle)", paddingBottom: 6 }}>
        {TABS.map(t => {
          const on = t.id === tab;
          return (
            <button key={t.id} onClick={() => setTab(t.id)} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 3, padding: "9px 0", background: "transparent", border: "none", cursor: "pointer" }}>
              <MIcon n={t.icon} size={22} color={on ? "var(--brand-primary)" : "var(--neutral-400)"} />
              <span style={{ fontSize: 10.5, fontWeight: on ? 700 : 500, color: on ? "var(--brand-primary)" : "var(--neutral-400)" }}>{t.label}</span>
            </button>
          );
        })}
      </div>
    </div>
  );
}
function Placeholder({ label }) {
  return <div style={{ padding: 40, textAlign: "center", color: "var(--text-faint)" }}><div style={{ marginTop: 60 }}><MIcon n="bar-chart-3" size={40} color="var(--neutral-300)" /></div><div style={{ marginTop: 12, fontSize: 14 }}>{label}</div></div>;
}
window.MobileApp = MobileApp;
