/** @type {import('tailwindcss').Config} */
export default {
  content: ["./src/**/*.{astro,html,js,jsx,ts,tsx}"],
  theme: {
    extend: {
      colors: {
        background: "#040914",
        surface: {
          DEFAULT: "rgba(8, 19, 38, 0.78)",
          card: "rgba(12, 27, 54, 0.72)",
          hover: "rgba(18, 38, 74, 0.88)",
          glass: "rgba(255, 255, 255, 0.04)",
          border: "rgba(56, 189, 248, 0.22)",
        },
        aurora: {
          cyan: "#38BDF8",
          sky: "#7DD3FC",
          purple: "#C4B5FD",
          violet: "#A78BFA",
          pink: "#F472B6",
          rose: "#FB7185",
          mint: "#34D399",
          emerald: "#10B981",
          teal: "#2DD4BF",
          amber: "#FBBF24",
        },
        ink: {
          DEFAULT: "#FFFFFF",
          muted: "#E2E8F0",
          subtle: "#94A3B8",
        },
      },
      fontFamily: {
        display: ["Prompt", "Inter", "sans-serif"],
        body: ["Prompt", "Inter", "sans-serif"],
        mono: ["JetBrains Mono", "monospace"],
      },
      boxShadow: {
        subtle: "0 2px 8px 0 rgba(0, 0, 0, 0.4)",
        glass: "0 8px 32px 0 rgba(0, 0, 0, 0.6), inset 0 1px 1px 0 rgba(255, 255, 255, 0.12)",
        pop: "0 12px 40px -4px rgba(56, 189, 248, 0.22), inset 0 1px 2px 0 rgba(255, 255, 255, 0.18)",
        glow: "0 0 35px -2px rgba(52, 211, 153, 0.45)",
        aurora: "0 0 40px -5px rgba(56, 189, 248, 0.35), 0 0 20px -2px rgba(52, 211, 153, 0.3)",
      },
    },
  },
  plugins: [],
};
