# GranTrack Admin Portal

Vanilla HTML, CSS, and JavaScript. No frameworks or build step.
Same theme and structure as the student portal (`student-dashboard/`).

## Roles

The "Admin" role in the team's role table is the **Teacher** here
(`role: "teacher"`). "Sup_Admin" is `role: "superadmin"`. The "User" role is
the student portal in `student-dashboard/`.

| Access feature (from the role table)                         | Sup_Admin | Admin (Teacher) |
|--------------------------------------------------------------|:---------:|:---------------:|
| Change system settings, mail, security options, updates      |    yes    |                 |
| Create accounts, change roles, disable or delete accounts    |    yes    |                 |
| View audit logs, failed and successful logins                |    yes    |                 |
| Add and edit scholar records (`scholars.html`)               |    yes    |       yes       |
| Add and edit requirements (`requirements.html`)              |    yes    |       yes       |
| Add and edit grades (`grades.html`)                          |    yes    | yes, own subjects |
| Verify documents and scholarship status (`requirements.html`)|    yes    |       yes       |
| Receive near-ineligibility alerts (`alerts.html`)            |    yes    |       yes       |
| See own requirements, status, and alerts                     |    yes    |       yes       |

Extras beyond the table: only a super admin can delete scholars and
requirements, assign subjects to teachers, and lock grading for teachers.

Pages that are super admin only use `data-roles="superadmin"` on the
`auth.js` script tag, and their sidebar group is marked the same way.

## Near-ineligibility alerts

`GTData.alerts()` flags a scholar when any of these is true (thresholds are in
System Settings): GWA below the retention GWA (critical) or within the early-warning
margin above it (warning), a grade below the passing grade, a required document
that is overdue (critical), due within 7 days, or rejected, or probation status.

## Demo accounts

- Teacher: `teacher@mcc.edu.ph` / `teacher123`  (CS101, CS102)
- Teacher: `ana.lopez@mcc.edu.ph` / `teacher123`  (MATH101, ENG101, PE101)
- Super Admin: `superadmin@mcc.edu.ph` / `admin123`

## Folder structure

    admin-dashboard/
    ├── login.html          Admin login
    ├── index.html          Dashboard (changes with role)
    ├── grades.html         Teacher / admin grade editing
    ├── scholars.html       Scholar records
    ├── requirements.html   Verify documents, manage requirements, verify status
    ├── alerts.html         Near-ineligibility alerts
    ├── accounts.html       Super admin: accounts
    ├── audit-logs.html     Super admin: audit trail
    ├── settings.html       Super admin: system settings
    └── assets/
        ├── css/
        │   ├── theme.css       Colors, sizes, motion (shared with student portal)
        │   ├── base.css        Reset, typography
        │   ├── layout.css      App shell, sidebar, header
        │   ├── components.css  Cards, panels, modal
        │   ├── admin.css       NEW: tables, forms, buttons, badges, toasts
        │   └── login.css       Login page
        └── js/
            ├── auth.js         Session guard with roles (head)
            ├── data.js         Demo data store (localStorage) = future PHP API
            ├── ui.js           Toasts, confirm dialog, modals, formatting
            ├── nav.js          Active link, drawer, logout, role-based menu
            ├── dashboard.js    Role-based overview
            ├── login.js        Login + failed-attempt lockout
            └── features/       grades, scholars, requirements, alerts, accounts, audit, settings

## Grading rules (editable in System Settings)

- Grade = average of midterm and final. Incomplete until both are entered.
- Passing grade default 75. Scholarship retention GWA default 85.
- Super admin can lock grade editing for teachers.

## Moving to PHP

This is a frontend demo. Data and passwords live in the browser's localStorage.
Replace `data.js` and `auth.js` with PHP sessions and MySQL, and enforce roles
on the server: hiding a button in JavaScript is not security. Hash passwords
with `password_hash()`.
