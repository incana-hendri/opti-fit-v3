# 03: Data model

MariaDB 10.11, InnoDB, `utf8mb4`. Laravel migrations. All tables have `id` (bigint) and
`created_at`/`updated_at` unless noted. Money is integer cents. Periods are `CHAR(7)` `YYYY-MM`.
Use foreign keys throughout. Laravel's own tables (`sessions`, `cache`, `jobs`, `failed_jobs`,
`password_reset_tokens`) are added as usual.

There are **14 app tables**. If you find yourself adding a fifteenth, check `AGENTS.md` first.

## Tables

### users
| column | type | notes |
|---|---|---|
| first_name, last_name | string | |
| email | string unique | |
| phone | string(20) | mobile, stored as entered, validated loosely (digits, +, spaces) |
| date_of_birth | date null | required for customers (BR-1.2), null for admins |
| password | string | bcrypt (the import keeps existing `$2y$` hashes, which Laravel accepts) |
| role | enum(`customer`,`admin`) default `customer` | |
| email_verified_at | timestamp null | |
| terms_version | string null | BR-1.4 |
| terms_accepted_at | timestamp null | |
| marketing_opt_in | bool default false | |
| deactivated_at | timestamp null | BR-1.8 |
| deletion_requested_at | timestamp null | BR-1.6 |
| remember_token | | |

### households
| column | type | notes |
|---|---|---|
| user_id | FK users, **unique** | the account holder (BR-2.1) |
| name | string | default "{last_name} family", editable |
| emergency_contact_name | string null | BR-2.6 |
| emergency_contact_phone | string null | |
| anonymised_at | timestamp null | BR-9.6 |

The household row is the **lock target** for all credit and booking operations (`lockForUpdate`).

### members
| column | type | notes |
|---|---|---|
| household_id | FK | |
| first_name, last_name | string | |
| date_of_birth | date | |
| gender | enum(`female`,`male`,`other`) | |
| is_account_holder | bool default false | BR-2.2 |
| health_notes | text null | `encrypted` cast |
| archived_at | timestamp null | BR-2.5 |

### packages (soft-deletes are **not** used; see BR-3.4)
| column | type | notes |
|---|---|---|
| name | string | |
| description | string(500) null | |
| sessions_per_week | tinyint | display/guide only (D1) |
| credits_per_member | smallint | monthly credits per covered member |
| is_active | bool default true | |
| sort_order | smallint default 0 | |

### package_prices
| column | type | notes |
|---|---|---|
| package_id | FK cascade delete | |
| member_count | tinyint ≥ 1 | unique `(package_id, member_count)` |
| price_cents | int unsigned | |

### memberships
| column | type | notes |
|---|---|---|
| household_id | FK | |
| package_id | FK (restrict) | |
| status | enum(`requested`,`active`,`paused`,`cancelled`,`declined`) | |
| monthly_credits | smallint | copied at request, final at approval (D5) |
| monthly_price_cents | int unsigned | copied at request, final at approval |
| starts_period | char(7) null | set at approval |
| ends_period | char(7) null | last period with a grant (BR-4.7, 4.8) |
| grants_from_period | char(7) null | first period the monthly job may grant; set at approval and resume (BR-4.4, 4.9, 5.5) |
| customer_note | string(500) null | |
| admin_note | text null | e.g. payment reference (D8) |
| decline_reason | string(500) null | |
| requested_at | timestamp | |
| approved_at | timestamp null | |
| approved_by | FK users null | |

BR-4.1 (one membership in effect per period, one requested) is enforced in the action code, inside a
household lock. No DB constraint is needed.

### membership_member (pivot, no timestamps)
`membership_id` FK cascade, `member_id` FK, primary key `(membership_id, member_id)`.

### credit_entries (immutable)
| column | type | notes |
|---|---|---|
| household_id | FK | |
| period | char(7) | index `(household_id, period)` |
| type | enum(`grant`,`booking`,`refund`,`adjustment`,`expiry`) | |
| amount | smallint (signed) | never 0 |
| membership_id | FK null | for grants |
| booking_id | FK null | for booking/refund |
| description | string(255) | human text shown to the customer, e.g. "Booked Mia – Mon 3 Nov 05:00" |
| idempotency_key | string(100) null **unique** | `grant:{m}:{p}`, `expiry:{h}:{p}`, `refund:{b}` |
| created_by | FK users null | null = system |
| created_at | timestamp | **no `updated_at`** |

The model must throw on `update`/`delete` (BR-5.3).

### locations
`name`, `address`, `map_url` null, `is_active` bool.

### schedule_templates
`title`, `location_id`, `weekday` tinyint (1=Mon … 7=Sun, ISO), `start_time` time, `duration_minutes`
smallint default 45, `capacity` smallint, `min_participants` smallint null, `cutoff_hours` tinyint null,
`description` string null, `is_active` bool.

### class_sessions
(Named `class_sessions` to avoid clashing with Laravel's `sessions` table. The model is `ClassSession`.)

| column | type | notes |
|---|---|---|
| schedule_template_id | FK null (null on delete) | |
| location_id | FK | |
| title | string | |
| starts_at | datetime | local time (Africa/Johannesburg, no DST) |
| duration_minutes | smallint default 45 | |
| capacity | smallint | |
| min_participants | smallint null | |
| cutoff_hours | tinyint null | null → setting (D2) |
| description | string(500) null | |
| status | enum(`scheduled`,`cancelled`) default `scheduled` | |
| cancellation_reason | string null | |

Unique `(schedule_template_id, starts_at)` (BR-8.2). Index `(starts_at)`.

### bookings
| column | type | notes |
|---|---|---|
| class_session_id | FK | |
| member_id | FK | |
| household_id | FK | denormalised for scoping and locking |
| status | enum(`booked`,`cancelled`,`attended`,`no_show`) | |
| credit_charged | bool default true | BR-6.3 |
| late_cancellation | bool default false | BR-7.7 |
| booked_by | FK users | customer or admin |
| cancelled_at | timestamp null | |
| cancelled_by | FK users null | |
| active_key | **generated** `tinyint AS (IF(status IN ('booked','attended','no_show'),1,NULL)) STORED` | |

Unique `(class_session_id, member_id, active_key)`. NULLs don't collide, so cancelled rows can repeat
and a member can rebook after cancelling (BR-7.5).

### guest_entries
`class_session_id` FK, `name` string, `participant_count` smallint ≥ 1, `note` string null, `created_by` FK users.

### settings
A single row (BR-9.1): `studio_name`, `contact_email`, `contact_phone`, `admin_notification_emails`,
`default_cutoff_hours` (8), `booking_horizon_days` (14), `terms_version`, `payment_instructions` (text). Access it through a small
cached `Settings` model (`Settings::current()`), with the cache cleared on save.

## Relationships (Eloquent)

User 1–1 Household · Household 1–n Member, Membership, CreditEntry, Booking · Membership n–n Member ·
Package 1–n PackagePrice, Membership · Location 1–n ScheduleTemplate, ClassSession ·
ClassSession 1–n Booking, GuestEntry · Booking 1–n CreditEntry.

## Seed data (`DatabaseSeeder`)

Production seed (safe to run once):
- Settings: studio name "OptiFit", contact email `info@opti-fit.co.za`, cutoff 8, horizon 14, terms version `2026-11`.
- Location: **Gene Louw Activity Centre**, *Tulip Street, Amanda Glen, Durbanville*.
- Packages and price tiers (current studio prices, ZAR per month):

| Package | sessions/wk | credits/member | 1 member | 2 | 3 | 4 | 5+ |
|---|---|---|---|---|---|---|---|
| 1× per week | 1 | 4 | R280 | R420 | R532 | R616 | R672 |
| 2× per week | 2 | 8 | R520 | R780 | R998 | R1 144 | R1 248 |
| 3× per week | 3 | 12 | R720 | R1 080 | R1 368 | R1 584 | R1 728 |

- Schedule templates (all 45 min, capacity 15, min 3, at the location above). The admin will adjust these:
  Mon 05:00 "Morning Functional Fitness", Mon 16:00 "Afternoon Functional Fitness",
  Tue 15:30 "Afternoon Functional Fitness", Wed 05:00 "Morning Functional Fitness",
  Thu 16:00 "Afternoon Functional Fitness", Fri 05:00 "Morning Functional Fitness".

A separate `DemoSeeder` (local only) creates three households with members, memberships, balances,
sessions for the next two weeks and some bookings. Use it for development and screenshots.
