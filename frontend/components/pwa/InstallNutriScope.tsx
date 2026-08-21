"use client";

import Image from "next/image";
import Link from "next/link";
import { Download, ExternalLink, Smartphone } from "lucide-react";
import { useEffect, useState } from "react";
import { isPhoneOrTablet } from "@/lib/pwa";

interface InstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: "accepted" | "dismissed" }>;
}

function standalone() {
  return window.matchMedia("(display-mode: standalone)").matches;
}

export function InstallNutriScope({ mode }: { mode: "login" | "landing" }) {
  const [mobileDevice, setMobileDevice] = useState<boolean | null>(null);
  const [installed, setInstalled] = useState(false);
  const [promptEvent, setPromptEvent] = useState<InstallPromptEvent | null>(null);
  const [showInstructions, setShowInstructions] = useState(false);

  useEffect(() => {
    const updateDevice = () => setMobileDevice(isPhoneOrTablet({
      coarsePointer: window.matchMedia("(any-pointer: coarse)").matches,
      viewportWidth: window.innerWidth,
    }));
    const onPrompt = (event: Event) => {
      event.preventDefault();
      setPromptEvent(event as InstallPromptEvent);
    };
    const onInstalled = () => {
      setInstalled(true);
      setPromptEvent(null);
    };

    updateDevice();
    setInstalled(standalone());
    window.addEventListener("resize", updateDevice);
    window.addEventListener("beforeinstallprompt", onPrompt);
    window.addEventListener("appinstalled", onInstalled);
    return () => {
      window.removeEventListener("resize", updateDevice);
      window.removeEventListener("beforeinstallprompt", onPrompt);
      window.removeEventListener("appinstalled", onInstalled);
    };
  }, []);

  async function install() {
    if (!promptEvent) {
      setShowInstructions(true);
      return;
    }
    await promptEvent.prompt();
    await promptEvent.userChoice;
    setPromptEvent(null);
  }

  if (mobileDevice === null) {
    return <div className="min-h-28 animate-pulse rounded-2xl bg-warm-100" aria-hidden="true" />;
  }

  if (!mobileDevice) {
    return (
      <section className="rounded-2xl border border-warm-200 bg-warm-50 p-4 text-center" aria-label="Open NutriScope on your phone">
        <p className="text-sm font-extrabold text-warm-900">Scan with your phone</p>
        <p className="mt-1 text-sm leading-5 text-warm-500">Food Service Staff use NutriScope on a phone or tablet.</p>
        <Image
          src="/nutriscope-mobile-qr.svg"
          width={176}
          height={176}
          alt="QR code for the NutriScope Food Service Staff app"
          className="mx-auto mt-3 rounded-xl border border-warm-200 bg-white p-2"
        />
      </section>
    );
  }

  if (mode === "login") {
    return (
      <section className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4" aria-label="Food Service Staff mobile access">
        <div className="flex items-center gap-3">
          <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
            <Smartphone className="h-5 w-5" aria-hidden="true" />
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-extrabold text-emerald-950">Food Service Staff</p>
            <p className="mt-1 text-sm leading-5 text-emerald-800">Use the mobile NutriScope app.</p>
          </div>
        </div>
        <Link href="/mobile-app" className="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">
          <ExternalLink className="h-4 w-4" aria-hidden="true" />
          Open mobile app setup
        </Link>
      </section>
    );
  }

  return (
    <section className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4" aria-label="Install NutriScope">
      <div className="flex items-start gap-3">
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
          <Smartphone className="h-5 w-5" aria-hidden="true" />
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-extrabold text-emerald-950">Food Service Staff mobile app</p>
          <p className="mt-1 text-sm leading-5 text-emerald-800">
            {installed ? "NutriScope is installed on this device." : "Install for quick access from your home screen."}
          </p>
        </div>
      </div>

      <div className="mt-4 grid gap-2 sm:grid-cols-2">
        {!installed && (
          <button
            type="button"
            onClick={() => void install()}
            className="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
          >
            <Download className="h-4 w-4" aria-hidden="true" />
            Install NutriScope
          </button>
        )}
        <Link
          href="/fss/login"
          className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-white px-4 py-2.5 text-sm font-bold text-emerald-800 transition-colors hover:bg-emerald-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
        >
          <ExternalLink className="h-4 w-4" aria-hidden="true" />
          Open FSS app
        </Link>
      </div>

      {showInstructions && !promptEvent && !installed && (
        <p className="mt-3 text-xs leading-5 text-emerald-800" aria-live="polite">
          {mode === "landing"
            ? "In your browser menu, choose Add to Home screen or Install app."
            : "Open this page in Chrome or Safari, then choose Add to Home screen from the browser menu."}
        </p>
      )}
    </section>
  );
}
