# 06: Emails

Transactional only. Use Laravel Mailables with **Markdown mail templates themed in the brand**: a light
email body (white card on `#F4F3F7`) for client compatibility, the logo `optifit-logo-on-light-tagline.jpg`
at the top, orange buttons with dark text, and the footer "OptiFit · Gene Louw Activity Centre, Durbanville ·
info@opti-fit.co.za". Every mail is **queued** (database queue, see `07`). The sender is `MAIL_FROM_ADDRESS`
(info@opti-fit.co.za) and replies go to the contact email.

Subjects are short and specific. Times are shown as `Mon 3 Nov, 05:00`.

## To customers

| # | Trigger | Subject | Content |
|---|---|---|---|
| C1 | Registration | Verify your email | Fortify verification link |
| C2 | Forgot password | Reset your password | Fortify reset link |
| C3 | Membership requested (BR-4.2) | We've received your package request | Package, members, credits, price; "We'll confirm and send payment details." |
| C4 | Approved (BR-4.4) | You're in! Your OptiFit package is active | Package, covered members, monthly credits/price, start month, first-month credits if granted now, **payment instructions** (the `payment_instructions` setting, BR-9.1), "Book your first class" button |
| C5 | Declined (BR-4.6) | About your package request | Reason, contact email |
| C6 | Change approved (BR-4.7) | Your package changes on 1 {Month} | Old → new, effective date |
| C7 | Monthly grant (BR-5.5) | Your {Month} credits are ready | Credits granted, expire on {last day}, "Book now" button |
| C8 | Booking made (by customer or admin) | Booked: {Title}, {Mon 3 Nov, 05:00} | Members booked, location, free-cancellation-until time, credits left |
| C9 | Booking cancelled by customer | Cancelled: {Title}, {date time} | Members, whether the credit was returned (BR-7.7) |
| C10 | Booking cancelled by admin | Your booking was cancelled | Member, session, credit returned yes/no |
| C11 | Session cancelled (BR-7.8) | Class cancelled: {Title}, {date time} | Reason if given, credits returned, "Book another class" button |
| C12 | Session time changed (BR-7.10) | Class time changed: {Title} | Old → new time, "Can't make it? Cancel for free until {new cutoff}" |
| C13 | Membership cancellation set (BR-4.8) | Your membership ends on {last day} | Credits usable until then, undo link |

## To admins (all addresses in `admin_notification_emails`)

| # | Trigger | Subject |
|---|---|---|
| A1 | New registration (verified) | New OptiFit sign-up: {name} |
| A2 | Membership request or change request | Package request: {household} – {package} |
| A3 | Customer cancelled membership | Membership ending: {household} |
| A4 | Account deletion requested | Deletion request: {household} |
| A5 | Daily 06:00, only if any of today's sessions are below their minimum | Classes below minimum today |

No other emails. No marketing emails are sent by the app (the opt-in is only recorded).
