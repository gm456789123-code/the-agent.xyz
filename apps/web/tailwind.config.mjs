/** @type {import('tailwindcss').Config} */
export default {
  content: ["./src/**/*.{astro,html,js,jsx,ts,tsx}"],
  theme: {
    extend: {
      colors: {
        background: "#050505",
        surface: "#121216",
        "neon-green": "#00FF66",
        "neon-purple": "#9D00FF",
        ink: "#F4F4F9",
      },
      fontFamily: {
        display: ["Rajdhani", "Orbitron", "sans-serif"],
        body: ["Inter", "sans-serif"],
      },
    },
  },
  plugins: [],
};
