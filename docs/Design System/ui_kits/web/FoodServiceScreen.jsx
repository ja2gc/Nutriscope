// NutriScope — Food Service / Inventory screen.
const { Card, KpiCard, Badge, StatusBadge, Button, Input, Tabs } = window.NutriScopeDesignSystem_c4cce8;
const Icon = window.NSIcon;

const ITEMS = [
  { name: "Chicken breast, skinless", type: "Ingredient", qty: "42.0", unit: "kg", ok: true },
  { name: "Brown rice", type: "Ingredient", qty: "0", unit: "kg", ok: false },
  { name: "Malunggay leaves", type: "Ingredient", qty: "8.5", unit: "kg", ok: true },
  { name: "Low-sodium broth", type: "Supply", qty: "0", unit: "L", ok: false },
  { name: "Banana, lakatan", type: "Ingredient", qty: "120", unit: "pcs", ok: true },
  { name: "Tilapia fillet", type: "Ingredient", qty: "16.2", unit: "kg", ok: true },
  { name: "Iodized salt", type: "Supply", qty: "3.0", unit: "kg", ok: true },
  { name: "Diabetic meal pack", type: "Recipe", qty: "0", unit: "serv", ok: false },
];

function FoodServiceScreen() {
  const [tab, setTab] = React.useState("inventory");
  const [filter, setFilter] = React.useState("all");
  const filters = [["all","All"],["ingredient","Ingredients"],["supply","Supplies"],["recipe","Recipes"]];
  const rows = ITEMS.filter(i => filter === "all" || i.type.toLowerCase() === filter);

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 18, maxWidth: 1180, margin: "0 auto" }}>
      <Tabs value={tab} onChange={setTab} tabs={[{id:'inventory',label:'Inventory'},{id:'menu',label:'Menu Cycle'},{id:'budget',label:'Budget'},{id:'procurement',label:'Procurement'}]} />

      <div style={{ display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: 14 }}>
        <KpiCard label="Total items" value="248" tone="neutral" />
        <KpiCard label="In stock" value="243" tone="emerald" />
        <KpiCard label="No stock" value="5" tone="red" hint="Restock needed before lunch service" />
      </div>

      <Card>
        {/* toolbar */}
        <div style={{ display: "flex", alignItems: "center", gap: 12, padding: "14px 18px", borderBottom: "1px solid var(--border-subtle)", flexWrap: "wrap" }}>
          <div style={{ flex: 1, minWidth: 220, position: "relative" }}>
            <span style={{ position: "absolute", left: 12, top: "50%", transform: "translateY(-50%)", color: "var(--text-faint)", display: "inline-flex" }}><Icon n="search" size={16} /></span>
            <input placeholder="Search inventory…" style={{ width: "100%", boxSizing: "border-box", padding: "9px 13px 9px 36px", fontSize: 13.5, fontFamily: "var(--font-sans)", color: "var(--text-strong)", background: "var(--surface-sunken)", border: "1px solid var(--border-subtle)", borderRadius: "var(--radius-md)", outline: "none" }} />
          </div>
          <div style={{ display: "flex", gap: 6 }}>
            {filters.map(([v, l]) => (
              <button key={v} onClick={() => setFilter(v)} style={{
                padding: "7px 13px", borderRadius: "var(--radius-full)", fontSize: 12, fontWeight: 700, cursor: "pointer",
                border: filter === v ? "1px solid var(--brand-primary)" : "1px solid var(--border-subtle)",
                background: filter === v ? "var(--brand-primary)" : "var(--surface-card)",
                color: filter === v ? "#fff" : "var(--text-muted)" }}>{l}</button>
            ))}
          </div>
          <Button variant="primary" size="sm" leftIcon={<Icon n="plus" size={15} />}>Add Item</Button>
        </div>

        {/* table */}
        <div>
          <div style={{ display: "grid", gridTemplateColumns: "2.4fr 1fr 1fr 1fr", padding: "10px 20px", borderBottom: "1px solid var(--border-subtle)", fontSize: 10.5, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.06em", color: "var(--text-faint)" }}>
            <span>Item</span><span>Type</span><span style={{ textAlign: "right" }}>In stock</span><span style={{ textAlign: "right" }}>Status</span>
          </div>
          {rows.map((it, i) => (
            <div key={it.name} style={{ display: "grid", gridTemplateColumns: "2.4fr 1fr 1fr 1fr", alignItems: "center", padding: "12px 20px", borderBottom: i < rows.length - 1 ? "1px solid var(--border-subtle)" : "none" }}>
              <span style={{ fontSize: 13.5, fontWeight: 600, color: "var(--text-strong)" }}>{it.name}</span>
              <span><Badge tone="neutral">{it.type}</Badge></span>
              <span style={{ textAlign: "right", fontFamily: "var(--font-mono)", fontSize: 13, fontWeight: 600, color: it.ok ? "var(--text-strong)" : "var(--status-danger)" }}>{it.qty} <span style={{ color: "var(--text-faint)", fontWeight: 400 }}>{it.unit}</span></span>
              <span style={{ textAlign: "right" }}><StatusBadge label={it.ok ? "In stock" : "No stock"} status={it.ok ? "success" : "error"} /></span>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}
window.FoodServiceScreen = FoodServiceScreen;
