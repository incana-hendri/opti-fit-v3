# OptiFit

Booking and credits app for **OptiFit Functional Fitness Training**, a single family-oriented
functional-fitness studio (Gene Louw Activity Centre, Tulip Street, Amanda Glen, Durbanville).
Website: www.opti-fit.co.za · Contact: info@opti-fit.co.za

Families sign up, add their members, and request a monthly package. The studio approves the
request and the family receives a monthly pool of credits. One credit books one person into one
class. Credits expire at month end.

> **Status:** Milestones 0 (scaffold and brand) and 1 (accounts and households) are done. Next is M2,
> packages, memberships and credits.
> Start with `AGENTS.md`, then `docs/10-milestones.md`.

## Repository map

| Path | What it is |
|---|---|
| `AGENTS.md` | Rules for anyone (human or AI) working here: scope, architecture, code and documentation standards. **Read first.** |
| `CLAUDE.md` | Loads `AGENTS.md` for Claude Code. Holds no rules of its own |
| `docs/01-product-overview.md` | Who it's for, scope, settled decisions, glossary |
| `docs/02-business-rules.md` | Numbered rules (BR-x.y). The source of truth for behaviour |
| `docs/03-data-model.md` | Tables, columns, constraints |
| `docs/04-customer-experience.md` | Customer screens and flows (mobile-first) |
| `docs/05-admin.md` | Filament admin panel: resources, actions, dashboard |
| `docs/06-emails.md` | Every email the system sends |
| `docs/07-architecture-and-stack.md` | Stack, structure, scheduler, security, hosting and deploy |
| `docs/08-brand-and-design.md` | Logo, colours, font, UI principles |
| `docs/09-testing-and-acceptance.md` | Required tests and the definition of done |
| `docs/10-milestones.md` | Build order with acceptance criteria |
| `docs/11-data-import.md` | One-off import of existing customers (JSON format) |
| `docs/decisions-log.md` | Running log of implementation decisions |
| `docs/legal/` | Privacy policy and terms (source PDFs and text) plus required amendments |
| `brand/` | Logos and `tokens.css` (brand colour and font tokens) |

## Quick start

No local PHP is needed, only Docker. The first command installs `vendor/` (and with it Sail).

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:latest composer install --ignore-platform-reqs
cp .env.example .env
./vendor/bin/sail up -d          # PHP 8.4, MariaDB 10.11, Mailpit
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run dev
./vendor/bin/sail artisan optifit:make-admin you@example.com
```

- Customer site: http://localhost
- Admin panel: http://localhost/admin
- Mailpit (outgoing mail): http://localhost:8025
- Component styleguide (not in production): http://localhost/_styleguide
- Tests: `./vendor/bin/sail composer test` · style: `sail composer lint` · static analysis: `sail composer analyse`
