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
- 2026-10-14 · Onboarding creates the household at step 1 and resumes at the first unfinished step. "Onboarded" means at least one active member; the `customer` middleware sends anyone else to `/onboarding` · progress saved per step (docs/04) without an extra column; archiving everyone sends the customer back to add a member
- 2026-10-14 · Onboarding step 1 asks the account holder's gender when they train (docs/04 updated) · BR-2.3 requires gender on a member and `users` has no gender column
- 2026-10-14 · In M1 onboarding "Finish" went to `/home`; since M2 it goes to `/membership/request` with the toast "Last step: choose your package" (docs/04) · the request form now exists
- 2026-10-15 · `Member::canBeDeleted()` is false for a member that any membership (active, ended or requested) covers or any booking refers to, so they can only be archived, and the /family menu and onboarding hide Delete and Remove for them (BR-2.5; M3 added bookings to the check) · deleting one hit the `membership_member` foreign key and gave the customer a 500
- 2026-10-14 · Archive and delete confirmations use the brand sheet, not `wire:confirm` · native browser dialogs can't be styled and block automated browser checks
- 2026-10-14 · The admin household resource has list and view pages only · customers create and edit their own household; docs/05 lists no admin create or edit
- 2026-10-14 · Reply-To is added to every outgoing email by one `MessageSending` listener using `Settings::current()->contact_email` · covers Fortify notifications and Mailables in one place (docs/06)
- 2026-10-14 · Mail theme keeps only the overridden pieces (`header`, `message`, `themes/optifit.css`) in `resources/views/vendor/mail` · the rest fall back to Laravel, so upgrades bring their fixes
- 2026-10-14 · Livewire's generator is set to class-based components without emoji names (`config/livewire.php`) · docs/07 puts components in `app/Livewire`
- 2026-10-14 · Models declare cast types (`Carbon`, enums) with `@property` · Larastan doesn't apply types from the `casts()` method here, and the PHPDoc states the real type
- 2026-10-14 · The admin households table shows household (with account holder underneath) and flags on phones; email, members and phone appear from wider screens · docs/05 asks for tables that work on the owner's phone
- 2026-10-15 · The M3 tables (`locations`, `schedule_templates`, `class_sessions`, `bookings`, `guest_entries`) have migration dates just before `credit_entries`, so the original `credit_entries` migration declares the `booking_id` foreign key directly (it was a plain column in M2) · AGENTS.md says to edit original migrations until the first production deploy, and the order lets the FK be created with the table. Existing dev databases need `migrate:fresh`
- 2026-10-14 · `CreditLedger` lives in `app/Support` as a final class with static `balance()` and `write()` · docs/07 allows static methods or a small class; it is the only code that inserts `credit_entries`, and `write()` refuses to run outside a transaction
- 2026-10-14 · Domain failures are `MembershipException` and `CreditException` (both `RuntimeException`) · docs/07 names `BookingException`-style exceptions per area; two tiny classes keep customer-safe messages separate from bugs
- 2026-10-14 · A customer can't cancel (or undo a cancellation) while a package change is scheduled for next month · the old membership's `ends_period` is what hands over to the new one, so changing it would leave a gap or an overlap (BR-4.1, 4.7); the page explains why and asks them to contact the studio
- 2026-10-14 · A customer can cancel only an `active` membership, not a `paused` one · BR-4.8 says "active"; the page tells paused customers to contact the studio
- 2026-10-14 · Approving a request is refused while the household already has a package starting next month · it would overlap (BR-4.1); the admin cancels the scheduled one first
- 2026-10-15 · Memberships have a `grants_from_period` column (the user approved the new column on 2026-10-15; docs/03 updated, added to the original migration): the first period the monthly job may grant. Approval sets it to `starts_period` for a next-period start and to the next period for a current-period start (the approval itself settles this month, even with 0 credits); resuming sets it to `max(existing, next period)` and never touches `starts_period` · the job used to treat "no grant row for this period" as "a grant is due", so a 0-credit approval got a full grant the next morning, and the earlier fix of rewriting `starts_period` on resume falsified "Active since", dropped the membership out of effect for the rest of the month (BR-4.10) and made an ending membership look like a scheduled one
- 2026-10-14 · Cancel now (admin) sets `ends_period` to the current period only if the membership has already started; a membership that never started keeps `ends_period` null · nothing was ever granted for it
- 2026-10-14 · "Used" on the Home credit card is bookings less refunds this period, so 0 until M3 adds bookings · there is nothing else that uses credits
- 2026-10-14 · The "Book your first class" (C4) and "Book now" (C7) email buttons link to `/home` until M3 adds `/book` · the target doesn't exist yet
- 2026-10-14 · The request form (`/membership/request`) has an "I'll do this later" link to `/home` · docs/04 says the customer can skip after onboarding, and the form is where onboarding now ends
- 2026-10-14 · The pricing page's "5+" shows the price of the top tier at or below 5 members and the credits for 5 members with a "+" · BR-3.6 asks for a 1…5+ selector; the top tier already means "or more"
- 2026-10-14 · Pricing and request pages show "≈ R{price ÷ credits} per class" rounded to the whole rand · docs/04 gives no rounding
- 2026-10-14 · `PackageFactory` creates standard price tiers by default (`pricedAt()` replaces them) · a package without a tier for 1 member is invalid (BR-3.2)
- 2026-10-14 · The membership list is a Filament table with row actions only (no create, edit or view page); approve, decline, pause, resume, cancel now and edit are modals calling the Actions · customers create memberships, and docs/05 lists only these actions
- 2026-10-14 · The scheduler registers `optifit:monthly-credits`, the queue worker and the 03:00 housekeeping now; the 06:00 below-minimum email (A5) arrives in M3 · A5 needs sessions
- 2026-10-14 · `DemoSeeder` writes its data with factories and `CreditLedger` rather than the actions, and is a no-op when `admin@optifit.test` exists · running the actions would queue emails for fake customers
- 2026-10-15 · Row actions on the admin Packages and Memberships tables sit in one menu per row · separate buttons ran off the edge at 390px and the owner approves requests from a phone (docs/05)
- 2026-10-15 · `DemoSeeder` dates last month's ledger rows last month (it fakes the clock while writing them) · the history on `/membership` and the Credits tab then reads like real data
- 2026-10-15 · A request is refused up front while a package change is already scheduled, and the request form says "Your package already changes on 1 {Month}" · approval always refuses it (BR-4.1), so the customer would otherwise wait for nothing
- 2026-10-15 · New `x-status-card` component (title, text, optional action) for Home's "no package", "request pending" and "package starts" cards, listed in docs/08 and `/_styleguide` · the card was repeated three times (AGENTS.md: a pattern needed twice becomes a component)

## Ideas (out of scope for now)

- Online payments (e.g. PayFast/PayGate) for monthly fees
- Payment tracking per month (paid/unpaid) on memberships
- Waitlist for full classes
- Promotional free-trial class sign-ups
- Two-factor login (Fortify supports it) · overkill for now, revisit only if the owner asks
- Restore (un-archive) a family member · BR-2.5 only describes archiving; today an admin would add the member again
