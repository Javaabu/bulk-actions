import { defineConfig } from "vitest/config"

const stencil = new URL("./vendor/javaabu/stencil/resources/js", import.meta.url).pathname

export default defineConfig({
  resolve: {
    alias: {
      "@javaabu/bulk-actions": new URL("./resources/js", import.meta.url).pathname,
      // Apps get @javaabu/stencil through javaabu/manifest; here it comes from the Composer install.
      "@javaabu/stencil": stencil,
    },
  },
  test: {
    environment: "jsdom",
    include: ["tests/js/**/*.test.{ts,tsx}"],
    setupFiles: ["tests/js/setup.ts"],
    coverage: {
      provider: "v8",
      include: ["resources/js/**/*.{ts,tsx}"],
      reporter: ["text", "clover", "lcov"],
      reportsDirectory: "coverage",
    },
  },
})
