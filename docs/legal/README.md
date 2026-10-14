# Legal pages

Source documents (as currently published by the studio):
- `privacy-policy.pdf` / `privacy-policy.txt`: POPIA privacy policy, effective 27 Aug 2025
- `terms-of-service.pdf` / `terms-of-service.txt`: terms of service, effective 27 Aug 2025

The `.txt` files were extracted from browser print-outs, so they contain print artefacts: page
headers/footers such as `localhost:8080/terms-of-service.php`, `2/8`, dates like `04/09/2025, 08:37`, and
repeated titles. **Remove them** when converting.

## Task (Milestone 4)

Convert both to clean Markdown in `resources/markdown/privacy.md` and `resources/markdown/terms.md`, keeping
the wording and section numbering. Apply **only** these amendments so the legal text matches the new rules:

**Terms of Service**
1. §4.1 Monthly Packages: replace the fixed "(2×, 3×, or 4× per week)" with "the packages listed on our
   pricing page, which may change from time to time".
2. §4.3 Credit System: remove "Weekly booking limits apply (cannot use all credits in one week)". Add:
   "Credits are shared by the family members covered by your package."
3. §5.1 Booking Policy: remove "Weekly limits apply per package type". Add: "Bookings close 8 hours before
   the class starts (or as shown for that class)."
4. §5.2 Cancellation Policy: replace "Cancellations do not restore used credits" with: "If you cancel before
   the booking cutoff (8 hours before the class, unless shown otherwise), your credit is returned. Later
   cancellations free your spot but the credit is not returned. If the studio cancels a class, your credit is
   always returned." Keep "No-shows are treated as attended (credits used)" and "Emergency cancellations
   considered case-by-case".
5. Effective/last-updated date: the launch month. This also becomes the `terms_version` setting (e.g. `2026-11`).

**Privacy Policy**
1. Remove references to analytics/usage tracking and to cookie preferences beyond essential session cookies
   (the new app uses no analytics). State that only essential cookies are used.
2. Mention that optional health notes about family members are stored encrypted and visible only to studio staff.
3. Effective/last-updated date: the launch month.

At the top of each Markdown file add the comment `<!-- Amended for the new app; owner to review before launch. -->`.
Render the pages with a simple prose layout (Tailwind typography-like styles written by hand; don't add the plugin
unless it's trivial).
