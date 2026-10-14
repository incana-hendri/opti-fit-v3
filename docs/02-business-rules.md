# 02: Business rules

Every rule has an ID (`BR-x.y`). Tests reference these IDs (see `09-testing-and-acceptance.md`).
"Now" always means the current time in `Africa/Johannesburg`.

## 1. Accounts

- **BR-1.1** Registration needs first name, last name, email, mobile phone, password (min 8 chars,
  confirmed), date of birth, and acceptance of the Terms and Privacy Policy. Marketing opt-in is optional
  and unticked by default.
- **BR-1.2** The account holder must be 18 or older at registration.
- **BR-1.3** The email must be verified before the customer can use anything beyond the verify-notice page.
- **BR-1.4** Store the accepted terms version (setting `terms_version`) and the timestamp on the user.
- **BR-1.5** Login is throttled (5 attempts per minute per email+IP; Fortify default).
- **BR-1.6** A customer can request account deletion. This notifies the admins. Deletion is done by an
  admin using "Anonymise household" (BR-9.6), not automatically.
- **BR-1.7** Admins are users with `role = admin`. Only admins can reach `/admin`. Customers who visit
  `/admin` get a 403. Admins who log in at `/login` are redirected to `/admin`.
- **BR-1.8** An admin can deactivate a customer user, which blocks login. Existing bookings remain.

## 2. Households and members

- **BR-2.1** Each customer user owns exactly one household, created during onboarding.
- **BR-2.2** During onboarding the account holder chooses whether they train too. If yes, a member
  record for them is created from their own details and flagged `is_account_holder`.
- **BR-2.3** A member needs first name, last name, date of birth and gender (`female`, `male`, `other`).
  Health notes (injuries, conditions) are optional free text, stored encrypted, and visible to admins.
- **BR-2.4** No hard limit on members per household.
- **BR-2.5** A member with any bookings can't be deleted, only archived (`archived_at`). Archived members
  can't be booked and don't count as covered. A member with no bookings can be deleted.
- **BR-2.6** A household has one emergency contact (name + phone), required before requesting a package.
- **BR-2.7** A customer can only see and change their own household's data. Enforce this with policies
  and scoped queries. Never trust IDs from the request.

## 3. Packages and pricing

- **BR-3.1** A package has: name, short description, `sessions_per_week` (display only), `credits_per_member`
  (monthly credits per covered member; default suggestion `sessions_per_week × 4`), `is_active`, `sort_order`.
- **BR-3.2** A package has one or more **price tiers**: `(member_count, price_cents)`. A tier for
  `member_count = 1` is required. The price for N members is the tier with the **largest
  `member_count ≤ N`**, so the highest tier acts as "N or more".
- **BR-3.3** Inactive (archived) packages are hidden from the public pricing page and from new requests.
  Existing memberships on them continue unchanged.
- **BR-3.4** A package can be **deleted** only if no membership has ever referenced it. Otherwise
  the admin can only archive it. The UI explains why.
- **BR-3.5** Changing a package's credits or prices never changes existing memberships (they hold copies, BR-4.4).
- **BR-3.6** The public pricing page and the request form show prices computed from the tiers, plus a
  family-size selector (1…5+) that updates the displayed prices.

## 4. Memberships

Statuses: `requested`, `active`, `paused`, `cancelled`, `declined`.

- **BR-4.1** For any period, a household has at most **one membership in effect**: status `active` or `paused`
  with `starts_period ≤ period` and (`ends_period` null or `≥ period`). During a package change, two
  `active` rows exist briefly (the old one ending this period, the new one starting next), but their periods
  never overlap. At most **one** membership may be `requested` at a time.
- **BR-4.2** To request, the customer picks an active package and one or more non-archived members to cover,
  with an optional note. The form shows the monthly credits (`credits_per_member × members`) and
  the price (BR-3.2). Requires the emergency contact (BR-2.6).
- **BR-4.3** The customer can withdraw their own `requested` membership. This deletes it.
- **BR-4.4** **Approval (admin):** the admin sets
  - `starts_period`: the current period or the next one (default: current if today ≤ the 20th, else next),
  - `monthly_credits` and `monthly_price_cents`: prefilled from BR-4.2 and editable,
  - for a **current-period start**: `first_month_credits`, prefilled with the prorated value (BR-4.5)
    and editable (0 allowed),
  - an optional admin note (e.g. payment reference).

  On approval: status → `active`, `approved_at`, `approved_by`. If the start is the current period,
  immediately write a `grant` entry of `first_month_credits` for the current period (idempotency key
  `grant:{membership_id}:{period}`). For a next-period start, the monthly job grants on the 1st.
  The customer gets an email.
- **BR-4.5** **Proration** (suggested first-month credits) =
  `round_half_up(monthly_credits × remaining_days / days_in_month)`, where `remaining_days` counts
  today through month end inclusive. Minimum 1, maximum `monthly_credits`. The approval form also
  shows a suggested prorated price on the same ratio, rounded to the rand, for the admin's information only.
- **BR-4.6** **Decline (admin):** status → `declined` with a required reason. The customer gets an email.
- **BR-4.7** **Change of package or members:** while active, the customer can submit a change request.
  This is a new `requested` membership (allowed by BR-4.1). When the admin approves it, it starts at the
  **next** period: the old membership gets `ends_period = current period` and the new one
  `starts_period = next period`. If the admin wants the change to apply this month, they make a manual
  credit adjustment (BR-6.5). No automatic mid-month swaps.
- **BR-4.8** **Customer cancellation:** the customer can cancel an active membership. It sets
  `ends_period = current period`. Credits already granted stay usable until month end. The monthly
  job sets the status to `cancelled` when that period ends. The customer can undo it before month end
  (clears `ends_period`). Admins get an email.
- **BR-4.9** **Admin actions on a membership:** pause (no grants from the next period; status `paused`),
  resume (status `active`; grants resume on the next 1st), cancel now (status `cancelled`, credits
  already granted remain until month end), and edit `monthly_credits`, `monthly_price_cents` and covered
  members (the changes apply from the next grant).
- **BR-4.10** Only members covered by the household's membership **in effect for the current period** (BR-4.1)
  with status `active` can be booked by the customer. If a covered member is archived, they stop being bookable.

## 5. Credits (ledger)

- **BR-5.1** Credits belong to a household and a period. The balance for a period is
  `SUM(amount)` of that household's `credit_entries` for that period. There is no stored balance.
- **BR-5.2** Entry types: `grant` (+), `booking` (−1 per booked person), `refund` (+1), `adjustment`
  (±, admin, reason required), `expiry` (−remaining, at month end).
- **BR-5.3** Entries are immutable. No updates and no deletes, in code or in the admin UI.
- **BR-5.4** The **current balance** is the balance for the current period. It must never go below
  zero. Bookings and negative adjustments are checked inside the locked transaction (BR-7.6).
- **BR-5.5** **Monthly job** (runs daily at 00:05 and is idempotent, so a missed day self-heals):
  1. For each household with a positive balance in any period **before** the current period, write an
     `expiry` entry of `−balance` for that period (key `expiry:{household_id}:{period}`).
  2. Memberships with `ends_period` < current period → status `cancelled`.
  3. For each `active` membership with `starts_period ≤ current` and (`ends_period` null or `≥ current`),
     write a `grant` of `monthly_credits` for the current period (key `grant:{membership_id}:{period}`).
     Send the "credits ready" email the first time each grant is written.
- **BR-5.6** Refunds go to the **current** period. Automatic refunds happen before the class starts,
  and classes are always in the current month (BR-7.2), so this is the booking's own period.
- **BR-5.7** Each booking can be refunded **at most once**, whether automatically or manually (key `refund:{booking_id}`).
- **BR-5.8** The customer sees their current balance, the date it expires, and a history of entries
  for the current and two previous periods, each with a human description.

## 6. Admin credit tools

- **BR-6.1** Manual refund: on any `cancelled` (late), `no_show` or `attended` booking that was
  charged and not yet refunded, the admin can "Return credit". This writes a `refund` +1 to the current period.
- **BR-6.2** When an admin cancels a booking, the "return credit" option defaults to **on** before the
  cutoff and **off** after it. The admin can change it.
- **BR-6.3** When an admin books for a customer, "charge 1 credit" defaults to **on**. If it's off, the
  booking is free (`credit_charged = false`). Charging still requires enough balance.
- **BR-6.4** Admin bookings ignore the cutoff and the current-month rule. If a session is full, the
  admin must tick "Book over capacity". This is allowed and doesn't change the session's capacity.
- **BR-6.5** Adjustment: the admin adds or removes N credits (current period) with a required reason. A negative
  adjustment can't take the balance below zero.

## 7. Sessions and booking

- **BR-7.1** A session has a title, location, start datetime, duration (minutes, default 45), capacity,
  optional minimum participants (shown to admins only), optional cutoff-hours override, optional
  public description, and status `scheduled` / `cancelled`.
- **BR-7.2** A customer can book a session only if **all** of these hold:
  1. the session is `scheduled`;
  2. now < `starts_at − cutoff_hours` (**cutoff**; `cutoff_hours` = session override ?? setting `default_cutoff_hours`, default **8**);
  3. the session's start falls in the **current calendar month** and within the next `booking_horizon_days` (setting, default 14);
  4. each selected member is covered (BR-4.10) and not already actively booked on this session;
  5. there is capacity for every selected member (BR-7.4);
  6. the current balance ≥ number of selected members.
- **BR-7.3** A customer may select **several members in one booking action**. It's all-or-nothing:
  each member gets one booking row and one `booking` −1 entry.
- **BR-7.4** Occupied places = bookings with status `booked`, `attended` or `no_show`, plus the sum of
  guest entries' `participant_count`. Places left = capacity − occupied (it can be negative after an over-capacity admin booking).
- **BR-7.5** A member can't have two active bookings on the same session. This is enforced by a unique index (see data model).
- **BR-7.6** Booking runs in one transaction: lock the household row, then the session row; re-check
  BR-7.2 items 1, 4, 5 and 6; insert the bookings and ledger entries; commit. Two parallel requests for the
  last place or the last credit must result in exactly one success.
- **BR-7.7** **Customer cancellation:** allowed while status is `booked` and now < `starts_at`.
  - Before the cutoff: status `cancelled`, write `refund` +1. The message says "Your credit has been returned."
  - After the cutoff: status `cancelled`, `late_cancellation = true`, no refund. The confirmation dialog
    warns *before* confirming: "This is a late cancellation. Your spot will be freed but the credit won't be returned."
- **BR-7.8** **Admin cancels a session:** status `cancelled` with an optional reason. All `booked` bookings
  → `cancelled` with a refund (if charged), and every affected household gets an email. Guest entries remain for the record.
- **BR-7.9** **Attendance:** the admin marks bookings `attended` or `no_show`, any time from 30 minutes before
  the start. Bookings still `booked` 24 hours after the start count as attended in reports but stay `booked`
  in the data (no job changes them). Marking attendance doesn't touch credits.
- **BR-7.10** Editing a session's time or capacity doesn't touch existing bookings. If the new time
  differs, every `booked` household gets a "class time changed" email.
- **BR-7.11** Past sessions (start < now) can't be booked or cancelled by customers.

## 8. Timetable templates

- **BR-8.1** A schedule template has: title, weekday, start time, duration, capacity, min participants,
  location, cutoff override, description, `is_active`.
- **BR-8.2** "Generate sessions" (admin) takes a date range (default: from today to the end of next
  month) and the templates to use (default: all active). It creates one session per matching weekday,
  **skipping any date where a session from the same template already exists**
  (unique `(schedule_template_id, starts_at)`), and reports "created X, skipped Y".
- **BR-8.3** Editing a template never changes sessions already generated.

## 9. Admin misc

- **BR-9.1** Settings (one row, editable in admin): studio name, contact email, contact phone,
  admin notification email(s) (comma-separated), `default_cutoff_hours` (8), `booking_horizon_days` (14),
  `terms_version` (e.g. `2026-11`), `payment_instructions` (free text with EFT details, included in email C4).
- **BR-9.2** Locations: name, address, map URL (optional), `is_active`. A location used by a future
  session or an active template can't be archived.
- **BR-9.3** Guest entries on a session: name or label, `participant_count` ≥ 1, optional note. They take
  capacity and cost no credits. The admin can remove them.
- **BR-9.4** The first admin is created with `php artisan optifit:make-admin {email}`, which prompts for
  name and password. Admins can create further admins in the panel.
- **BR-9.5** Admins can't delete users that have households with bookings. They can deactivate them (BR-1.8) or anonymise them (BR-9.6).
- **BR-9.6** **Anonymise household** (POPIA erasure): replaces the user's names, email (`deleted-{id}@invalid.local`)
  and phone, the members' names (`Member {id}`), DOBs (set to 1 Jan of the birth year), health notes and
  emergency contact; deactivates login; cancels the active membership and future bookings (no refunds
  needed). Ledger, bookings and counts stay for reporting. Requires typing `ANONYMISE` to confirm.
