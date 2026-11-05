# GranTrack Student Portal

Vanilla HTML, CSS, and JavaScript. No frameworks or build step.

## Folder structure

    student-dashboard/
    ├── index.html            Dashboard (student home page)
    ├── login.html            Login page
    └── assets/
        ├── css/
        │   ├── theme.css       Colors, sizes, motion (edit colors here)
        │   ├── base.css        Reset, typography, focus rings, reduced motion
        │   ├── layout.css      App shell, sidebar, header, footer, responsive shell
        │   ├── components.css  Cards, panels, alerts, deadlines, reminder, modal
        │   └── login.css       Login page styles
        ├── js/
        │   ├── auth.js         Session guard (loads first, in <head>)
        │   ├── nav.js          Active link, mobile drawer, logout modal
        │   ├── dashboard.js    Greeting and footer year
        │   ├── login.js        Login form logic
        │   └── features/       Page-specific scripts, load only where needed
        │       ├── documents.js  Upload file-name preview
        │       └── profile.js    Profile photo preview
        └── images/
            ├── grantrack-logo.png
            └── grantrack-login-logo.png

## Adding a new page

1. Copy `index.html` and rename it, for example `scholarship.php`.
2. Keep the `<head>` order: `auth.js` first, then the four CSS files.
3. Set `class="nav-link active"` on the matching sidebar link. `nav.js` also
   highlights the current page automatically from the file name.
4. Load `assets/js/nav.js` at the bottom of the page.
5. Add a page-specific script in `assets/js/features/` if the page needs one.

## Colors

Edit `assets/css/theme.css`. Current palette:

- Crimson: `#A4162A` (accent and active state)
- Charcoal: `#1E1E1E` (sidebar)
- Black: `#111111` (headings)
- White: `#FFFFFF` (cards)
- Greys: page background, borders, and muted text

## Notes

- The sidebar links to `.php` pages (scholarship, records, documents, and so on)
  that are not in this folder yet. Those links will work once the pages exist.
- Login is still a frontend demo. Replace the `localStorage` check in
  `assets/js/login.js` and `assets/js/auth.js` with real PHP sessions.
