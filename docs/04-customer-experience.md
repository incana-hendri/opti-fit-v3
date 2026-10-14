# 04: Customer experience

Most customers are parents on a phone, often booking early in the morning or between school runs.
Design for a **390px-wide phone first**, and make it look good on desktop. Every screen should answer
"how many credits do I have, and what's booked?" without hunting. Brand and visual rules are in `08`.

## Navigation

- **Signed-out header:** logo (left), "Timetable", "Pricing", "Log in" (text link), "Join" (orange button).
- **Signed-in, mobile:** a fixed **bottom tab bar** with 4 tabs: **Home**, **Book**, **Bookings**, **Family**.
  A top bar shows the logo and an avatar menu (Membership, Account, Log out).
- **Signed-in, desktop (≥1024px):** a top nav with the same items. Content max width is 960px.
- Footer (public pages): contact email/phone, venue address, Privacy, Terms, © year.

## Public pages

### `/` Landing
1. Hero: logo, headline "Functional fitness for the whole family", sub-line (Durbanville, 45-minute classes, kids and adults train together), buttons "Join OptiFit" and "See timetable".
2. How it works: 3 steps with icons. Add your family → choose a monthly package → book classes with credits.
3. Packages teaser: cards from active packages showing "from R{1-member price}/month", linking to `/pricing`.
4. This week's timetable (read-only, compact, next 7 days).
5. Venue card with address and map link.
6. Footer.

### `/pricing`
- A family-size selector (segmented control: 1, 2, 3, 4, 5+) that updates all package cards live (Alpine is fine).
- One card per active package (sorted): name, description, "{credits} credits / month", price for the
  selected size, "≈ R{price ÷ credits} per class", and a button "Choose" (→ register, or → request form if signed in).
- A short FAQ: credits expire at month end; book up to 8 h before; cancel before the cutoff to get the
  credit back; pay by EFT after approval; any covered family member can use the family's credits.

### `/timetable`
Read-only list of the next 14 days of scheduled sessions grouped by day: time, title, location, and places left
("3 places left" / "Full"). The CTA says "Log in to book".

### `/privacy`, `/terms`
Rendered from Markdown files in `resources/markdown/` (see `docs/legal/README.md`).

## Auth (Fortify, custom Blade views in the brand)

- `/register`: one page, fields per BR-1.1, with a password show/hide toggle. Checkbox "I accept the Terms and Privacy Policy" (links open in a new tab). After submitting → verify notice.
- `/email/verify`: "Check your inbox" with a resend button (throttled).
- `/login`, `/forgot-password`, `/reset-password/{token}`: standard.
- After login: no household → `/onboarding`, otherwise `/home`.

## Onboarding `/onboarding` (Livewire, 3 steps, progress dots, progress saved per step)

1. **About you:** "Will you be training too?" (yes/no). Household name (prefilled "{Last name} family").
2. **Your family:** a list of member cards. "Add family member" opens a sheet (first name, last name, DOB,
   gender, health notes with helper text "Injuries or conditions our trainer should know about. Only the studio sees this").
   At least one member is required to continue.
3. **Emergency contact:** name + phone.

Finish → `/membership/request` with the message "Last step: choose your package". The customer may
skip with "I'll do this later" → `/home`.

## `/home` (dashboard)

Top to bottom:
1. **Credit card** (prominent): big number "12 credits", "for October · expire 31 Oct", and a thin
   progress bar of used vs granted this month. If there's no active membership, show a status card instead:
   "No package yet → Choose a package", or "Request pending: we'll email you once approved".
   If the membership is ending: "Your membership ends on 31 Oct · Undo".
2. **Next up:** the next three bookings as cards (day, time, title, member chips), each with a link to Bookings.
3. **Book a class** button (full width, orange).
4. If not all covered members have a booking this week, a gentle hint: "Mia has no class booked this week."

## `/book` (the most important screen)

- A horizontal **day strip** for the bookable window (today → min(horizon, end of month)). Each day shows a
  dot if it has sessions. Tapping a day scrolls to it. Below it is a vertical list of session cards grouped by day.
- **Session card:** time (large), title, location, duration, a places-left badge (green > 3, orange 1–3,
  grey "Full"), and **member chips** for household members already booked ("Mia ✓"). States:
  - bookable → "Book" button;
  - past cutoff → "Booking closed" (muted) with an explanation tooltip/text;
  - full → "Full";
  - everyone covered already booked → "All booked".
- Tapping **Book** opens a **bottom sheet** (Livewire):
  - checkboxes for each covered member (already-booked members ticked and disabled; archived or uncovered members not shown),
  - live summary: "2 credits · you'll have 6 left",
  - the cutoff note: "Free cancellation until Sun 2 Nov 21:00" (the computed cutoff time),
  - a confirm button "Book 2 people". Errors (full, balance changed) show inline, then the sheet refreshes.
- After a successful booking: a toast "Booked! See you Monday 05:00", and the card updates in place.
- If there's no active membership or the balance is 0, the cards still show, but the Book button explains
  the problem and links to the fix.

## `/bookings`

Tabs: **Upcoming** | **Past**.
- **Upcoming:** grouped by session (one card per session listing the household's members on it). Each
  member row has a "Cancel" action. The confirm dialog wording depends on the cutoff (BR-7.7):
  - before the cutoff: "Cancel Mia's booking? Your credit will be returned."
  - after the cutoff: "**Late cancellation.** Mia's spot will be freed, but the credit won't be returned."
- **Past:** the last 60 days, with status labels (Attended / No-show / Cancelled / Late cancel /
  Cancelled by studio).

## `/family`

- Member cards (name, age, gender, "Covered by your package" chip, "Archived" chip). Add, edit, archive
  or delete per BR-2.5. Archived members sit in a collapsed section.
- Emergency contact card (edit).
- Household name (edit).

## `/membership`

- **Current membership card:** package name, covered members, monthly credits, monthly price,
  "Active since {month}", status. Actions: "Change package or members" (→ request form, BR-4.7), and
  "Cancel membership" (confirm dialog explaining BR-4.8) or "Undo cancellation".
- **Pending request card** (if any): details and "Withdraw request".
- **Credit history:** grouped by month (current plus 2 previous). Each row has an icon by type, the description,
  +/− amount, and date. The month footer shows "Expired: N".
- `/membership/request` form: package cards (radio) → member checkboxes (default all non-archived) →
  live summary (credits and price, BR-4.2) → optional note → "Send request". It explains: "We'll
  confirm and send payment details by email."

## `/account`

Profile (names, phone, email: changing the email requires re-verification), change password,
marketing opt-in toggle, "Request account deletion" (confirm → sets `deletion_requested_at`, emails the
admin, and shows "Requested on …"), log out.

## Empty states, errors, feedback

- Every list has a friendly empty state with one next action.
- Validation errors appear inline under fields. Flash toasts for success.
- 403/404/419/500 pages are branded, with a link home.
- No raw IDs or technical messages are shown to customers.

## Accessibility and quality bar

- Body text ≥ 16px, tap targets ≥ 44px, visible focus ring (orange), labels on every input, colour is never
  the only signal (badges have text), contrast ≥ 4.5:1 for text (see `08`).
- Works at 320px width without horizontal scroll. Respects `prefers-reduced-motion`.
- Pages load fast on mobile data: no big JS frameworks beyond Livewire/Alpine, images sized properly, fonts self-hosted.
