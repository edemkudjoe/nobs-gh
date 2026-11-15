# National Organ & Blood Bank System

A web system that connects hospitals, blood banks, donors and a national office in Ghana. It tracks blood stock, matches blood requests to available units, collects organ donor reports, and ranks waiting patients for organ offers.

End-of-semester project for Full-Stack Web Development, Group 2, IT8, University of Professional Studies, Accra (UPSA).

**Live site:** https://nobs-gh.vercel.app

---

## What it does

| Role | Can do |
|---|---|
| **National administrator** | Create and manage facilities, verify blood requests, offer blood and organs, see all stock and waiting lists, set rules, view the audit log, reset facility admin passwords, clear all data. |
| **Hospital** (administrator and staff) | See blood availability, request blood, answer blood and organ offers, report organ donors, manage the patient waiting list (transplant centres), create staff accounts. |
| **Blood bank / centre** (administrator and staff) | Open donation slots, see appointments, record and update blood units, create staff accounts. |
| **Donor** | Register, check eligibility, book and cancel a donation. |

Main rules built in:

- **Blood offers** use only units that are `available`, not past expiry, and a matching or compatible group. Routine requests skip units close to expiry. Units on offer are reserved, and released automatically if the hospital does not reply in time.
- **Organ ranking** is strict: urgency, then waiting time, then travel hours, then region. Choosing someone out of order needs a written reason, and a longer one if the patient is at the hospital that reported the donor.
- **Facility accounts** get an auto-generated registration ID (for example `H-GAR-0001`) and a one-time temporary password, delivered as a printable or downloadable PDF slip.
- **Reports** on admin, hospital and blood bank pages can be printed, downloaded as PDF, or exported as CSV.
- **Audit log** records sensitive actions (offers, resets, facility changes).

## Tech stack

- PHP 8.4 on FrankenPHP (Caddy), packaged in Docker
- PostgreSQL on Supabase (plain database, no Supabase Auth)
- Hosting: Vercel (Container Runtime)
- Plain HTML, CSS and JavaScript on the front end
- jsPDF and jspdf-autotable (self-hosted) for table PDFs

## Folder layout

```
includes/            Shared PHP: database, sessions, auth, layout, blood and organ rules, PDF helpers
  pdf-logos/         Logos (JPEG) used on the credentials slip PDF
public/              Web root
  admin/             National administrator pages
  hospital/          Hospital pages
  center/            Blood bank pages
  donor/             Donor pages
  assets/css, js, img
  assets/js/vendor/  jsPDF and jspdf-autotable
sql/                 schema.sql, seed.sql (optional sample data), travel-times SQL
Caddyfile            Web server settings
```

## Setup

### 1. Database (Supabase)

1. Create a Supabase project and open the SQL editor.
2. Run `sql/schema.sql`. This creates all tables, rules and default settings.
3. Load the travel times between regions: run the travel-times SQL file. Without it, travel time is blank and ranks last.
4. **Optional, for demos only:** `sql/seed.sql` adds sample users and records. Skip it for a real run.
5. If you created the database before the "public or private" update, also run:

```sql
ALTER TABLE facilities ADD COLUMN IF NOT EXISTS ownership VARCHAR(7) NOT NULL DEFAULT 'public' CHECK (ownership IN ('public','private'));
CREATE UNIQUE INDEX IF NOT EXISTS facilities_license_unique ON facilities (license_no) WHERE license_no IS NOT NULL;
```

### 2. First national administrator (if you did not load the seed)

```sql
CREATE EXTENSION IF NOT EXISTS pgcrypto;
INSERT INTO users (full_name, email, password_hash, role, must_change_password)
VALUES ('Your Name', 'you@example.com', crypt('Choose-a-temporary-password-1', gen_salt('bf', 10)), 'admin', TRUE);
```

Sign in with that email and password. You will be asked to choose your own password straight away. Everything else (facilities, staff) is then created from inside the system.

### 3. Environment variables (Vercel project settings)

| Variable | Meaning |
|---|---|
| `DB_HOST` | Supabase pooler host |
| `DB_PORT` | `6543` (the pooler) |
| `DB_NAME` | Database name (usually `postgres`) |
| `DB_USER` | Database user |
| `DB_PASS` | Database password |
| `DB_SSLMODE` | `require` |
| `SHOW_LOGOS` | Optional. Set to `0` to hide the logos. |

Never commit these values to the repository.

### 4. Deploy

Push to the connected GitHub repository. Vercel builds the Docker image and deploys it. On the Hobby plan the container registry holds a limited number of images, so if a push fails because the registry is full, clear old images with the Vercel CLI (`vercel vcr`) and push again.

## Using it

1. The national administrator signs in and opens **Facilities → Create facility account**.
2. The system makes the registration ID and a temporary password, and shows a slip. Download the PDF and send it to that facility's administrator.
3. The facility administrator signs in, chooses a password, and creates staff accounts from **Staff accounts**. Each new account also gets a slip, and each staff password can be reset there.
4. If a facility administrator forgets their password, the national administrator presses **Reset password** on the Facilities page. If the national administrator forgets theirs, it must be reset directly in the database.
5. Donors register themselves on the public site. There is no password-reset email, so a donor who loses their password needs an administrator to help.

## Settings

Rules such as the minimum donor age, weight, gap between donations, booking window, offer reply times and organ viability hours are in the `settings` table and editable by the national administrator under **Settings**.

## Security notes

- Passwords are stored as bcrypt hashes. Temporary passwords are random, shown once, and must be changed at first sign-in.
- Forms are protected with CSRF tokens. Sign-in is limited to five failed attempts per email in 15 minutes.
- Access to each page is checked on the server by role. Typing another role's address does not open its pages.
- Donor reports carry a consent reference, never a donor's name.
- Delete `public/health.php` before real use.
- Use real data only if all required approvals and consent are in place. This is a student project and has not been security-audited.

## Known limits

- No "forgot password" email, and no sign-in with Google.
- Travel times are estimates (straight-line helicopter flight times), not measured.
- The automatic release of lapsed offers runs when a signed-in page loads (at most once a minute), because Vercel has no background jobs.
- PDFs use standard fonts, so letters outside Western European ones appear as `?`.

## Team

Group 2, IT8, UPSA. 
