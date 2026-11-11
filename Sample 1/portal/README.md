# MCC BALUQ: Admin + Student portals (connected)

    portal/
    ├── login.html            THE one login page (staff and students)
    ├── index.html            Redirects to login.html
    ├── shared/css/           theme.css (palette: edit colors here), base, layout,
    │                         components, dark.css (surface fixes), login.css
    ├── shared/img/           BALUQ logos and favicon
    ├── shared/js/            Used by BOTH portals (edit once)
    │   ├── login.js          Single sign-in: finds out if it is staff or a scholar
    │   ├── data.js           Shared data store + cross-tab sync
    │   ├── files.js          Uploaded documents (IndexedDB)
    │   ├── ui.js             Toasts, modals, date formatting
    │   └── autorefresh.js    Reloads display-only pages when the other portal saves
    ├── admin-dashboard/
    └── student-dashboard/

## Run it (important)

Both portals must be served from the same address and opened in the same browser,
otherwise they cannot share data. From this folder:

    php -S localhost:8000        (or: python3 -m http.server 8000, or VS Code Live Server)

Then open http://localhost:8000 (it goes to `login.html`).

## Theme

BALUQ dark theme: black page, charcoal cards, midnight blue (#2D4E71) sidebar and
buttons, pastel blue (#AABED6) accents. Sidebar is on the right; the three-line button
hides it on desktop and opens a drawer on phones. Change colors in `shared/css/theme.css`;
both portals use the same CSS files. Fonts: Inter and Plus Jakarta Sans.

## One login

`login.html` checks the email against staff accounts first, then scholars, and sends
the person to `admin-dashboard/` or `student-dashboard/`. Log out and expired or
wrong-role sessions all return to this page. The old `login.html` files inside each
portal just redirect here. `login.js` loads `student-dashboard/assets/js/student.js`
for the scholar check.

## What is connected

1. Student uploads a document (My Documents) -> admin sees it right away in
   Requirements > Submissions as Pending, and can click **View file** to open it.
2. Admin verifies or rejects (with a reason) -> the student's Documents,
   Notifications and Dashboard update on their own.
3. Admin enters grades or changes settings -> student's GWA, alerts and scholarship
   status change. Student login and uploads appear in the admin Audit Logs.

## Demo accounts

- Student: `gabriela.1004@mcc.edu.ph` (and others on the student login page) / `student123`
- Teacher: `teacher@mcc.edu.ph` / `teacher123`
- Super Admin: `superadmin@mcc.edu.ph` / `admin123`

## Still a frontend demo

Data and files live in this browser only. For the real system, the PHP + MySQL backend
replaces `shared/js/data.js` and `files.js` (see each file's header for the endpoints),
and roles, passwords (`password_hash`) and upload checks must be enforced on the server.
