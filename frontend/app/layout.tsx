import type { Metadata } from "next";
import { Plus_Jakarta_Sans, Space_Grotesk } from "next/font/google";
import { AuthProvider } from "@/contexts/AuthContext";
import "./globals.css";

const plusJakarta = Plus_Jakarta_Sans({
  subsets: ["latin"],
  weight: ["400", "500", "600", "700", "800"],
  variable: "--font-plus-jakarta",
  display: "swap",
});

const spaceGrotesk = Space_Grotesk({
  subsets: ["latin"],
  weight: ["400", "500", "600", "700"],
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
        <AuthProvider>{children}</AuthProvider>
      </body>
    </html>
  );
}
