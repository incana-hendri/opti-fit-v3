# 10: Milestones (build order)

Each milestone ends with a working, tested app. Plan each one before starting it: list files, migrations
and tests. Don't start a milestone until the previous one meets its definition of done (`09`).

## M0: Scaffold and brand foundation
**Build:**
- New Laravel app **in this directory**. This directory already contains `AGENTS.md`, `README.md`, `docs/`
  and `brand/`, so `laravel new`/`composer create-project` won't target it directly. Scaffold into a temp
  directory, then move everything in without overwriting these files. Merge `.gitignore`. Don't delete `docs/` or `brand/`.
- Sail (MariaDB 10.11 + Mailpit), Pest, Pint, Larastan, the GitHub `ci.yml`.
- Tailwind v4 with `brand/tokens.css` mapped into `@theme`, Poppins via `@fontsource/poppins`, and the processed logo/favicon assets (`08`).
- Fortify installed (views not built yet), Filament panel at `/admin` with brand colours/logo/font, `role` column, `optifit:make-admin` command.
- Base layouts (public, app with bottom tab bar, auth), the components list from `08`, and `/_styleguide` (local only).
- Settings model and seeder (BR-9.1).

**Accept when:** `sail up` → the landing placeholder renders in brand at 390/1280px; an admin can log into
`/admin`; CI is green; the styleguide shows all components.

## M1: Accounts and households
**Build:** registration/verify/login/reset views (`04`), BR-1.x, onboarding wizard (3 steps), `/family`,
`/account` (incl. deletion request), `customer` middleware, `HouseholdPolicy`, Filament HouseholdResource
(Overview + Members tabs, deactivate, send reset), A1/A4 emails.
**Accept when:** a new user can register, verify, finish onboarding and manage family members on a phone-sized
screen; isolation tests pass; the admin sees the household.

## M2: Packages, memberships and credits
**Build:** `packages`, `package_prices` (Filament PackageResource), the public `/pricing` page, `memberships` and the
request form, `/membership` page, MembershipResource with approve/decline/pause/resume/cancel/edit,
the `credit_entries` ledger + `CreditLedger`, the Credits tab with "Adjust credits", `optifit:monthly-credits`
plus its schedule, the `/home` credit card, and emails C3–C7, C13, A2, A3. Seed packages.
**Accept when:** request → approve (this month, prorated) → the balance shows on Home; the monthly job, run on
a simulated 1st, expires and grants correctly and idempotently; all BR-3/4/5/6.5 tests pass.

## M3: Timetable and booking
**Build:** locations, schedule templates + generate, class sessions (Filament) incl. roster, guests,
attendance, admin book/cancel/return credit, session cancel/reschedule; customer `/book` with the day strip and
booking sheet, `/bookings` with cancel; the public `/timetable` and landing timetable; emails C8–C12, A5.
**Accept when:** the full loop works on a phone: browse → book 2 members → see credits drop → cancel one before
the cutoff (credit back) → late-cancel the other (no credit) → admin returns it manually. All BR-7/8/6.1–6.4 tests
pass, including the concurrency test.

## M4: Dashboard, reports and polish
**Build:** the Filament dashboard widgets and Reports page (+ CSV), legal pages from Markdown (`docs/legal/README.md`),
`AnonymiseHousehold`, branded error pages, the landing page content, empty states, an accessibility pass
(keyboard, focus, contrast, 320px), and the security headers middleware.
**Accept when:** the security checklist in `07` is fully ticked; Lighthouse mobile on `/` and `/book` scores
≥ 90 for accessibility and best practices.

## M5: Import and launch readiness
**Build:** `optifit:import` (`11-data-import.md`) with `--dry-run`, `deploy.yml`, `docs/deploy.md`
(server setup, `.env`, cron, document root, backup cron, first deploy, rollback), and the production seeder path.
**Accept when:** importing the sample file in `11` into a fresh DB produces the expected households,
memberships and balances (a test asserts this), and a dry run prints a summary without writing. **Deploying is
done by a human** (see `AGENTS.md`).
