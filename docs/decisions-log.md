# Decisions log

One line per decision made during implementation that the spec didn't settle, or a deliberate deviation
from it. Format: `YYYY-MM-DD · decision · reason`. Keep it short.

## Decisions

- 2026-10-14 · Spec written. Settled product decisions are D1–D12 in `01-product-overview.md` · baseline
- 2026-10-14 · PHP 8.4 everywhere: Sail runtime, CI, `require.php ^8.4` and `config.platform.php` 8.4.1 (docs/07 updated from "≥ 8.3") · the cPanel server is on 8.1, which Laravel 13 can't run, and the owner can switch it to 8.4; pinning the platform keeps the lock installable there. 8.4.1 is the floor Symfony 8.1 needs
- 2026-10-14 · Laravel 13.36, Filament 5.10, Livewire 4.4, Fortify 1.41, Pest 5.3 (PHPUnit 13) · latest stable majors at scaffold time
- 2026-10-14 · Fortify's two-factor and passkey features and migrations removed; email verification on · not in the spec (BR-1.x); passkeys would add a table
- 2026-10-14 · Fortify `views` is off until M1 builds the branded auth views · with it on and no views registered, `/login` would 500
- 2026-10-14 · Laravel Boost's generated `AGENTS.md`/`CLAUDE.md` not imported · ours is the source of truth; Boost's tells agents to install PHP on the host
- 2026-10-14 · `Settings::current()` caches the attribute array, not the model · Laravel 13's cache refuses to unserialize objects (`serializable_classes` = false), a security default worth keeping
- 2026-10-14 · Poppins latin subset only, built by Vite from `@fontsource/poppins`; the Filament panel loads the same `fonts.css` via `LocalFontProvider` · English only; Filament's default font provider calls Bunny Fonts (third party)
- 2026-10-14 · `admin_notification_emails` seeds as `info@opti-fit.co.za` · the spec seed doesn't list it; the studio inbox is the obvious default
- 2026-10-14 · `/_styleguide` (+ `/app`, `/auth` shell previews) returns 404 in production and renders in local and testing · "local only" in docs/08; testing access lets a smoke test cover it
- 2026-10-14 · Published Filament assets (`public/{css,js,fonts}/filament`) are git-ignored · `composer install` regenerates them via `filament:upgrade`
- 2026-10-14 · Every customer layout loads `@livewireScripts` · it provides Alpine, which the sheet, toast, password toggle and pricing selector need on non-Livewire pages too
- 2026-10-14 · Session card shows "Full" only as the places badge, and "Booking closed"/"All booked" as a line under the details · a right-hand status column squeezed titles to three lines at 390px
- 2026-10-14 · CI runs on `ubuntu-26.04` with `actions/checkout@v7`, `actions/setup-node@v7` (Node 24 LTS) and `shivammathur/setup-php@v2` (2.40.0) · latest stable releases; v4 actions ran on deprecated Node 20, and `ubuntu-latest` was about to move to 26.04 anyway. Node 26 isn't LTS yet
- 2026-10-14 · Public footer has the venue address hard-coded · locations arrive in M3; the footer can read the active location then

## Ideas (out of scope for now)

- Online payments (e.g. PayFast/PayGate) for monthly fees
- Payment tracking per month (paid/unpaid) on memberships
- Waitlist for full classes
- Promotional free-trial class sign-ups
- Two-factor login (Fortify supports it) · overkill for now, revisit only if the owner asks
