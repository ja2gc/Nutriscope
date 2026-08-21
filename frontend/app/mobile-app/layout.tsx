import type { Metadata, Viewport } from "next";

export const metadata: Metadata = {
  manifest: "/fss.webmanifest",
  applicationName: "NutriScope Food Service Staff",
};


export const viewport: Viewport = { themeColor: "#047857" };

export default function MobileAppLayout({ children }: { children: React.ReactNode }) {
  return children;
}
