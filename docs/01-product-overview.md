# 01: Product overview

## The studio

OptiFit runs short (45-minute) functional-fitness classes for families: parents, spouses and
children train together. There is one venue today (Gene Louw Activity Centre, Durbanville). Classes
run weekday early mornings (around 05:00–06:30) and weekday afternoons (around 14:30–16:00). A
class holds 10–15 people. The studio has about 30 customer accounts, 17 families, and fewer than
10 active memberships at any time. A busy month is a few hundred bookings.

The business is small and the owner runs it personally. The app must be **easy for the owner to
operate from a phone** and **impossible to get wrong on credits**.

## Users

| Role | Who | How they sign in |
|---|---|---|
| Customer (account holder) | An adult (18+) who manages a household. They may train themselves, and they book for their family members. | `/login` (email + password) |
| Family member | Spouse, child, etc. Has **no login**. Exists as a profile under the household. | n/a |
| Admin | Studio owner or trainer. All admins have equal rights. | `/admin` (Filament) |
| Visitor | Not signed in. Can see the landing page, packages and prices, the timetable, and the legal pages. | n/a |

## What the app does (in scope)

1. **Accounts:** register, verify email, log in, reset password, edit profile, request deletion.
2. **Households:** the account holder adds family members (name, date of birth, gender, optional
   health notes) and one emergency contact.
3. **Packages:** the admin defines any number of packages (e.g. "1× per week", "2× per week", "3× per
   week"). Each has monthly credits per member and a price per family size. Admins can add, edit,
   archive and delete them.
4. **Memberships:** a household requests a package for chosen members. The admin approves it, with
   payment handled outside the app (EFT/cash). Credits are granted monthly and expire at month end.
5. **Timetable:** the admin keeps weekly class templates and generates class sessions from them. One-off
   sessions can also be added. Sessions can be edited or cancelled.
6. **Booking:** customers book one or more covered family members into a session at one credit each,
   and can cancel. Cutoff rules decide whether a cancelled credit comes back.
7. **Studio operations:** the admin sees rosters, books on a customer's behalf, adds walk-in guests,
   marks attendance, refunds credits manually, and adjusts credits.
8. **Emails:** transactional only (see `06-emails.md`).
9. **Light reporting:** bookings, attendance, credit flow, fill rate and active memberships per month.
10. **Data import:** a one-off import of existing customers, families, packages, memberships and
    balances from a JSON file.

## Out of scope

See the "do NOT build" list in `AGENTS.md`. In particular there are **no online payments**. The admin
records payment out of band and approves memberships. Promotional "free trial" sign-ups aren't a
feature: the admin grants credits manually instead.

## Settled decisions (don't reopen these)

| # | Decision |
|---|---|
| D1 | **No weekly booking limit.** A household has one pooled monthly balance. Any covered member can use any of it. "2× per week" is a package name and guide, not an enforced cap. |
| D2 | **Booking cutoff = 8 hours before class start** (a studio-wide setting, default 8). A session may override it with its own hours. |
| D3 | **Cancelling before the cutoff refunds the credit automatically. Cancelling after the cutoff doesn't** (the spot is freed, the credit is spent). Admins can hand a credit back manually for any booking. |
| D4 | **Packages are fully admin-managed** (create, edit, archive, delete). Seed data reproduces the studio's current 1×/2×/3× per-week packages and prices (see `03-data-model.md` § Seed data). |
| D5 | Monthly credits for a membership = `package.credits_per_member × number of covered members`. Price = the package's price tier for that member count. Both are **copied onto the membership at approval** and can be edited by the admin afterwards. |
| D6 | Credits **expire at the end of the calendar month**. Classes can only be booked in the **current calendar month** (otherwise leftover credits would roll over). |
| D7 | No-shows keep the credit spent and there's no penalty. |
| D8 | Payments are offline. The app has no payment records, invoices or reminders. The admin can write a free-text note on a membership (e.g. EFT reference). |
| D9 | One login per household (the account holder). Family members have no logins. |
| D10 | Stack: Laravel + Filament (admin) + Blade/Livewire/Tailwind (customer) on MariaDB, hosted on cPanel. See `07`. |
| D11 | Brand carries over exactly: logo files, orange `#FF9500` on night `#0D0918`, Poppins font. UX is redesigned. See `08`. |
| D12 | English only, ZAR currency, `Africa/Johannesburg` time zone, dates shown as `Mon 3 Nov`, times as `05:00`. |

## Glossary

- **Household:** an account holder and their family members. Owns the credit balance.
- **Member:** a person who can attend classes (the account holder may also be a member).
- **Covered member:** a member included in the household's active membership. Only covered members can be booked by the customer.
- **Package:** a product the studio sells (e.g. "2× per week").
- **Membership:** a household's subscription to one package for a set of covered members.
- **Period:** a calendar month, written `YYYY-MM`. Credits belong to a period.
- **Credit entry:** one immutable ledger row (+ or −).
- **Session:** one scheduled class occurrence (date, time, capacity).
- **Schedule template:** a weekly recurring class definition used to generate sessions.
- **Guest entry:** a walk-in or group the admin adds to a session. It takes capacity but costs no credits.
- **Cutoff:** the moment (`session start − cutoff hours`) after which customers can't book and cancellations aren't refunded.
