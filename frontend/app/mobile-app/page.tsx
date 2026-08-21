import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { FssAppAccess } from "@/components/mobile-app/FssAppAccess";
import { Logo } from "@/components/ui/Logo";

export default function MobileAppPage() {
  return (
    <main className="min-h-dvh bg-warm-50 px-4 py-8 sm:px-6 sm:py-12">
      <div className="mx-auto w-full max-w-xl">
        <Link
          href="/login"
          className="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm font-bold text-warm-600 hover:text-warm-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          Back to sign in
        </Link>

        <section className="mt-5 rounded-3xl border border-warm-200 bg-white p-5 shadow-sm sm:p-8">
          <Logo variant="light" />
          <p className="mt-5 text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700">
            Food Service Staff
          </p>
          <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-warm-900">
            NutriScope Android app
          </h1>
          <p className="mt-3 text-base leading-7 text-warm-600">
            Download the Food Service Staff app for menu viewing, meal preparation, accomplishments, and purchase records.
          </p>

          <div className="mt-6">
            <FssAppAccess />
          </div>
          <div className="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
            Android may ask you to allow installation from your browser. Download updates from this same page; the QR code does not change.
          </div>
        </section>
      </div>
    </main>
  );
}
