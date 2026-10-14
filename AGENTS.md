# AGENTS.md: working rules for this repository

This repository is **OptiFit**, the booking and credits app for one small family functional-fitness
studio in Durbanville, South Africa. There are about 30 customer families and one or two admins.
**Build a small, solid app for that studio. Do not build a platform.**

These rules apply to everyone who changes this repository, human or AI. They exist so that someone who
has never seen the code can open it in two years, understand it in an afternoon, and change it safely.
`CLAUDE.md` loads this file. Keep all rules here, not there.

## Start of every task

1. Read `README.md`, then the docs in `docs/` that touch your task, and skim `docs/decisions-log.md`
   (it records choices you must stay consistent with). `docs/10-milestones.md` gives the build order.
2. **Plan first.** Write a short plan listing the files you'll touch, the migrations, and the tests, then implement.
   The spec is complete on purpose. Don't stop to ask clarifying questions. When something is unclear,
   choose the simplest reading that fits `docs/02-business-rules.md`, note it in
   `docs/decisions-log.md` (one line: date, decision, reason), and keep going.
3. Work one milestone at a time. A milestone is done when it meets the definition of done below.
4. Read the code around your change before writing it, and match it. Consistency beats personal preference.

## Ask the human only for

- deploying to production, or anything else that touches the live server or the live database
- adding a paid or third-party service, or any runtime dependency, that isn't listed in `docs/07-architecture-and-stack.md`
- changing a rule in `docs/02-business-rules.md`. Don't quietly "improve" the rules.
- pushing to the remote, merging into `main`, or rewriting published history

## Scope ceiling: do NOT build these

Online payments or a payment gateway · invoices/billing documents · waitlists · multiple studios or
tenants · instructor or staff scheduling, payroll · CRM or marketing campaigns · SMS/WhatsApp ·
push notifications · native or PWA-offline apps · public API or webhooks · fine-grained roles or
permission matrices (there are exactly two roles: `customer`, `admin`) · consent-versioning
systems · outbox/event-sourcing/CQRS · separate audit/security-event tables · feature flags ·
multi-language (English only) · a light/dark theme toggle on the customer site · price-band or
proration engines beyond the one formula in the rules · analytics or tracking scripts · two-factor login.

If a feature isn't described in `docs/`, it's out of scope. Note it under "Ideas" in
`docs/decisions-log.md` and move on.

## Architecture

- **Plain Laravel.** Use Eloquent models, form requests, policies, and a few `app/Actions/*` classes
  for operations that move credits or change bookings, because those must be atomic. No repositories,
  DTO layers, service containers per module, or "Domain" folders.
- **Credits are a ledger.** A balance is always `SUM(credit_entries.amount)` for the period. Never
  store a balance column. Every credit change is a new row, and rows are never updated or deleted.
  Only `CreditLedger` inserts `credit_entries`.
- **Anything that moves credits or changes bookings or memberships goes through an Action**, whether it's
  called from Livewire, Filament, a command or a test. Actions run in a DB transaction that locks the
  household row first and then the class session row (`lockForUpdate`, BR-7.6). They throw a domain
  exception with a customer-safe message when a rule fails.
- **Money is integer cents (ZAR).** Time zone is `Africa/Johannesburg` everywhere. South Africa has no
  daylight saving, so store local datetimes. Use the `Period`, `Money` and `Cutoff` helpers in `app/Support`.
  Don't redo that arithmetic inline.
- **The admin UI is Filament.** Don't hand-build admin CRUD pages.
- **Customer UI is Blade + Livewire + Tailwind**, mobile-first, using the brand in `docs/08-brand-and-design.md`.

Where code goes:

| Kind of code | Place |
|---|---|
| Rule-enforcing operations (credits, bookings, memberships, sessions, anonymising) | `app/Actions/{Area}/VerbNoun.php`, one public `handle()` |
| Status and type sets (`role`, `gender`, statuses) | backed enums in `app/Enums`, cast on the model |
| Small pure helpers (periods, money, cutoff times) | `app/Support` |
| Request validation | form requests, or Livewire `#[Validate]`/`rules()` on the component |
| Who may do what | policies (`app/Policies`), plus `canAccessPanel()` for the admin panel |
| Reusable UI | anonymous Blade components in `resources/views/components` (see `/_styleguide`) |
| Emails | one Mailable per row in `docs/06-emails.md`, Markdown views in `resources/views/mail` |

## Code standards

**PHP**
- Laravel Pint (default preset) and Larastan level 5 must be clean. Never add `@phpstan-ignore`,
  baselines or `assert()` to silence a finding. Fix the cause. Precise PHPDoc (`@property`, generics,
  array shapes) is fine when it states the real type.
- Type every parameter, return and property. Use constructor promotion and `readonly` where it fits.
- Name things after the business: `ApproveMembership`, `$household`, `$cutoffAt`, not `$data`, `$item` or `Manager`.
  Use the glossary words in `docs/01-product-overview.md` (household, member, period, session, credit entry).
- Keep methods short and flat: return early, and don't nest more than two levels. If a method needs a
  comment to explain *what* it does, split it or rename it.
- Use enums and constants for magic strings and numbers. Read settings through `Settings::current()`, never hard-coded.
- Queries: eager-load relations you loop over (no N+1), scope customer queries through the household
  (BR-2.7), and use `lockForUpdate()` only inside Actions.
- No dead code, commented-out code or `TODO`s left at the end of a milestone. Delete unused scaffolding.

**Database**
- Migrations match `docs/03-data-model.md` exactly: column names, types, nullability, indexes, foreign keys.
- Until the first production deploy, fix the original migration. After it, never edit a migration.
  Add a new one.
- Every model has a factory whose default state is valid. Add named states for common variants
  (`admin()`, `deactivated()`, …).

**Blade, Livewire and CSS**
- Build screens from the components in `resources/views/components`. If you need a new pattern twice,
  make it a component and add it to `/_styleguide`.
- Colours, radii and fonts come from the brand tokens (`bg-surface`, `text-brand`, `rounded-card`, …).
  No raw hex values or inline styles in views (computed widths like progress bars are the exception).
- Accessibility is part of done: labels on inputs, 44px tap targets, visible focus, text with every
  colour signal, works at 320px with no horizontal scroll (docs/04).
- Livewire components stay thin: validate, call an Action, catch its exception, show the message.

**Tests**
- Pest feature tests against MariaDB, never SQLite. Every numbered business rule has a test named
  after it, e.g. `it('refunds the credit when cancelled before cutoff (BR-7.7)')`. See `docs/09-testing-and-acceptance.md`.
- Every bug fix starts with a failing test that reproduces it.
- Freeze time (`$this->travelTo()`) for anything date- or cutoff-related. Fake mail and queues when asserting them.
- Don't chase coverage percentages. Don't write browser tests for admin CRUD.

## Documentation

Documentation lives in a few fixed places. Keep each one true, and don't add new ones.

| What | Where |
|---|---|
| How the product must behave | `docs/02-business-rules.md` (changes need the human) |
| Tables and columns | `docs/03-data-model.md` |
| Screens, admin, emails, stack, brand, tests, milestones | `docs/04`–`docs/10` |
| Every implementation choice the spec didn't settle, and every deliberate deviation | `docs/decisions-log.md`, one line each |
| Out-of-scope ideas | "Ideas" in `docs/decisions-log.md` |
| How to set up, run and test the app locally | `README.md` (must stay copy-paste runnable) |
| Server setup, deploy, rollback, backups (from Milestone 5) | `docs/deploy.md` |
| Why a non-obvious line of code exists | a short code comment citing the rule, e.g. `// BR-5.4` |

- When the implementation deliberately differs from a spec doc, fix that doc **in the same change** and
  log it in the decisions log. Docs and code never disagree.
- When you add a command, environment variable, service or setup step, update `README.md` (and
  `.env.example`) in the same change.
- Each Action class and console command starts with a one-line comment naming the rules it implements.
- Comments explain *why*, never *what*. Don't write PHPDoc that only repeats the signature.
- Don't create other planning documents, ADRs, status reports or per-feature READMEs.

## Versions and dependencies

- Use the **latest stable release of everything** from the day it's added: Composer and npm packages,
  GitHub Actions, CI runner images, and Docker images. For Node.js that means the newest LTS line.
  The one deliberate exception is MariaDB, which stays on 10.11 to match production.
- Pin the CI runner to a named image (`ubuntu-26.04`), never `-latest`, so runner changes are a visible
  commit. Use the action's current major tag (`actions/checkout@v7`).
- When you touch dependencies, check for newer releases first (`sail composer outdated --direct`,
  `sail npm outdated`, and each action's releases page). Log any major upgrade in the decisions log.
- CI must finish clean: no deprecation warnings or notices in the run annotations. A passing run with
  warnings isn't done.

## Commands

There's no local PHP. Everything runs in Sail (`./vendor/bin/sail …`, aliased as `sail` below).

| Purpose | Command |
|---|---|
| Start / stop | `sail up -d` / `sail stop` |
| Tests | `sail composer test` |
| Code style (check / fix) | `sail composer lint` / `sail php vendor/bin/pint` |
| Static analysis | `sail composer analyse` |
| Assets | `sail npm run dev` / `sail npm run build` |
| Fresh database | `sail artisan migrate:fresh --seed` |

## Definition of done (every milestone and every change)

- [ ] The milestone's acceptance criteria in `docs/10-milestones.md` are demonstrably met.
- [ ] `sail composer lint`, `sail composer analyse` and `sail composer test` all pass, and `sail npm run build` succeeds.
- [ ] CI is green with no warnings or notices in its annotations.
- [ ] Tests exist for every business rule touched.
- [ ] Customer screens touched are checked at 390px and 1280px.
- [ ] No TODOs, dead code or debug output left behind.
- [ ] Docs updated as described above, with decisions logged.
- [ ] `DemoSeeder` (from M2 onwards) still runs and shows the new feature.

## Git

- `main` always passes CI. Work on a branch per milestone (`m1-accounts`, `m2-memberships`, …), or per
  fix (`fix/short-description`).
- Commit in small, logical steps with an imperative subject line that says what changed and why
  (`Add booking cutoff check (BR-7.2)`). One concern per commit, and never mix formatting with behaviour.
- Commit the generated brand images. Don't commit `vendor/`, `node_modules/`, `public/build/`,
  published Filament assets or `.env` (they're in `.gitignore`).
