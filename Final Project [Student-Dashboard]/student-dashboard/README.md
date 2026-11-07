# BALUQ Student Scholarship Monitoring Portal (frontend)

Vanilla HTML, CSS, and JavaScript.

## Folder structure

    student-dashboard/
    ├── index.html                  Dashboard
    ├── scholarship.html            My Scholarship
    ├── academics-record.html       Grades
    ├── documents.html              My Documents
    ├── notifications.html          Notifications
    ├── deadlines.html              Deadlines
    ├── login.html                  Login
    └── assets/
        ├── css/
        │   ├── theme.css           Palette and sizes (edit colors here)
        │   ├── base.css            Reset, typography, focus ring
        │   ├── layout.css          Sidebar, top bar, profile menu, footer
        │   ├── components.css      Cards, stepper, lists, tables, buttons, modal
        │   └── login.css           Login card
        ├── js/
        │   ├── auth.js             Session guard (loads first, in <head>)
        │   ├── nav.js              Sidebar toggle, profile menu, logout modal
        │   ├── dashboard.js        Greeting and year
        │   ├── login.js            Login form
        │   └── features/
        │       ├── documents.js    Upload file-name preview
        │       └── profile.js      Profile photo preview
        └── images/
            └── baluq-logo.png

## Palette

| Name          | Hex       | Used for                                  |
|---------------|-----------|-------------------------------------------|
| Midnight      | #2D4E71   | Sidebar, primary buttons, banners         |
| Pastel Blue   | #AABED6   | Hover, active accents, avatar             |
| White         | #FFFFFF   | Headings, text                            |
| Grey Marble   | #B9B6AD   | Muted text                                |
| Charcoal      | #1E1E1E   | Cards, borders                            |
| Black         | #111111   | Page background                           |
| Tomato Red    | #E4472F   | Urgent and missing (status only)          |
| Grass         | #40916C   | Completed and active (status only)        |
| Butter Yellow | #F2D36B   | Pending and warning (status only)         |

## Adding a page

1. Copy any page, for example `deadlines.html`.
2. Keep the `<head>` order: `auth.js` first, then the CSS files.
3. Set `class="nav-link active"` on the matching sidebar link (`nav.js` also does this automatically from the file name).
4. Load `assets/js/nav.js` and `assets/js/dashboard.js` at the bottom.
5. Load a file from `assets/js/features/` only on pages that need it.

## Notes

- Profile (`profile.php`) and Settings (`settings.php`) are linked but not included in this folder.
- Login is a frontend demo. Replace the `localStorage` check in `login.js` and `auth.js` with real PHP sessions.
