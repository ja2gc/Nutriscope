import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "NutriScope FSS",
    short_name: "NutriScope",
    description: "Food Service Staff operations for NutriScope.",
    start_url: "/fss",
    display: "standalone",
    background_color: "#f9fafb",
    theme_color: "#047857",
    orientation: "any",
    icons: [
      { src: "/nutriscope-fss-192.png", sizes: "192x192", type: "image/png" },
      { src: "/nutriscope-fss-512.png", sizes: "512x512", type: "image/png" },
    ],
  };
}
