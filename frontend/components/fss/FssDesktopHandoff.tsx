import { InstallNutriScope } from "@/components/pwa/InstallNutriScope";
import { Logo } from "@/components/ui/Logo";

export function FssDesktopHandoff() {
  return (
    <main className="grid min-h-dvh place-items-center bg-warm-50 p-6">
      <div className="w-full max-w-md rounded-3xl border border-warm-200 bg-white p-8 shadow-sm">
        <Logo variant="light" />
        <h1 className="mt-6 text-2xl font-extrabold text-warm-900">Food Service Staff mobile access</h1>
        <p className="mt-2 text-base leading-6 text-warm-600">Continue on a phone or tablet. RND and Admin use the regular website.</p>
        <div className="mt-6"><InstallNutriScope mode="landing" /></div>
      </div>
    </main>
  );
}
