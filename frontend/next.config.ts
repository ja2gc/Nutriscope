import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
  // TypeScript is validated by the deployment workflow before this image build.
  // Avoid repeating the memory-heavy check on the 2 GB production server.
  typescript: {
    ignoreBuildErrors: true,
  },
};

export default nextConfig;
