# CampusEvents Hub

```
CampusEvents/
├── Backend/    Laravel API (MySQL, Sanctum)
└── frontend/   React + Vite
    └── src/
        ├── components/   all .jsx files
        ├── css/          one CSS file per section
        ├── utils/        helpers (.ics export)
        └── main.jsx      app state + routing between views
```

## Run the backend
```
cd Backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Create the `campusevents-hubspc` database in phpMyAdmin before running the migrations.
Update the MySQL credentials in `Backend/.env` if they differ from the defaults.
`php artisan migrate --seed` creates the tables and demo data without dropping existing tables.
Demo login: `student@spc.edu.ph` / `password`
New registrations are student accounts and must select one of the active departments: CCS, CBA, COE, COED, or CCJ. Existing BSCRIM and CED accounts are migrated to CCJ and COED. Legacy CAD records remain inactive until reassigned by an administrator. Organizer accounts are managed separately and cannot be created through public student registration.

The Admin dashboard provides user invitations and account management, organization and department CRUD, event moderation without an approval queue, scoped announcements, report resolution, analytics, activity logs, and system settings. Configure `MAIL_*` in `Backend/.env` to send faculty and organizer invitation emails; local development can use the `log` mailer to inspect invitations in the application log. Invitation links expire after 24 hours.

To create the initial admin account, set `BOOTSTRAP_ADMIN_EMAIL` and `BOOTSTRAP_ADMIN_PASSWORD` in `Backend/.env`, then run `php artisan db:seed --class=AdminAccountSeeder` from `Backend`. Use a strong password and change it through **Profile & settings** after logging in. The seeder does not reset an existing admin password.

Event reminders appear in the in-app notification panel about one hour before an event for students with an active RSVP. Keep the app open and signed in to receive them. Students who bookmarked an event are notified in-app if its schedule changes. Organizers can check in attendees from their dashboard using the code shown on the student's **My RSVPs** page; students can view completed check-ins under **Check-in history**.

Organizer tools include event poster uploads, editing, cancellation notices, RSVP exports, printable attendance sheets, and RSVP/view counts. Students can report event listings for staff review and manage their profile and notification preferences under **Profile & settings**. Event details and RSVP lists have print views.

## Run the frontend
```
cd frontend
npm install
cp .env.example .env
npm run dev
```
