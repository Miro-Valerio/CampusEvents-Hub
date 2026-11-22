# CampusEvents Hub: Original 40-feature implementation audit

The React route (page) is followed by the API/data workflow. Separate administrative and membership requirements are below.

| # | Original feature | Page / implemented workflow |
|---:|---|---|
| 1 | Sign up, log in and log out | `/register`, `/login`; `POST /api/register`, `POST /api/login`, `POST /api/logout` |
| 2 | Browse upcoming events on the home page | `/`, `/events`; `GET /api/events` |
| 3 | Search by title, venue or organization | `/events`, backend `search` filter |
| 4 | Filter by date, category, organizer | `/events`, `date`, `category`, `department_id` filters |
| 5 | Sort by soonest, newest or popular | `/events`, backend `sort` |
| 6 | Event page with poster, venue, time and rules | `/events/:id`, `GET /api/events/:id` |
| 7 | Seat counter (42 of 50) | Event cards and details, active RSVP counts |
| 8 | Organizer page | `/departments/:id`, linked from event details |
| 9 | Friendly no-results empty state | `/events` UI `EmptyState` |
| 10 | Error and retry | Load/retry UI and persistent form errors with resubmission |
| 11 | RSVP in one tap | Event detail action, `POST /api/events/:id/rsvp` |
| 12 | Cancel RSVP and reopen seat | `/my-events`, `DELETE /api/events/:id/rsvp` |
| 13 | Block RSVP when full | Server transaction/seat limit + UI message |
| 14 | Seat number confirmation | Event detail confirmation card + My RSVPs |
| 15 | View my RSVPs | `/my-events`, `GET /api/me/activity` |
| 16 | Bookmark an event | Event details, `/bookmarks`, bookmark toggle API |
| 17 | Add an event to my calendar | Event details, `/my-calendar`, calendar toggle API |
| 18 | Monthly calendar of events | `/calendar`, `GET /api/events/calendar?year=&month=` |
| 19 | See past-event archive with photos | `/archive`, `GET /api/events?archive=1` (event posters when uploaded) |
| 20 | See who is going before RSVP | Masked names via `GET /api/events/:id/attendees-preview` |
| 21 | Event reminder before event starts | `php artisan events:remind`, schedule every five minutes |
| 22 | Notification when a saved event changes time | `OrganizerController::update`; in-app `event_update` notification |
| 23 | Check in at door with code from officer | `/events/:id`; `POST /api/events/:id/check-in` |
| 24 | View check-in history | `/attendance` and `/my-events` |
| 25 | Officer-only campus announcements feed | `/organizer`; authorized create/update/delete endpoints |
| 26 | Pin announcement at top | Organizer form, `is_pinned` sort |
| 27 | Highlight unread announcements | `/announcements`, read tracking |
| 28 | Notification when new announcement posted | `AnnouncementController::store`, notifications |
| 29 | Reactions with emoji | `/announcements`, reaction toggle endpoint |
| 30 | Filter announcements by course or organization | `/announcements`, course/department filters |
| 31 | Create event with poster, venue, seat limit | Organizer → Manage events, multipart poster upload |
| 32 | Edit event details | Organizer → Manage events, update API |
| 33 | Cancel event and notify RSVP holders | Organizer → Manage events; cancellation + notifications |
| 34 | Report suspicious event listing | Event details report form; Admin → Reports |
| 35 | See RSVP totals and views on dashboard | Organizer → Analytics / Admin → Analytics |
| 36 | Export RSVP list | Organizer → Attendees → Export CSV |
| 37 | Print an event poster page | `/events/:id` print button and print stylesheet |
| 38 | Notification settings | `/settings`, `PUT /api/me/preferences` |
| 39 | All caught up notice | `/notifications` when none unread |
| 40 | Manage name, course, photo | `/profile`, `POST /api/me/profile` |

**Numbering clarification:** The supplied screenshot uses #1–#40, including **#1 = Sign up/log in/log out**, **#2 = browse** and **#3 = search**. The expanded narrative roadmap renumbered some items (for example, it used #2 for logout and #3 for browse). This checklist follows the actual original screenshot's feature order so every original requirement is present.

## Additional roadmap modules

- Public landing page: hero, events, departments, How It Works, FAQ, contact-neutral footer and responsive navigation.
- Authentication: password reset and account settings; public registration creates students only.
- Department membership: correct-department restrictions, pending/approved/rejected states, cancellation and review with notifications.
- Department presidents: manage events, announcements and membership; adjust permissions of assigned officers.
- Department officers: may only access actions explicitly granted via `officer_permissions`.
- Admin: department CRUD, accounts and roles, officers/presidents, event monitoring/cancellation, department announcements, reports, campus-report CSV, statistics, audit logs and system settings.
- Security: server-side role checks, resource ownership checks, request validation, seat locking, private attendance codes, upload type/size restrictions, throttled login/registration/reset, optional authorized photo uploads.
- Application tests: server-side tests for public listing, registration role restrictions, one-seat RSVP/cancel/reuse, permission checks and department membership rules.

### Main data tables (19)

`departments`, `users`, `password_reset_tokens`, `sessions`, `personal_access_tokens`, `events`, `rsvps`, `bookmarks`, `calendar_entries`, `event_check_ins`, `announcements`, `announcement_reads`, `announcement_reactions`, `user_notifications`, `memberships`, `event_reports`, `audit_logs`, `system_settings`, `migrations`.
