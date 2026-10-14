# 05: Admin panel (Filament)

The panel lives at `/admin`. Use Filament's latest stable major that supports the chosen Laravel version.
Brand it: primary colour `#FF9500`, font Poppins, logo `optifit-logo-on-dark.png` in dark mode and
`optifit-logo-on-light-tagline.jpg` in light mode, favicon from the mark. Filament's built-in light/dark
mode is fine here. The panel must be usable on a phone, because the owner runs classes with a phone in hand.
Filament's responsive tables do this. Prefer simple table columns that stack well.

Access: `User::canAccessPanel()` returns true only for `role = admin` and not deactivated.

All actions that move credits or change bookings call the same `app/Actions/*` classes as the customer
side. Never write credit entries directly from Filament code.

## Navigation groups

**Today** (dashboard) · **Classes** (Sessions, Timetable templates, Locations) · **Members**
(Households, Membership requests, Memberships) · **Setup** (Packages, Admins, Settings) · **Reports**

## Dashboard

Widgets, in order:
1. **Today's classes:** each session today and tomorrow, with time, title, booked/capacity, and a
   "below minimum" warning. Clicking opens the roster.
2. **Needs attention:** count and link for pending membership requests, deletion requests, and
   memberships ending this month.
3. **This month:** active memberships, expected monthly income (sum of `monthly_price_cents` of active),
   bookings so far, attendance rate, credits granted / used / expired so far.

## Sessions (`ClassSessionResource`)

- **Table:** date, time, title, location, booked/capacity (+ guests), status. Filters: date range (default
  today → +14 days), status, location. Default sort is by start ascending. A "Past" tab is sorted descending.
- **Create/Edit form:** BR-7.1 fields. Editing the time triggers the email in BR-7.10 (confirm modal: "N households will be emailed").
- **Row actions:** Open roster · Duplicate to date (date picker; copies all fields, no bookings) · Cancel session (reason; confirm; BR-7.8).
- **Roster page** (the session view page, used at the venue):
  - Header: date/time, places left, cutoff time, min participants indicator.
  - **Bookings table:** member name, age, household, status, credit charged, health-notes icon
    (tooltip/modal shows the notes). Row actions: Mark attended · Mark no-show · Cancel (with a "return credit"
    toggle, default per BR-6.2) · Return credit (BR-6.1, visible only when eligible).
    Bulk action: "Mark selected attended".
  - **Header action "Add booking":** select a household (searchable) → member(s) from that household
    (all non-archived, not just covered) → "Charge 1 credit each" toggle (default on, shows the balance)
    → "Book over capacity" checkbox (required if full) → confirm. Uses BR-6.3/6.4.
  - **Guests table:** name, count, note. Add or remove (BR-9.3).

## Timetable templates (`ScheduleTemplateResource`)

- CRUD per BR-8.1. Table sorted by weekday and time.
- Header action **"Generate sessions"**: date range (default today → end of next month) and a template
  multi-select (default all active) → result notification "Created 34 sessions, skipped 6 existing".

## Locations (`LocationResource`)
Simple CRUD with the archive rule BR-9.2.

## Households (`HouseholdResource`)

- **Table:** household name, account holder, email, phone, members count, membership (package + status
  badge), current balance, flags (deletion requested, deactivated). Search by any name or email.
- **View page** with tabs:
  - **Overview:** account holder details, emergency contact, terms accepted (version/date), marketing opt-in.
  - **Members:** table with an edit modal (admins can edit members, including health notes) and archive.
  - **Credits:** current balance and the ledger table (all periods, newest first, filter by period). Header
    action **"Adjust credits"**: amount (± integer, not 0) and a required reason (BR-6.5).
  - **Bookings:** upcoming and past bookings across members, with the same row actions as the roster.
  - **Memberships:** history of all memberships.
- **Header actions:** Deactivate/Reactivate login (BR-1.8) · Send password reset email · Anonymise household
  (BR-9.6, typed confirmation).

## Membership requests and memberships (`MembershipResource`)

- **Tabs:** Requested (badge count) · Active · Paused · Ending this month · Cancelled/Declined.
- **Requested row actions:**
  - **Approve:** modal with `starts_period` (radio: this month / next month, default per BR-4.4),
    `monthly_credits`, `monthly_price_cents` (shown and edited in rand), `first_month_credits` (visible only for
    this month; prefilled per BR-4.5, with the suggested prorated price shown as helper text), and an admin note.
    For a change request (household already active), the starts-period is fixed to next month and the old
    membership's end is shown ("Current package ends 31 Oct").
  - **Decline:** reason (required).
- **Active/Paused row actions:** Pause · Resume · Cancel now · Edit (credits, price, covered members, admin note).

## Packages (`PackageResource`)

- Form: name, description, sessions per week, credits per member (helper: "Usually sessions per week × 4"),
  active toggle, sort order, and a **price tiers repeater** (member count, price in rand). Validation:
  a tier for 1 is required, member counts are unique, prices > 0.
- Table: name, credits/member, price for 1 member, number of tiers, active, memberships using it.
- Delete is visible only when it's allowed (BR-3.4). Otherwise there's an "Archive" toggle. The table is reorderable by `sort_order`.

## Admins (`UserResource` scoped to `role = admin`)
Create admin (name, email, password or "send invite/reset link"), deactivate. An admin can't deactivate
themselves, and there must always be at least one active admin.

## Settings (custom Filament page)
The BR-9.1 fields in one form.

## Reports (one custom page, month picker, default current month)

- Totals: bookings, attended, no-shows, late cancellations, cancellations, guest places.
- Credits: granted, used (bookings − refunds), adjusted, expired.
- Sessions table: date, title, capacity, occupied, fill %, attended.
- Memberships: active at month end per package, with income per package.
- "Export CSV" for the sessions table. That's the only export.
