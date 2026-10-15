import type { Metadata } from "next";
import localFont from "next/font/local";
import { AuthProvider } from "@/contexts/AuthContext";
import { OnboardingReminder } from "@/components/auth/OnboardingReminder";
import { LegacyAppCleanup } from "@/components/mobile-app/LegacyAppCleanup";
import "./globals.css";

const plusJakarta = localFont({
  src: "./fonts/plus-jakarta-sans.woff2",
  weight: "400 800",
  variable: "--font-plus-jakarta",
  display: "swap",
});

const spaceGrotesk = localFont({
  src: "./fonts/space-grotesk.woff2",
  weight: "400 700",
  variable: "--font-space-grotesk",
  display: "swap",
});

export const metadata: Metadata = {
  title: "NutriScope - Clinical & Operational System",
  description: "Clinical nutrition and food service management platform.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className={`h-full antialiased ${plusJakarta.variable} ${spaceGrotesk.variable}`}>
      <body className="min-h-full flex flex-col font-sans bg-warm-25 text-warm-900">
        <AuthProvider><LegacyAppCleanup /><OnboardingReminder />{children}</AuthProvider>
      </body>
    </html>
  );
}
