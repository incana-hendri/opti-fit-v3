# 09: Testing and acceptance

Pest feature tests against MariaDB. Name each test after the rule it proves, e.g.
`it('refunds the credit when cancelled before cutoff (BR-7.7)')`. Freeze time with `$this->travelTo()`
for anything time-based. Use model factories for every model.

## Required tests (minimum set)

**Accounts and access**
- Registration validation, the under-18 rejection (BR-1.2), terms version stored (BR-1.4).
- Unverified users are redirected to the verify notice (BR-1.3).
- Customers get a 403 on `/admin`. Admins are redirected from `/home` to `/admin` (BR-1.7). Deactivated users can't log in (BR-1.8).
- **Isolation:** customer A gets a 404/403 when trying to edit, archive or book B's member, cancel B's booking,
  or withdraw B's request (BR-2.7). Cover each Livewire action and route that takes an ID.

**Members**
- Archiving vs deleting (BR-2.5). Archived members can't be booked (BR-4.10).

**Packages**
- Tier resolution: 1, 2, 5 and 9 members against tiers {1,2,3,4,5} and against {1,3} (BR-3.2).
- A package with memberships can't be deleted, but can be archived. Archived packages are hidden from requests (BR-3.3/3.4).

**Memberships**
- One active and one requested at most (BR-4.1). Request computes credits and price (BR-4.2).
- Approve with a current-period start grants the first-month credits once; approving twice doesn't double-grant (BR-4.4).
- Proration: 1 Oct (31 days) → full; 16 Oct → 16/31; 31 Oct → round(1/31 × N) with min 1 (BR-4.5).
- A change request ends the old membership this period and starts the new one next period (BR-4.7).
- Customer cancel and undo; the job sets `cancelled` after the period (BR-4.8).
- Pause skips the next grant; resume restores it (BR-4.9).

**Credits**
- Balance = sum for the period (BR-5.1). Updating or deleting an entry throws (BR-5.3).
- Monthly job: expires the previous balance, grants current, sends one C7; **running it twice changes nothing**;
  running it on the 3rd after a missed 1st still produces the correct result (BR-5.5).
- Refund at most once per booking, across the automatic and manual paths (BR-5.7).
- A negative adjustment can't go below zero (BR-6.5).

**Booking**
- Every BR-7.2 condition rejects correctly: cancelled session, past cutoff (e.g. a 05:00 class → closes 21:00
  the previous day), next month, beyond the horizon, uncovered member, double booking, full, insufficient balance.
- A session cutoff override beats the setting (D2).
- A multi-member booking is all-or-nothing (BR-7.3): 3 members, 2 places left → nothing booked, no ledger rows.
- Capacity counts guests and attended/no-show bookings (BR-7.4).
- **Concurrency (BR-7.6):** a test that opens two DB connections (or uses `pcntl_fork`/two processes via
  `Process::pool`) to book the last place at the same time → exactly one success. If true parallelism is
  impractical in CI, at minimum prove the unique index rejects a duplicate active booking and that the action
  re-checks balance and capacity *after* acquiring the lock (by injecting a competing booking between the
  pre-check and the lock using a test hook).
- Rebooking after a cancellation works (the generated `active_key` index).
- Cancel before the cutoff → refund. After the cutoff → `late_cancellation`, no refund. After the start → rejected (BR-7.7/7.11).
- Admin: book over capacity only with the flag; free booking writes no ledger row; cancel with/without
  return credit; return credit on a no-show (BR-6.1–6.4).
- Session cancel refunds all charged bookings and queues the C11 emails (BR-7.8). Reschedule queues C12 (BR-7.10).

**Timetable**
- Generate creates the right weekdays and skips existing ones, reporting the counts (BR-8.2).

**Anonymise**
- Personal data scrubbed, ledger and booking counts unchanged, login blocked (BR-9.6).

**Emails**
- `Mail::fake()`: each trigger in `06` queues the right Mailable to the right recipients.

**Smoke (HTTP)**
- Every public page and every customer page returns 200 for an appropriate user, and the Filament resource index pages return 200 for an admin.

## Optional browser test
One Pest browser (or Dusk) test of the happy path on a 390px viewport: register → verify (via signed URL) → onboarding
→ request → (admin approves via factory) → book → cancel. Add it only once M3 is done. Skip it if it's
flaky in CI.

## Definition of done (per milestone)

- [ ] The milestone's acceptance criteria in `10-milestones.md` are demonstrably met.
- [ ] Tests for every BR touched exist and pass (`composer test`), and Pint and Larastan are clean.
- [ ] Customer screens touched are checked at 390px and 1280px widths (the agent takes screenshots if it can).
- [ ] No TODOs left for this milestone's scope. Decisions are logged in `docs/decisions-log.md`.
- [ ] `DemoSeeder` still runs and shows the new feature.
