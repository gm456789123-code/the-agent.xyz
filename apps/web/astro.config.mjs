import { defineConfig } from "astro/config";

export default defineConfig({
  output: "static",
  server: {
    host: '127.0.0.1',
    headers: {
      'X-Content-Type-Options': 'nosniff',
      'X-Frame-Options': 'DENY',
      'Referrer-Policy': 'strict-origin-when-cross-origin',
      'Content-Security-Policy': "base-uri 'self'; object-src 'none'; frame-ancestors 'none'",
    },
  },
});
