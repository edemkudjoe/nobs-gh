# National Organ & Blood Bank System

A web system that connects hospitals, blood banks, donors and a national office in Ghana. It tracks blood stock, matches blood requests to available units, collects organ donor reports, and ranks waiting patients for organ offers.

End-of-semester project for Full-Stack Web Development, Group 2, IT8, University of Professional Studies, Accra (UPSA). It is a student project and is not an official NBS, MoH or GHS system.

**Live site:** https://nobs-gh.vercel.app

---

## What it does

| Role | Can do |
|---|---|
| **National administrator** | Create and manage facilities, verify blood requests and waiting-list entries, offer blood and organs, see all stock, set rules, view the audit log, reset a facility administrator's password, clear all data. |
| **Hospital** (administrator and staff) | See blood availability, request blood, answer blood and organ offers, report organ donors, manage the patient waiting list (transplant centres), create staff accounts. |
| **Blood bank / centre** (administrator and staff) | Open donation slots, see appointments, record and update blood units, create staff accounts. |
| **Donor** | Register, check eligibility, book and cancel a donation. |

Main rules built in:

- **Blood offers** use only units that are `available`, not past expiry, and a matching or compatible group. Routine requests skip units close to expiry. Units on offer are reserved, and released automatically if the hospital does not reply in time. A request marked "verified" has only been approved into the queue. It is not yet offered blood.
- **Organ ranking** is strict: urgency, then waiting time, then travel hours, then region. Choosing someone out of order needs a written reason, and a longer one if the patient is at the hospital that reported the donor.
- **Facility accounts** get an auto-generated registration ID (for example `H-GAR-0001`: H hospital or B blood bank, a region code, a running number) and a one-time temporary password, delivered as a printable or downloadable PDF slip.
- **Reports** on administrator, hospital and blood bank pages can be printed, downloaded as PDF, or exported as CSV.
- **Audit log** records sensitive actions (offers, resets, facility changes).

## Tech stack

- PHP 8.4 on FrankenPHP (Caddy), in Docker
- PostgreSQL 16 on Supabase (a plain database, no Supabase Auth)
- Hosting: Vercel (Container Runtime)
- Plain HTML, CSS and JavaScript on the front end
- jsPDF and jspdf-autotable, self-hosted in `public/assets/js/vendor/`, for table PDFs. The credentials slip PDF is built on the server with no library.

## Repository layout

```
Dockerfile.vercel    Image used on Vercel and locally
docker-compose.yml   Local app + PostgreSQL
Caddyfile            Web server settings
.env.example         Environment variables, local and Supabase examples
includes/            Shared PHP: database, sessions, auth, layout, blood and organ rules, PDF slip
  pdf-logos/         JPEG logos used on the slip PDF
public/              Web root
  admin/ hospital/ center/ donor/   One folder per role
  assets/css, js, img
  assets/js/vendor/  jsPDF and jspdf-autotable
sql/
  schema.sql          Tables and indexes (20 tables)
  seed-reference.sql  Required lookup data: regions, blood groups, compatibility, settings
  travel-times.sql    Estimated travel hours for all 120 region pairs
```

## Setup on Supabase and Vercel

### 1. Database

Run these in the Supabase SQL editor, in this order, on a **new** project:

1. `sql/schema.sql`. **Warning:** it starts with `DROP SCHEMA public CASCADE`, so it erases everything in the database. Never run it on a database that holds data you want to keep.
2. `sql/seed-reference.sql`. The system does not work without it (no regions, blood groups or rule settings). It contains no sample users and no demo records.
3. `sql/travel-times.sql`. Without it, travel time is blank and ranks last in organ offers.

If you are upgrading a database made before facilities had a public/private field, run this instead of starting over:

```sql
ALTER TABLE facilities ADD COLUMN IF NOT EXISTS ownership VARCHAR(7) NOT NULL DEFAULT 'public' CHECK (ownership IN ('public','private'));
CREATE UNIQUE INDEX IF NOT EXISTS facilities_license_unique ON facilities (license_no) WHERE license_no IS NOT NULL;
```

### 2. First national administrator

```sql
CREATE EXTENSION IF NOT EXISTS pgcrypto;
INSERT INTO users (full_name, email, password_hash, role, must_change_password)
VALUES ('Your Name', 'you@example.com', crypt('Choose-a-temporary-password-1', gen_salt('bf', 10)), 'admin', TRUE);
```

Sign in with that email and password. You are asked to choose your own password straight away. Everything else (facilities, staff, donors) is created from inside the system.

### 3. Environment variables (Vercel project settings)

| Variable | Meaning |
|---|---|
| `DB_HOST` | Supabase pooler host (Supabase: Project, then Connect) |
| `DB_PORT` | `6543` for the pooler. Defaults to `5432`. |
| `DB_NAME` | Usually `postgres` |
| `DB_USER` | The user Supabase shows for the pooler |
| `DB_PASS` | Your database password |
| `DB_SSLMODE` | `require` (the default) |
| `SHOW_LOGOS` | Optional. `0` hides the partner logos. |

Never commit real values. `.env*` files are ignored by git, except `.env.example`.

### 4. Deploy

Push to the connected GitHub repository. Vercel builds `Dockerfile.vercel` and deploys it.

## Run it on your own computer

You need Docker.

```
cp .env.example .env
docker compose up --build
```

Open http://localhost:8080. On first start, the database container runs everything in `sql/` in file order (`schema.sql`, then `seed-reference.sql`, then `travel-times.sql`). Then create the first administrator:

```
docker compose exec db psql -U nobb -d nobb
```

and paste the SQL from step 2 above. To start over with an empty database, run `docker compose down -v` and `docker compose up --build`.

## Using it

1. The national administrator signs in and opens **Facilities, then Create facility account**.
2. The system makes the registration ID and a temporary password and shows a slip. Download the PDF and send it to that facility's administrator.
3. The facility administrator signs in, chooses a password, and creates staff accounts from **Staff accounts**. Each new account gets a slip, and each staff password can be reset there.
4. If a facility administrator forgets their password, the national administrator presses **Reset password** on the Facilities page. If the national administrator forgets theirs, it must be reset in the database.
5. Donors register themselves on the public site. There is no password-reset email, so a donor who loses their password needs an administrator's help.

Rules such as minimum donor age and weight, the gap between donations, booking window, offer reply times and organ viability hours live in the `settings` table and can be edited by the national administrator under **Settings**.

## Security notes

- Passwords are stored as bcrypt hashes. Temporary passwords are random, shown once, and must be changed at first sign-in.
- Forms use CSRF tokens. Sign-in is limited to five failed attempts per email in 15 minutes.
- Every page checks the signed-in role on the server. Typing another role's address does not open its pages.
- Donor reports carry a consent reference, never a donor's name.
- Use real data only if all required approvals and consent are in place. This project has not been security-audited.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| HTTP 500 on a page | Check the Vercel logs for the `PHP Fatal error` line. Common causes: a missing environment variable, or `ownership` or another recent column missing from the database. |
| Facility form shows no regions | `seed-reference.sql` was not run. |
| Table PDF button says "could not be made" | The two files in `public/assets/js/vendor/` are missing. |
| Slip PDF has no logos | The JPEGs in `includes/pdf-logos/` are missing. The PDF still works. |
| Vercel push fails, registry full | The Hobby plan keeps a limited number of images. Clear old ones with `vercel vcr` and push again. |

## Known limits

- No "forgot password" email and no sign-in with Google.
- Travel times are estimates (straight-line helicopter flight times between regional capitals), not measured.
- Lapsed offers are released when a signed-in page loads (at most once a minute), because Vercel has no background jobs.
- PDFs use standard fonts, so letters outside Western European ones appear as `?`.
