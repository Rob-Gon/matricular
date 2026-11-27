# Design system

- Stack: Tailwind CSS 4 + Alpine.js via Vite. No CDNs: self-host fonts and use an inline SVG icon sprite.
- Theme: vehicles and roads, no mascot. Core elements as reusable Blade components with inline SVG:
  - Spanish/EU license plate (blue EU band with "E", condensed DIN-style free font) as the main game element.
  - Traffic light for the 3-2-1 start, speedometer/fuel gauge for remaining time, asphalt with lane markings as dividers, road signs for feedback (stop = error, etc.), ITV sticker for achievements, driving-licence card for the profile, starting grid with up to 16 slots for the lobby, podium for results.
- Mobile-first. WCAG 2.2 AA: contrast, full keyboard play, visible focus, `prefers-reduced-motion` respected for every animation.
- Feedback states for every data-driven component: loading, empty, error.
- Public pages (home, how-to-play guide, rankings) get unique title/meta description, Open Graph, one h1, and appear in `sitemap.xml`.
