"use client";

import Image from "next/image";
import Link from "next/link";
import { Download, ExternalLink, QrCode, Smartphone } from "lucide-react";
import { useEffect, useState } from "react";

const APK_URL = "/downloads/nutriscope-fss.apk";

export function FssAppAccess({ compact = false }: { compact?: boolean }) {
  const [mobile, setMobile] = useState<boolean | null>(null);
  useEffect(() => {
    const detect = () => setMobile(window.matchMedia("(any-pointer: coarse)").matches && window.innerWidth <= 1280);
    detect(); window.addEventListener("resize", detect); return () => window.removeEventListener("resize", detect);
  }, []);

  if (mobile === null) return <div className="min-h-28 animate-pulse rounded-2xl bg-warm-100" aria-hidden="true" />;
  if (!mobile) return <section className="rounded-2xl border border-warm-200 bg-warm-50 p-4 text-center" aria-label="Scan to download the Food Service Staff app">
    <QrCode className="mx-auto h-5 w-5 text-emerald-700" aria-hidden="true" />
    <p className="mt-2 text-sm font-extrabold text-warm-900">Scan with your phone</p>
    <p className="mt-1 text-sm leading-5 text-warm-500">Food Service Staff use the Android app. This is not a desktop app.</p>
    <Image src="/nutriscope-mobile-qr.svg" width={compact ? 148 : 176} height={compact ? 148 : 176} alt="QR code opening the NutriScope Food Service Staff Android download page" className="mx-auto mt-3 rounded-xl border border-warm-200 bg-white p-2" />
  </section>;

  return <section className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4" aria-label="Download the Food Service Staff Android app">
    <div className="flex items-center gap-3"><span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"><Smartphone className="h-5 w-5" aria-hidden="true" /></span><div><p className="text-sm font-extrabold text-emerald-950">Food Service Staff Android app</p><p className="mt-1 text-sm leading-5 text-emerald-800">Download the latest signed NutriScope APK.</p></div></div>
    <a href={APK_URL} download className="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"><Download className="h-4 w-4" aria-hidden="true" />Download NutriScope APK</a>
    {compact && <Link href="/mobile-app" className="mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 text-sm font-bold text-emerald-800"><ExternalLink className="h-4 w-4" aria-hidden="true" />Install instructions</Link>}
  </section>;
}
