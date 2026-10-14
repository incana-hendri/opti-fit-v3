# 08: Brand and design

The **brand is fixed**: logo, colours and font must match exactly. The **UX is new**: the brand is applied
with a clean, modern, mobile-first interface, not a copy of any earlier site.

## Logo files (`brand/logos/`)

| File | Use |
|---|---|
| `optifit-logo-on-dark.png` | Primary. Transparent PNG with white "Opti", orange "Fit" and the orange barbell mark. Use it on the night background (header, auth pages, admin dark mode). 2527×780, so scale it down. |
| `optifit-logo-on-dark-tagline.png` | Same, with the tagline "Functional Fitness Training". Transparent, 2560×848. Landing hero, auth pages. |
| `optifit-logo-on-light-tagline.jpg` | Black "Opti", orange "Fit" and tagline on white (no transparency). Emails, admin light mode, print. |
| `optifit-square-on-dark.jpg` / `optifit-square-on-light.jpg` | 1024² square with background. Social/OG image (`og:image`). |
| `optifit-mark-on-dark.jpg` | 210² barbell mark on night background. Source for the favicon and apple-touch-icon. |

Build step (Milestone 0): copy the logos into `public/images/brand/`. Generate a properly downscaled
`logo-on-dark@1x.png` (≈ 320px wide) and `@2x` (≈ 640px), `favicon.ico`/`favicon-32.png`/`apple-touch-icon.png` (180²)
from the mark, and an `og.jpg` (1200×630) from the square dark logo centred on `#0D0918`. Use any local image
tool. The generated files are committed. **Never redraw the logo or set it as live text.** In tight spots
(e.g. a 32px-high mobile header) use the on-dark PNG at 28–32px height. Keep clear space of at least the
height of the "O" around the logo.

## Colours

Defined as CSS custom properties in `brand/tokens.css` and mapped into Tailwind v4's `@theme`.

| Token | Hex | Use |
|---|---|---|
| `--of-night` | `#0D0918` | Page background (brand night/indigo-black) |
| `--of-surface` | `#171423` | Cards, sheets, nav bars |
| `--of-surface-raised` | `#221D33` | Inputs, hovered cards, modals |
| `--of-border` | `rgba(255,255,255,0.10)` | Hairlines, card borders |
| `--of-orange` | `#FF9500` | **Brand primary**: buttons, active tab, focus ring, key numbers |
| `--of-orange-light` | `#FFB84D` | Hover, highlights |
| `--of-orange-dark` | `#E6860E` | Pressed |
| `--of-text` | `#FFFFFF` | Primary text on night |
| `--of-text-muted` | `#CCCCCC` | Secondary text |
| `--of-text-subtle` | `#9A96A8` | Tertiary text, placeholders (≥ 4.5:1 on night) |
| `--of-success` | `#2ED573` | Places available, success toasts, "Attended" |
| `--of-danger` | `#FF4757` | Errors, destructive actions, "Full" |
| `--of-info` | `#5B7CFA` | Informational badges (sparingly) |
| `--of-ink` | `#0D0918` | **Text on orange**. White on orange fails contrast, so orange buttons always use night text |

Light surfaces (emails, print) use white `#FFFFFF`, page `#F4F3F7`, text `#1A1A1A`, muted `#555555`, and
orange for accents and buttons (with `#0D0918` text).

The customer site is **dark only** (night background), which is the brand's signature look. No theme toggle.
Set `<meta name="color-scheme" content="dark">` and `theme-color` `#0D0918`.

## Typography

- **Poppins** (geometric sans; it matches the wordmark). Self-host it with `@fontsource/poppins`, weights 400, 500, 600, 700.
- Headings: Poppins 600, tight letter-spacing (−0.01em). Body: Poppins 400, 16px, line-height 1.5.
  Numbers that matter (credits, times): Poppins 600 with tabular figures (`font-variant-numeric: tabular-nums`).
- Scale: 12 / 14 / 16 / 18 / 22 / 28 / 36 / 48px. Mobile H1 is 28px, desktop 36–48px.
- System font fallback: `ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif`.

## UI principles

1. **One obvious primary action per screen**, styled as a solid orange button with night text, 48px tall and fully rounded (`rounded-full`) on mobile.
2. **Cards on night:** `--of-surface` background, 16px radius, 1px `--of-border`, 16–20px padding. No heavy shadows; depth comes from surface steps.
3. **Status by text and colour together:** badges are pill-shaped with a tinted background (10–15% of the colour) and a coloured label.
4. **Bottom sheets** (mobile) or **centred modals** (desktop) for book/cancel/add-member. They're focus-trapped and close with Esc.
5. **Motion:** subtle (150–200ms ease-out) for the sheet slide and toast. Disabled with `prefers-reduced-motion`.
6. **Spacing:** 4px base grid, 16px page gutter on mobile, 24px on desktop.
7. **Icons:** one consistent outline set (e.g. Heroicons, already available through Blade UI or Filament). Use them only where they add meaning.
8. **Imagery:** none required. The landing hero may use a subtle orange radial glow on night behind the logo. No stock photos.
9. **Tone:** warm, short and direct. "Booked! See you Monday at 05:00." Not "Your booking request has been successfully processed."

## Components to build (Blade, in `resources/views/components/`)

`x-button` (primary/secondary/ghost/danger, sizes) · `x-card` · `x-badge` · `x-input`, `x-select`, `x-checkbox`, `x-radio-card` ·
`x-sheet` (bottom sheet/modal) · `x-toast` · `x-empty-state` · `x-credit-meter` · `x-session-card` · `x-day-strip` ·
`x-member-chip` · `x-tab-bar` · `x-page-header`. Build a hidden local-only `/_styleguide` route that renders them
all, for visual QA (disabled in production).
