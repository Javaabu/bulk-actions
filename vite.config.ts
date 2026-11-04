import { defineConfig } from "vite"

/**
 * Builds the prebuilt Blade script in dist/ for apps that don't build javaabu/bulk-actions
 * with their own Vite. javaabu/manifest finds it through dist/.vite/manifest.json, keyed
 * by this entry path. dist/ is committed; CI fails when it's out of date.
 *
 * The React components aren't prebuilt: apps build them from resources/js with their Vite.
 */
export const entries = ["resources/js/blade/bulk-actions.ts"]

export default defineConfig({
  // Relative URLs, as javaabu/manifest serves dist/ from a sub-path.
  base: "./",
  publicDir: false,
  build: {
    outDir: "dist",
    emptyOutDir: true,
    manifest: true,
    target: "es2020",
    sourcemap: false,
    reportCompressedSize: false,
    rollupOptions: { input: entries },
  },
})
