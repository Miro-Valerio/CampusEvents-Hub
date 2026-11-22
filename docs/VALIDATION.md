# Validation record

Checks performed on the source bundle before packaging:

- PHP syntax: `php -l` passed for all 40 PHP files (including the Laravel migration, routes, controllers, models and tests).
- JavaScript / JSX syntax: all 16 frontend `.js`/`.jsx` files parsed without diagnostics using the TypeScript JSX parser.
- Source imports: all frontend relative imports resolve to existing files.
- Project manifest: `backend/composer.json` and `frontend/package.json` parse as valid JSON.
- SQL: 19 defined tables and all `REFERENCES` tables exist in the schema.
- Image references: both user-supplied reference images are retained in this `docs` folder.

## Local validation after replacing the project source

- Composer dependencies installed successfully; Laravel reports version 12.72.0.
- npm dependencies installed and `npm run build` completed successfully.
- `php artisan test`: 15 tests passed (122 assertions) against the test suite's in-memory SQLite database, including membership, report, RSVP, check-in, announcement, role, privacy, and reminder flows.
- PHP syntax: all 48 Laravel PHP source files passed `php -l`.
- The SQL schema declares 19 tables, 19 primary keys, and 24 foreign-key constraints; every referenced table exists.
- MySQL was reachable using the existing `.env` connection settings. The previous database was exported, restored to a temporary database, and verified to have matching table and row counts before replacement.
- The new SQL schema was imported and verified in `campusevents-hubspc`: 19 tables, 19 primary keys, and 24 foreign keys. Safe local demo seed data was added.
- Before notification schema changes, the current database was backed up to `campusevents-hubspc-before-notification-updates-20261117-0130.sql` in the project backup directory under `C:\Users\ADMIN\Documents`.
- Only the additive `2026_11_17_000001_add_notification_metadata` migration was applied. It adds `event_reports.category` and a nullable, uniquely indexed per-user `user_notifications.dedupe_key`. Existing counts remained 0 event reports and 2 notifications before and after; no notification records were removed.
- The frontend page and proxied Laravel health, events, and departments endpoints returned HTTP 200 using the new source and database.

These checks do **not** establish that all 40 features have been exercised end-to-end in a browser. Notification recipients and authorization were tested through Laravel feature tests using isolated SQLite data; a complete multi-role browser workflow was not performed.

The pre-replacement database backup is `campusevents-hubspc-before-replacement.sql` in the project backup directory created under `C:\Users\ADMIN\Documents`.
