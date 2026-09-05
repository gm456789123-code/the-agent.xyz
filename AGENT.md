# Project: Gaming & Digital Service Platform (Headless WP + Astro)

## Tech Stack & Environment
- **Frontend:** Astro (SSR) hosted on Node.js environment.
- **Styling:** Tailwind CSS.
- **Backend / CMS:** WordPress (REST API / GraphQL).
- **Database Management:** MySQL via Docker (phpMyAdmin available for DB administration).
- **Microservices:** Node.js backend for webhooks (payment slips/automation) and real-time events.

## Core Architectural Rules
1. **Performance First (SEO Focus):** Ensure Astro renders pages efficiently (Zero-JS by default where possible) to maximize Core Web Vitals and SEO performance.
2. **Separation of Concerns:** 
   - WordPress handles content, products, and admin CRUD.
   - Astro handles public-facing SSR views and high-speed rendering.
   - Node.js handles background automation, webhooks, and real-time triggers.
3. **Design System Adherence:** Strictly follow the Cyberpunk/High-Tech dark mode aesthetic (Neon accents, sharp angles, custom Tailwind classes). Avoid generic corporate UI patterns.
4. **Database Operations:** All database changes, backups, and structural inspections should reference the Docker-managed MySQL instance via phpMyAdmin.