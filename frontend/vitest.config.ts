import { defineConfig } from "vitest/config";
import path from "node:path";

// Minimal Vitest setup for pure-logic unit tests (no DOM needed yet).
// `@/` resolves to the frontend root, matching tsconfig paths.
export default defineConfig({
  resolve: {
    alias: { "@": path.resolve(__dirname, ".") },
  },
  test: {
    environment: "node",
    include: ["**/*.test.ts", "**/*.test.tsx"],
    // Legacy suite uses Node's built-in `node:test` runner, not Vitest. Excluded
    // here until it's migrated to Vitest, so it doesn't get double-collected.
    exclude: [
      "**/node_modules/**",
      // Legacy suites on Node's built-in `node:test` runner (not Vitest). Run via
      // `node --test` until migrated. Listed explicitly so Vitest skips them.
      "lib/nutritionCalculations.test.ts",
      "lib/assessmentPageCalcs.test.ts",
    ],
  },
});
