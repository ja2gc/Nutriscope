// NutriScope — RND Dashboard screen.
const { Card, KpiCard, Badge, StatusBadge, Button } = window.NutriScopeDesignSystem_c4cce8;
const Icon = window.NSIcon;

const FOLLOWUPS = [
  { name: "Ramon Dela Cruz", id: "NS-00231", goal: "Weight gain", due: "Today", tone: "warning" },
  { name: "Liwayway Reyes", id: "NS-00198", goal: "Glycemic control", due: "In 2 days", tone: "neutral" },
  { name: " Benigno Aquino", id: "NS-00204", goal: "Renal diet", due: "1 day overdue", tone: "error" },
  { name: "Corazon Lim", id: "NS-00250", goal: "Post-op recovery", due: "In 4 days", tone: "neutral" },
];

const ANNOUNCEMENTS = [
  { cat: "Policy", title: "Updated low-sodium menu rolls out Monday", time: "2h ago", pinned: true },
  { cat: "Kitchen", title: "Supplier change for fresh produce — see procurement", time: "5h ago" },
  { cat: "Clinical", title: "New NCP monitoring template now available", time: "1d ago" },
];

function DashboardScreen() {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 22, maxWidth: 1180, margin: "0 auto" }}>
      {/* Welcome hero */}
      <div style={{ position: "relative", borderRadius: "var(--radius-2xl)", overflow: "hidden", padding: "30px 34px", color: "#fff", minHeight: 150, display: "flex", flexDirection: "column", justifyContent: "center" }}>
        <img src="https://images.pexels.com/photos/1660027/pexels-photo-1660027.jpeg?auto=compress&cs=tinysrgb&w=1400" alt="" style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover" }} />
        <div style={{ position: "absolute", inset: 0, background: "linear-gradient(100deg, rgba(6,32,17,0.9) 0%, rgba(5,150,105,0.66) 100%)" }} />
        <div style={{ position: "relative" }}>
          <div style={{ fontSize: 11, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.12em", color: "#86efac" }}>Tuesday · June 26</div>
          <h2 style={{ fontSize: 27, fontWeight: 800, letterSpacing: "-0.02em", margin: "8px 0 0" }}>Good morning, Maria</h2>
          <p style={{ fontSize: 14.5, color: "rgba(255,255,255,0.85)", margin: "6px 0 0" }}>4 follow-ups need attention and today's menu is within budget.</p>
          <div style={{ marginTop: 16, display: "flex", gap: 10 }}>
            <Button variant="primary" size="sm" style={{ background: "#fff", color: "var(--green-700)" }}>Start a care cycle</Button>
            <Button variant="ghost" size="sm" style={{ color: "#fff", border: "1px solid rgba(255,255,255,0.4)" }}>View schedule</Button>
          </div>
        </div>
      </div>

      {/* KPIs */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(4,1fr)", gap: 14 }}>
        <KpiCard label="Active patients" value="38" tone="emerald" hint="6 new this week" icon={<Icon n="heart-handshake" size={16} />} />
        <KpiCard label="Cost / head" value="₱62.40" tone="neutral" hint="Limit ₱65.00 · within budget" icon={<Icon n="wallet" size={16} />} />
        <KpiCard label="Meals today" value="312" tone="sky" hint="Breakfast · Lunch · Dinner" icon={<Icon n="utensils" size={16} />} />
        <KpiCard label="Out of stock" value="5" tone="red" hint="Needs restock" icon={<Icon n="package" size={16} />} />
      </div>

      {/* Two columns */}
      <div style={{ display: "grid", gridTemplateColumns: "1.4fr 1fr", gap: 20 }}>
        {/* Follow-ups */}
        <Card>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "16px 20px", borderBottom: "1px solid var(--border-subtle)" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 9 }}>
              <Icon n="calendar-clock" size={17} color="var(--green-700)" />
              <h3 style={{ fontSize: 15, fontWeight: 700, color: "var(--text-strong)", margin: 0 }}>Upcoming follow-ups</h3>
            </div>
            <a href="#" onClick={(e)=>e.preventDefault()} style={{ fontSize: 12.5, fontWeight: 600, color: "var(--brand-primary)", textDecoration: "none" }}>View all</a>
          </div>
          <div>
            {FOLLOWUPS.map((f, i) => (
              <div key={f.id} style={{ display: "flex", alignItems: "center", gap: 12, padding: "13px 20px", borderBottom: i < FOLLOWUPS.length - 1 ? "1px solid var(--border-subtle)" : "none" }}>
                <div style={{ width: 36, height: 36, borderRadius: "50%", background: "var(--brand-primary-soft)", color: "var(--green-700)", display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 700, fontSize: 13, flexShrink: 0 }}>
                  {f.name.split(" ").map(p=>p[0]).slice(0,2).join("").toUpperCase()}
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13.5, fontWeight: 600, color: "var(--text-strong)" }}>{f.name}</div>
                  <div style={{ fontSize: 11.5, color: "var(--text-muted)", fontFamily: "var(--font-mono)" }}>{f.id} · {f.goal}</div>
                </div>
                <StatusBadge label={f.due} status={f.tone} />
              </div>
            ))}
          </div>
        </Card>

        {/* Announcements */}
        <Card>
          <div style={{ display: "flex", alignItems: "center", gap: 9, padding: "16px 20px", borderBottom: "1px solid var(--border-subtle)" }}>
            <Icon n="megaphone" size={17} color="var(--orange-600)" />
            <h3 style={{ fontSize: 15, fontWeight: 700, color: "var(--text-strong)", margin: 0 }}>Announcements</h3>
          </div>
          <div>
            {ANNOUNCEMENTS.map((a, i) => (
              <div key={a.title} style={{ padding: "13px 20px", borderBottom: i < ANNOUNCEMENTS.length - 1 ? "1px solid var(--border-subtle)" : "none", background: a.pinned ? "var(--orange-50)" : "transparent" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 7, marginBottom: 5 }}>
                  {a.pinned && <Icon n="pin" size={11} color="var(--orange-600)" />}
                  <Badge tone={a.cat === "Clinical" ? "emerald" : a.cat === "Kitchen" ? "lime" : "amber"}>{a.cat}</Badge>
                  <span style={{ marginLeft: "auto", fontSize: 11, color: "var(--text-faint)", fontFamily: "var(--font-mono)" }}>{a.time}</span>
                </div>
                <div style={{ fontSize: 13.5, fontWeight: 600, color: "var(--text-strong)", lineHeight: 1.4 }}>{a.title}</div>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}
window.DashboardScreen = DashboardScreen;
