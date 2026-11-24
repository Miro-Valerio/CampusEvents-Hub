# CampusEvents Hub — SPC Events, Announcements & RSVPs

A Laravel 12 REST API + React/Vite frontend + MySQL campus event management application for **St. Peter's College (SPC)**. UI styling follows the supplied burgundy/cream reference image with a campus-style hero, floating cards, navigation, and dedicated dashboards.

**Project folders:**

```text
CampusEvents-HubSPC/
├── Backend/                       Laravel API source (PHP 8.2+)
│   ├── app/                        Controllers, models, role checks, reminder command
│   ├── routes/api.php              REST endpoints
│   ├── database/migrations/        Laravel version of the schema
│   ├── database/seeders/           Departments, sample event and demo accounts
│   └── database/sql/SPC-EventHub.sql  Importable MySQL database SQL
├── frontend/                      React, React Router, Vite and local SVG graphic
│   ├── src/pages/                  Landing, Events, Students, Organizer, Admin, etc.
│   ├── src/styles.css              Responsive burgundy/cream design
│   └── public/campus-silhouette.svg  Illustrated hero art (not a real SPC photo)
└── docs/FEATURE_CHECKLIST.md      All 40 features and routes
```

## Requirements

- Windows with Laragon, MySQL 8+ or compatible MariaDB, and phpMyAdmin.
- PHP **8.2+** with usual Laravel extensions (including mbstring, openssl, pdo_mysql, fileinfo, xml).
- Composer 2.x.
- Node.js 20+ and npm.
- An internet connection **on your PC during installation** to download Composer and npm dependencies. Neither `vendor/` nor `node_modules/` is bundled.

> **Important:** The database name is **`campusevents-hubspc`**. Because it has a hyphen, raw SQL uses backticks, and Laravel `.env` uses `DB_DATABASE="campusevents-hubspc"`. The SQL file is named `SPC-EventHub.sql` but is configured for this database.

## 1. Extract and open in Laragon

The ZIP is applied to the existing project folder (do not create a nested `CampusEvents-Hub` folder):

```text
C:\laragon\www\CampusEvents-HubSPC
```

Start **Laragon** and **Start All** (MySQL). Laragon's terminal or Windows Terminal can be used. The example commands below assume **PowerShell**. `npm.cmd` avoids PowerShell's possible `npm.ps1` execution policy error.

## 2. Create your MySQL database (choose ONE method)

**Method A: Import the included complete SQL file using phpMyAdmin (recommended when you asked for SQL).**

1. Confirm the target database does not already exist. Do not import over an existing database or tables.
2. Open `http://localhost/phpmyadmin` from Laragon (or the phpMyAdmin URL configured on your PC).
3. Select **Import** and choose `Backend/database/sql/SPC-EventHub.sql`. It contains the baseline schema and 19 tables.
4. Import the file once, then run `php artisan migrate` to apply newer additive migrations. Never re-import over an existing database.
5. Seed the sample content with `php artisan db:seed` only if desired.

**Method B: Laravel migrations (alternative).**

In phpMyAdmin's SQL tab, create only the empty database:

```sql
CREATE DATABASE IF NOT EXISTS `campusevents-hubspc` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

After installing the backend, run `php artisan migrate --seed`. This creates the same application tables from the Laravel migration.

## 3. Install the Laravel API

Open a terminal in `C:\laragon\www\CampusEvents-HubSPC\Backend`:

```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }
composer install
```

Open `backend/.env` and confirm:

```ini
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Manila
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE="campusevents-hubspc"
DB_USERNAME=root
DB_PASSWORD=
```

If your Laragon MySQL `root` user has a password, enter it in `DB_PASSWORD` (quote it when needed). Then run **one** of these:

```powershell
# If you imported the schema into a newly created empty database, seed only if sample data is wanted
php artisan db:seed

# If you used Method B: created an empty database
php artisan migrate --seed
```

Generate an application key only if `APP_KEY` is blank; do not rotate an existing key. Finish backend setup:

```powershell
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Keep this terminal open. You should be able to visit `http://127.0.0.1:8000/api/health` and receive a JSON status response. On Windows, `storage:link` may require Administrator rights or Windows Developer Mode for a symbolic link.

## 4. Install the React frontend

Open a **second** terminal in `C:\laragon\www\CampusEvents-HubSPC\frontend`:

```powershell
npm.cmd install
npm.cmd run dev
```

Open **http://127.0.0.1:5173** in the browser.

Vite proxies `/api` and `/storage` to Laravel at `http://127.0.0.1:8000`, so **keep both the Laravel and Vite servers running**. Do not open `index.html` by double-clicking it: use the Vite URL.

## 5. Starter accounts

These are **local development accounts only** created by `php artisan db:seed`:

| Access | Email | Starting password |
|---|---|---|
| Admin | `admin@spc.local` | `ChangeMe123!` |
| Department president | `president@spc.local` | `ChangeMe123!` |
| Officer | `officer@spc.local` | `ChangeMe123!` |
| Student | `student@spc.local` | `ChangeMe123!` |

The seed creates four sample departments and a future technology event. **Change demo passwords, remove unnecessary demo accounts, and use authorized email addresses before real deployment.** Registration only creates student accounts. Admin users can create Faculty accounts under **Admin → Users & roles**; Faculty are assigned to the existing CCS department and receive a password setup link by email. Faculty accounts have no publishing permissions by default. Admin can grant Faculty Adviser event or announcement permissions when editing the account. Department presidents can adjust existing officers' permissions under **Organizer → Officer access**.

The additive `2026_11_22_233000_add_faculty_profile_and_activation_to_users_table` migration adds optional Faculty position and account activation fields. Back up the database before applying it, then run:

```powershell
php artisan migrate --path=database/migrations/2026_11_22_233000_add_faculty_profile_and_activation_to_users_table.php
```

## 6. Notifications, reminders and password reset

In a **third terminal** in the backend folder, run:

```powershell
php artisan schedule:work
```

This triggers the `events:remind` command every five minutes. You can also test reminders manually:

```powershell
php artisan events:remind
```

It generates **in-app notifications** up to 24 hours before RSVPed events (once per RSVP). Schedule changes, cancellations, announcement publishing and membership decisions also create in-app alerts.

The authenticated bell opens a recent-notification dropdown and refreshes unread counts every 30 seconds. The full notification center supports read/unread state and pagination. Event reports are saved as pending, sent to Admin accounts with a Reports-page link, and can be marked **Under Review**, **Resolved**, or **Dismissed**; the reporting student is notified of decisions. Department event and announcement publications notify the department's students and faculty, and Admin accounts receive department-publication alerts.

Notifications use the existing `user_notifications` table. The additive `2026_11_17_000001_add_notification_metadata` migration adds report categories and a per-user notification deduplication key without removing records. The current SQL schema includes those columns and records the migration as applied. For an older installation only, back up the database and run this migration if its columns and migration record are not already present:

```powershell
php artisan migrate --path=database/migrations/2026_11_17_000001_add_notification_metadata.php
```

In local development, **`MAIL_MAILER=log`** writes password reset and email-change verification messages to `Backend/storage/logs/laravel.log`. To deliver these links to real inboxes, configure a working SMTP server in `.env` and set `MAIL_MAILER=smtp`. Set `FRONTEND_URL` to the frontend's public base URL; email-change verification returns the user to Profile Settings after confirmation. The authenticated `POST /api/me/email-change` endpoint requires the current password and emails a signed, one-hour verification link. The signed `GET /api/email/change/verify` endpoint activates the new address. Admin-created Faculty accounts use the existing password reset mechanism to send a password setup link.

Event display status is calculated from `starts_at` and `ends_at` in the configured `APP_TIMEZONE` (Asia/Manila by default). Cancelled events remain listed and take precedence over date-based status; ended events remain in Discover and the archive.

QR attendance uses the event check-in window from two hours before `starts_at` through `ends_at`. Student scans create a pending verification record; authorized department event managers approve or reject it. Pending check-ins are not counted as attended. Events can optionally require check-out; organizers configure its open/close times, defaulting to the event end and a 30-minute grace period (maximum two hours after the event). Attendance is completed after approval when check-out is not required, or after an approved check-in and valid check-out when it is. The additive `2026_11_22_234500_add_verified_attendance_and_event_checkout` migration adds these fields without updating historical attendance records. Back up the database, then apply it from `Backend` with:

```powershell
php artisan migrate --path=database/migrations/2026_11_22_234500_add_verified_attendance_and_event_checkout.php
```

QR camera scanning requires camera permission and a secure context (HTTPS or localhost). Manual code entry remains available as a fallback.

Admins can set Online or Busy from the account dropdown. Presence is refreshed after Admin interaction and expires after two minutes without activity; department Presidents and Officers see the aggregate Admin status above the Department Workspace heading, refreshed every 15 seconds. Before enabling this feature on another database, back it up and apply the additive migration from `Backend`:

```powershell
php artisan migrate --path=database/migrations/2026_11_24_000001_add_admin_availability_and_presence_to_users.php
```

Deleting an event from Organizer or Admin removes it from normal event listings using Laravel soft deletes. Existing RSVP, check-in, and attendance rows and poster files are retained. Back up the database, then apply `2026_11_24_000002_add_soft_deletes_to_events.php` from `Backend`.

Student registration requires a Year Level. Existing user rows remain unchanged with `year_level` set to null until updated by an Admin. Year Level is included in authorized event attendee data and RSVP exports. Back up the database, then apply the additive migration from `Backend`:

```powershell
php artisan migrate --path=database/migrations/2026_11_24_000003_add_year_level_to_users_table.php
```

## 8. Use your own authorized SPC photos

The generated landing page includes a **local campus-style illustration**, **not a claim to depict the actual SPC campus**. For an accurate campus photo, add your authorized photo at `frontend/public/campus-hero.jpg`. In `frontend/src/styles.css`, replace both occurrences of:

```css
url('/campus-silhouette.svg')
```

with:

```css
url('/campus-hero.jpg')
```

Department organizers can upload event posters from **Organizer → Manage events → Create/Edit event**. Student profile photos can be uploaded under **My Profile**. Laravel stores the files on the `public` disk, served by the storage symbolic link.

## 9. Quick system test

1. Log in as **admin**, view the dashboard, departments, users, event management and reports.
2. Log in as **president**, create an event with a capacity of 1 and a poster.
3. Log in as **student**, open the event, RSVP and verify the seat number shown.
4. Try a second student account: the event should report full. Cancel the first RSVP to reopen the seat.
5. Submit a department membership request as a student, then approve it in the organizer dashboard.
6. Publish and pin an announcement as president, then react to it as student.
7. Export an event's attendee list to CSV and use the check-in code when the event is within its check-in window.
8. Run `php artisan events:remind` to process events starting within 24 hours.
9. Export the campus report from **Admin → Analytics** and review **Admin → Audit logs**.
10. Create a course and official organization as Admin, assign an organizer with specific permissions, and verify that a student outside the selected event/announcement audience receives neither content nor notifications.

Run the included PHP feature tests (requires the `pdo_sqlite` PHP extension; the test suite uses SQLite in memory):

```powershell
cd backend
php artisan test
```

Build the React project:

```powershell
cd frontend
npm.cmd run build
```

## 10. Architecture and privacy notes

- **Laravel** is the source of truth for users, permission checks, events, RSVPs, announcements and saved records; the React frontend never directly connects to MySQL.
- **Sanctum API tokens** are used for this local-development build; the frontend keeps the current token in `sessionStorage`. For production, migrate the first-party SPA to **Sanctum's HttpOnly-cookie session flow** and configure HTTPS, secure cookies and CSRF protection. Also configure backups, monitoring, email delivery, real domain, credentials and institutional data policy.
- The public attendee preview masks names; full attendee details are only exposed to authorized organizers.
- RSVPs use a transaction with a locked event row to enforce capacity. Cancelling an RSVP frees the seat.
- Attendance codes are hidden from public event responses and available to organizers via a protected endpoint.
- This is a substantial source implementation, **not a claim of production certification**. The current changes were verified with the Laravel feature suite, a Vite production build, and the additive migration on the configured local MySQL database.

For the complete 1–40 feature mapping, See `docs/FEATURE_CHECKLIST.md`.
