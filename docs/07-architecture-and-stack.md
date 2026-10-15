# 07: Architecture, stack and hosting

## Stack

| Concern | Choice |
|---|---|
| Language/runtime | PHP 8.4 (the cPanel account must be switched from 8.1 to 8.4 before the first deploy) |
| Framework | **Laravel**, latest stable major (13.x at time of writing) |
| Auth (customer) | **Laravel Fortify** (headless) + our own Blade views. Features: registration, email verification, password reset, profile/password update |
| Admin UI | **Filament**, latest stable major compatible with the Laravel version |
| Customer UI | Blade components + **Livewire** (already required by Filament) + Alpine, **Tailwind CSS v4** via Vite |
| Fonts | `@fontsource/poppins` (self-hosted, weights 400/500/600/700) |
| Database | **MariaDB 10.11** (production is 10.11) |
| Queue | `database` driver, processed by the scheduler (no long-running worker on cPanel) |
| Mail | SMTP (credentials in `.env`); Mailpit locally |
| Tests | **Pest** against MariaDB (not SQLite: generated columns and locking must behave like production) |
| Code style | Laravel Pint (default preset) |
| Static analysis | Larastan level 5. Keep it practical, don't fight it |
| Local dev | Laravel Sail with `mariadb` and `mailpit` services |

Allowed extra packages: the ones above only. Ask a human before adding another runtime dependency.

## Application structure

```
app/
  Actions/            # the only place credits/bookings/memberships change
    Bookings/   BookSession.php, CancelBooking.php, AdminBook.php, MarkAttendance.php, PlaceBooking.php (internal)
    Credits/    GrantMonthlyCredits.php, ExpireCredits.php, AdjustCredits.php, ReturnCredit.php
    Memberships/ RequestMembership.php, ApproveMembership.php, DeclineMembership.php,
                 CancelMembership.php, PauseMembership.php, ResumeMembership.php
    Sessions/   GenerateSessions.php, CancelSession.php, RescheduleSession.php
    Households/ AnonymiseHousehold.php
  Console/Commands/   MakeAdmin.php, RunMonthlyCredits.php, NotifyBelowMinimum.php, ImportData.php
  Filament/           # resources, pages (Dashboard, Reports, Settings), widgets (see 05)
  Http/Controllers/   # thin; public pages, legal pages (LegalController)
  Http/Middleware/    # EnsureCustomer.php, SecurityHeaders.php (global)
  Livewire/           # Onboarding, Home, Family, MembershipPage, RequestForm, BookPage (with the booking sheet), BookingsPage
  Mail/               # one Mailable per row in 06
  Models/
  Policies/
  Support/            Period.php (period helpers), Money.php (cents ↔ "R1 144" formatting), Cutoff.php,
                      MonthReport.php (the month's numbers, shared by the dashboard and Reports)
resources/views/      # layouts, components (x-button, x-card, x-badge, x-sheet…), pages, mail
resources/markdown/   # privacy.md, terms.md
```

Conventions:
- An Action is a plain class with one public `handle()` (or `__invoke`) method. It wraps itself in
  `DB::transaction` and locks the household (`Household::whereKey($id)->lockForUpdate()->first()`)
  **before** any read used for a decision. It throws a domain exception (e.g. `BookingException`) with a
  user-safe message on rule violations. Callers (Livewire/Filament) catch it and show the message.
- Lock order is always **household → class session** to avoid deadlocks.
- A `CreditLedger` helper (static methods or a small class) provides `balance(Household, ?period)`
  and `write(...)`. It's the only code that inserts `credit_entries`.
- Dates: set `config('app.timezone') = 'Africa/Johannesburg'` and the MariaDB connection timezone `+02:00`.
  The `Period` helper gives `current()`, `next()`, `fromDate()`, `lastDay()`.
- Authorisation: a `HouseholdPolicy`. Customer routes resolve the household from `auth()->user()->household`,
  never from the URL. Child records (member, booking) are looked up via `$household->members()->findOrFail()`.
- Customer routes use the `auth`, `verified` and `customer` middlewares (`customer` = role customer, has a household, not deactivated;
  redirects to onboarding when there's no household).

## Scheduler (`routes/console.php`)

| When | Command |
|---|---|
| daily 00:05 | `optifit:monthly-credits` (BR-5.5; idempotent) |
| daily 06:00 | `optifit:below-minimum` (A5: the admin email, only when a class today is below its minimum) |
| every minute | `queue:work --stop-when-empty --max-time=50` (withoutOverlapping) |
| daily 03:00 | `queue:prune-failed --hours=168`, `auth:clear-resets` |

The cPanel cron is a single line: `* * * * * cd ~/optifit && php artisan schedule:run >> /dev/null 2>&1`

## Security checklist (must all hold before launch)

- CSRF on all forms (Laravel default). No state-changing GET routes.
- Login and password-reset throttling (Fortify). Session ID regenerated on login (Laravel default).
- `APP_DEBUG=false`, `APP_ENV=production`, HTTPS forced (`URL::forceScheme('https')` in production), `SESSION_SECURE_COOKIE=true`.
- Only `public/` is web-accessible. The app root, `.env`, `storage/` and `database/` are outside the document root.
- Policy tests prove customer A can't read or change customer B's members, bookings or memberships (see `09`).
- `health_notes` use the `encrypted` cast. Don't log request bodies.
- Admin panel: role check, plus Filament's own login throttle.
- Security headers via a small global middleware (`SecurityHeaders`, so the admin panel and error pages carry them too): `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: strict-origin-when-cross-origin`. Skip CSP; it's not worth the friction for this project.
- No analytics or third-party scripts, so no cookie banner is needed (only essential session cookies).
  The privacy policy says so.

## Hosting and deployment (cPanel shared hosting)

Layout on the server (home dir `~`):
```
~/optifit/            # the whole Laravel app (git-free build artefact)
~/optifit/public      # set as the domain's document root in cPanel
                      #   (if the root can't change: replace ~/public_html with a symlink to ~/optifit/public)
~/optifit/.env        # created once by hand on the server, never committed or uploaded by CI
```

**GitHub Actions**
- `ci.yml` (every push/PR): MariaDB 10.11 service, `composer install`, `npm ci && npm run build`, Pint check, Larastan, `composer test`.
- `deploy.yml` (manual `workflow_dispatch` only, never on push, with a human pressing the button):
  build (`composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`) → tar the app without
  `node_modules`, `tests`, `.git`, `.env` → scp to the server → extract to `~/optifit-releases/{sha}` →
  symlink `~/optifit` → `{sha}` (keep the last 3 releases; `storage/` is a shared directory symlinked into each release) →
  run `php artisan migrate --force`, `optimize`, `storage:link`, `queue:restart`.
- Secrets: `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`. Nothing else, since the `.env` lives on the server.
- Document the exact steps in `docs/deploy.md` during Milestone 5 (the only new doc allowed).

Backups: rely on cPanel's daily backups, and also schedule a nightly `mysqldump` of the app DB to `~/backups`
(keep 14 days) using a tiny artisan command or a cron line documented in `docs/deploy.md`.

## Environment (.env.example)

```
APP_NAME=OptiFit
APP_ENV=local
APP_URL=http://localhost
APP_TIMEZONE=Africa/Johannesburg
DB_CONNECTION=mariadb
DB_HOST=mariadb
DB_DATABASE=optifit
DB_USERNAME=sail
DB_PASSWORD=password
QUEUE_CONNECTION=database
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS=info@opti-fit.co.za
MAIL_FROM_NAME=OptiFit
```
