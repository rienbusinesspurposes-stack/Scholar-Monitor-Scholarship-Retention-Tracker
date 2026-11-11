# GranTrack Student Portal (User side)

Vanilla HTML, CSS, and JavaScript. No frameworks or build step.
Same theme as the admin portal, and it reads the **same data**: when an admin
verifies a document or enters a grade, the student sees it.

## Run it

Open `login.html`. For the student and admin sides to share data, serve both
folders from the same origin (e.g. `php -S localhost:8000` in the parent folder,
or VS Code Live Server). Both use localStorage key `grantrackAdminDB_v2`.

## Demo accounts

Emails look like `juan.1001@mcc.edu.ph`, `gabriela.1004@mcc.edu.ph`.
Password for all: `student123`. The login page has one-click demo buttons.

## Pages (role table: "User")

| Page                    | What the student can do                                      |
|-------------------------|--------------------------------------------------------------|
| `index.html`            | Overview: status, GWA, missing documents, alerts, deadlines  |
| `scholarship.html`      | Status, GWA vs minimum meter, eligibility checklist, alerts  |
| `academics-record.html` | Grades per subject (read-only)                               |
| `documents.html`        | See status of each requirement, upload or replace a file     |
| `notifications.html`    | Alerts, document reviews, grade updates; mark as read        |
| `deadlines.html`        | Due dates grouped: overdue, this week, upcoming, submitted   |
| `profile.html`          | Scholar record (read-only), contact details, photo, password |

## Files

    assets/css/  theme, base, layout, components (original)
                 ui.css     buttons, tables, forms, badges, toasts (same as admin)
                 pages.css  styles for the new student pages
    assets/js/   auth.js      session guard (head)
                 data.js      COPY of admin data store (keep identical)
                 student.js   student API on top of GTData  <- replace with PHP calls
                 ui.js        COPY of admin helpers (toast, modal, dates)
                 shell.js     fills name, avatar, unread counts on every page
                 nav.js, dashboard.js, login.js
                 features/    one script per page

## Adding a new page

Copy any page, keep the `<head>` order (auth.js first), set the title, and load
`data.js`, `student.js`, `ui.js`, `nav.js`, `shell.js`, then your feature script.
Listen for `shell:ready` (gives the signed-in student) before rendering.

## Moving to PHP

Frontend demo only. Replace `GTStudent` functions in `student.js` with API calls
(login, requirements, uploadDocument, ...). Hash passwords with `password_hash()`,
check the session and the student id on the server, and validate uploads
(type, size) on the server too. Only the file name is saved in this demo.
