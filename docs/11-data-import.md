# 11: One-off data import

The studio already has customers. Before launch, a human exports their data into **one JSON file** in
the format below. The app imports it with:

```bash
php artisan optifit:import storage/app/import.json --dry-run   # validate + print summary, write nothing
php artisan optifit:import storage/app/import.json             # import inside one DB transaction
```

The import command doesn't know or care where the data came from. Producing the JSON is out of scope
for this repository.

## Rules

- Runs only when there are **no customer users and no households** yet. Otherwise it aborts. (`--force`
  is not offered. To re-run, rebuild the DB with `migrate:fresh --seed`.)
- Everything happens in one transaction. Any validation error aborts the whole import and prints all errors
  (each with its JSON path).
- `key` fields are the export's own identifiers, used only to link records inside the file. They aren't stored.
- Packages are **not** imported: the production seeder already created them. Memberships reference
  packages by `package_name`, which must match a seeded package exactly.
- `password_hash` is a bcrypt hash (`$2y$…`), stored as-is so customers keep their passwords. If it's
  null, the user gets a random password and must use "Forgot password".
- Users with `email_verified_at` null are imported unverified.
- `opening_balance` is written for the **current period** (see the next rule for the entry type). It's skipped
  if it's 0, since entries are never 0. The importer refuses the file if `balance_period` ≠ the current period.
- Imported memberships keep their `active`/`paused` status and get `starts_period` = current period. The importer
  writes the idempotency-keyed grant marker so the monthly job doesn't grant again this month: it writes the
  opening balance as the membership's **`grant`** entry for the current period (key `grant:{membership_id}:{period}`,
  description "Opening balance (imported)") instead of an `adjustment`. Households **without** a membership
  get their opening balance (if > 0) as an `adjustment`. The monthly job grants normally from next month.
- Future bookings are imported only for sessions that the import itself creates (`sessions` array).
  They're imported with `credit_charged = true` and **no ledger rows**, because the opening balance already reflects them.
- Print a summary at the end: users, households, members, memberships, sessions, bookings, sum of opening balances.

## Format

```json
{
  "format": "optifit-import-v1",
  "balance_period": "2026-11",
  "households": [
    {
      "key": "f17",
      "user": {
        "first_name": "Anna", "last_name": "Smith",
        "email": "anna@example.com", "phone": "082 123 4567",
        "date_of_birth": "1986-04-02",
        "password_hash": "$2y$10$abcdefghijklmnopqrstuuJ1yG0f1z1N1mQ8yqvF0gq1R2xX3yZ4a",
        "email_verified_at": "2025-10-01T09:00:00+02:00",
        "marketing_opt_in": false,
        "created_at": "2025-10-01T08:55:00+02:00"
      },
      "name": "Smith family",
      "emergency_contact": { "name": "Johan Smith", "phone": "083 765 4321" },
      "members": [
        { "key": "m40", "first_name": "Anna", "last_name": "Smith", "date_of_birth": "1986-04-02",
          "gender": "female", "is_account_holder": true, "health_notes": null },
        { "key": "m41", "first_name": "Mia", "last_name": "Smith", "date_of_birth": "2015-08-19",
          "gender": "female", "is_account_holder": false, "health_notes": "Mild asthma" }
      ],
      "membership": {
        "package_name": "2× per week",
        "status": "active",
        "covered_member_keys": ["m40", "m41"],
        "monthly_credits": 16,
        "monthly_price_cents": 78000,
        "admin_note": "Imported"
      },
      "opening_balance": 9
    }
  ],
  "sessions": [
    {
      "key": "c501", "title": "Morning Functional Fitness", "location_name": "Gene Louw Activity Centre",
      "starts_at": "2026-11-03T05:00:00+02:00", "duration_minutes": 45, "capacity": 15,
      "min_participants": 3, "cutoff_hours": null,
      "bookings": [ { "member_key": "m41" } ]
    }
  ]
}
```

`membership` may be `null`. `members[].gender` ∈ `female|male|other`. `location_name` must match an existing location.

## Test

`tests/Feature/ImportTest.php` imports a fixture built from the example above (plus a second household with
no membership) and asserts the counts, balances (`9` for the Smith household), the bcrypt login working with
the original password used to generate the fixture hash, and that a dry run writes nothing.
