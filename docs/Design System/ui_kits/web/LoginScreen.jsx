// NutriScope — Split-screen login landing.
// Left: forest brand panel with fresh-produce imagery + value props.
// Right: the sign-in card (composes Input, Button, Logo from the DS bundle).
const { Input, Button, Logo } = window.NutriScopeDesignSystem_c4cce8;

function LoginScreen({ onSignIn }) {
  const [email, setEmail] = React.useState("m.santos@hospital.ph");
  const [password, setPassword] = React.useState("••••••••");
  const [loading, setLoading] = React.useState(false);

  const submit = (e) => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => { setLoading(false); onSignIn?.(); }, 850);
  };

  const points = [
    ["heart-pulse", "Run the full Nutrition Care Process — assess, diagnose, intervene, monitor."],
    ["salad", "Plan menus and track cost-per-head down to the last ₱."],
    ["shield-check", "Every action logged. Built for hospital-grade accountability."],
  ];

  return (
    <div style={{ display: "flex", minHeight: "100%", background: "var(--surface-card)" }}>
      {/* LEFT — brand panel */}
      <div style={{ position: "relative", flex: "1.05", display: "flex", flexDirection: "column", justifyContent: "space-between", padding: "44px 48px", overflow: "hidden", color: "#fff" }}>
        <img src="https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?auto=compress&cs=tinysrgb&w=1400"
          alt="" style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover" }} />
        <div style={{ position: "absolute", inset: 0, background: "linear-gradient(155deg, rgba(6,32,17,0.86) 0%, rgba(10,38,26,0.82) 45%, rgba(5,150,105,0.62) 100%)" }} />
        {/* sprout watermark */}
        <svg viewBox="0 0 200 200" style={{ position: "absolute", right: -40, bottom: -30, width: 320, height: 320, opacity: 0.12 }}>
          <path d="M100 40C100 40 60 75 60 120C60 142 78 160 100 160C122 160 140 142 140 120C140 75 100 40 100 40Z" fill="#a3e635" />
        </svg>

        <div style={{ position: "relative", display: "flex", alignItems: "center", gap: 11 }}>
          <Logo variant="forest" size={32} />
        </div>

        <div style={{ position: "relative" }}>
          <div style={{ fontSize: 13, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.14em", color: "#86efac", marginBottom: 18 }}>
            Clinical &amp; Operational Care
          </div>
          <h1 style={{ fontSize: 46, lineHeight: 1.04, fontWeight: 800, letterSpacing: "-0.03em", margin: 0, maxWidth: 520 }}>
            Eat well,<br />heal well.
          </h1>
          <p style={{ fontSize: 16, lineHeight: 1.6, color: "rgba(255,255,255,0.82)", maxWidth: 440, marginTop: 18 }}>
            One console for the dietitians and kitchen teams who nourish every patient, every day.
          </p>
          <div style={{ marginTop: 28, display: "flex", flexDirection: "column", gap: 14 }}>
            {points.map(([icon, text]) => (
              <div key={text} style={{ display: "flex", gap: 12, alignItems: "flex-start", maxWidth: 460 }}>
                <span style={{ flexShrink: 0, width: 30, height: 30, borderRadius: 9, background: "rgba(163,230,53,0.18)", display: "inline-flex", alignItems: "center", justifyContent: "center", color: "#bef264" }}>
                  <i data-lucide={icon} style={{ width: 16, height: 16 }} />
                </span>
                <span style={{ fontSize: 14.5, lineHeight: 1.45, color: "rgba(255,255,255,0.9)", paddingTop: 5 }}>{text}</span>
              </div>
            ))}
          </div>
        </div>

        <div style={{ position: "relative", fontSize: 12, color: "rgba(255,255,255,0.55)", display: "flex", gap: 8, alignItems: "center" }}>
          <i data-lucide="lock" style={{ width: 13, height: 13 }} />
          Secure connection · Activity logs active
        </div>
      </div>

      {/* RIGHT — sign-in */}
      <div style={{ flex: "0.95", display: "flex", alignItems: "center", justifyContent: "center", padding: "40px 32px" }}>
        <div style={{ width: "100%", maxWidth: 380 }}>
          <div style={{ marginBottom: 26 }}>
            <h2 style={{ fontSize: 28, fontWeight: 800, letterSpacing: "-0.02em", color: "var(--text-strong)", margin: 0 }}>Welcome back</h2>
            <p style={{ fontSize: 14.5, color: "var(--text-muted)", marginTop: 7 }}>Sign in to access your workspace.</p>
          </div>

          <form onSubmit={submit} style={{ display: "flex", flexDirection: "column", gap: 16 }}>
            <Input label="Email Address" type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" />
            <div>
              <Input label="Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" />
              <div style={{ textAlign: "right", marginTop: 8 }}>
                <a href="#" onClick={(e) => e.preventDefault()} style={{ fontSize: 12.5, fontWeight: 600, color: "var(--brand-primary)", textDecoration: "none" }}>Forgot password?</a>
              </div>
            </div>
            <Button type="submit" fullWidth size="lg" loading={loading} style={{ boxShadow: "var(--shadow-brand)" }}>Sign In</Button>
          </form>

          <div style={{ marginTop: 22, paddingTop: 18, borderTop: "1px solid var(--border-subtle)", display: "flex", alignItems: "center", justifyContent: "center", gap: 8 }}>
            <span style={{ fontSize: 10.5, fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.12em", color: "var(--text-faint)" }}>
              RND · Admin · Food Service
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
window.LoginScreen = LoginScreen;
