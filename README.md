# Scholar Monitor: Scholarship Retention Tracker

Tracks each scholar's grade requirement and renewal documents, and alerts staff when a scholar is close to losing eligibility. Built with vanilla PHP and MySQL/MariaDB.

## Roles

| Role | What they do |
|---|---|
| **Super Admin** | Creates accounts and changes roles (`super_admin/users.php`), reads the audit log, changes system settings, and can do everything an Admin can. |
| **Admin** | Manages programs, requirements, scholars, grades and documents, and downloads CSV reports. |
| **User (student)** | Signs up, uploads documents, sees their status, deadlines and notifications, and changes their own password. |

## Requirements

- PHP 8.1 or newer with the `pdo_mysql` and `mbstring` extensions
- MySQL or MariaDB with strict SQL mode on. The grade limits (0 to 100) are `CHECK` rules, which MySQL enforces from 8.0.16 and MariaDB from 10.2.1.
- Apache (the folders that must stay private are protected with `.htaccess` files)

## Setup

1. **Put the project folder** in your web root (Laragon: the `www` folder). The app works out its own web address, so the folder name does not matter.

2. **Create the database.** Run the four files in `sql/` in this order, in any MySQL client:

   ```
   sql/001_core.sql
   sql/002_scholarship.sql
   sql/003_updates.sql
   sql/004_settings.sql
   ```

   `001` creates the `scholar_monitor` database. Every file is safe to run again.

3. **Add your settings.** Copy `config/local.php.example` to `config/local.php` and edit it. The defaults (`127.0.0.1`, user `root`, empty password, database `scholar_monitor`) match a fresh Laragon install. `config/local.php` is ignored by git, so passwords never get committed.

4. **Create the first Super Admin**, once, in Git Bash from the project folder:

   ```bash
   php tools/creating_super_admin_powers.php
   ```

   It asks for a username, email, full name and a password of 12 to 72 characters. What you type is visible on screen. It refuses to run if a Super Admin already exists; every later account is created from inside the system.

5. **Log in.** Open the project in your browser. Logging in has two steps: your username and password, then a 6-digit code sent by email.

   While `mail_enabled` is `false` in `config/local.php` (the default), no email is sent. The code is written to the **PHP error log** instead, so read it there during development.

### Turning on email (optional)

In `config/local.php`, set `mail_enabled` to `true` and fill in the `mail_*` values. For Gmail, `mail_password` must be an app password, not your normal password. Set `app_url` to the site's full address if it runs anywhere other than localhost, because it is used for the links in emails.

Email is used for login codes, password reset links, password-changed notices and the staff alerts.

## Using it

**Staff, in the order things normally happen**

1. **Programs:** add a program with its minimum average and a warning margin.
2. **Requirements:** add the documents each program asks for, with an optional due date.
3. **Scholars:** a student signs up on the register page, then staff add that account to a program with a student number, then verify it. A scholar stays Pending until verified.
4. **Grades:** record a term average. Entering the same term again corrects it.
5. **Documents:** approve, or reject with a reason. The student sees the reason and uploads again.
6. **Reports:** download scholars, grades or documents as CSV files for Excel. Each download is logged.

A scholar's status comes from their latest grade and the program's rule:

| Status | Meaning |
|---|---|
| Pending | Added but not verified yet. No grades can be recorded. |
| Active | Latest average is at least the minimum plus the warning margin. |
| At Risk | Latest average is at or above the minimum but within the warning margin. |
| Ineligible | Latest average is below the minimum. |
| Ended | Staff ended the scholarship with a reason. It can be reinstated. |

On a scholar's page staff can also fix the student number, move the scholar to another program, end the scholarship or reinstate it.

**Super Admin extras:** Users (create accounts, change roles, switch accounts on or off), Audit log, and Settings (how many days before a due date reminders start, default 7; and idle logout minutes, default 30).

**Students:** Dashboard, My Scholarship, Academics Record, Documents (PDF, JPG or PNG, up to 5 MB), Notifications, Deadlines, and Profile (change password).

## Each new term: renewal documents

A scholar uploads one file per requirement, and an approved document cannot be replaced. So renewal documents that repeat every term get a new requirement each term:

1. On **Requirements**, add the document with the term in its name, for example `Certificate of Registration 2027 1st Sem`, and set its due date. Every scholar in that program sees it straight away.
2. **Switch off** the previous term's requirement. If you forget, a student who never uploaded it keeps getting overdue reminders.

Switched-off requirements disappear from student pages and reminders. Their uploads are kept.

## Daily alerts

`tools/send_alerts.php` sends:

- **Students:** a reminder for each required document that is missing or rejected, starting a set number of days before its due date (the Settings value) and once more when it is overdue.
- **Staff:** one email to every active Admin and Super Admin listing scholars who are At Risk or Ineligible. A scholar is mentioned again only when their latest grade or status changes.

Run it once a day. Running it twice in a day is harmless.

```bash
php tools/send_alerts.php
```

It must be scheduled, because nothing runs it automatically. On Windows use Task Scheduler with the full path to `php.exe` and the full path to `tools\send_alerts.php`. On a Linux server use cron, for example `0 7 * * * php /path/to/project/tools/send_alerts.php`. It exits with an error code when an email fails, and that email is tried again on the next run.

## Self-checks

Run from Git Bash in the project folder. Each uses your real database but does all its work inside a transaction that is rolled back, so nothing is kept. `tools/test_alerts.php` uses a fake mail function, so it never sends email.

```bash
php tools/test_core.php       # environment, folder protection, accounts, lockout, login codes, CSRF, sessions
php tools/test_status.php     # the grade rule, ending and reinstating a scholarship
php tools/test_alerts.php     # student reminders and the staff digest
php tools/test_reports.php    # the CSV reports
php tools/test_settings.php   # Super Admin settings (needs sql/004_settings.sql)
```

## Security notes

- `config`, `includes`, `sql`, `tools` and `uploads/documents` are blocked from the web by `.htaccess`. `tools/test_core.php` checks this.
- Uploaded files are saved under random names, and their type is read from the file's contents, not its name.
- Logins are rate limited, login codes are single-use, and changing a password logs out every other session.
- Every important action is written to the audit log.

## Known limits

- HTTPS is detected from the server variable only, and only the connecting address is trusted, so behind a proxy the settings in `includes/security.php` need changing.
- Classmates behind one shared address share the per-address login limit (`LOGIN_MAX_FAILS_PER_IP`).
- A scholar's "latest grade" is the most recently entered record, not the latest term name.
- A document reminder stays unread after the student uploads the document.
- Staff have no profile page. Admins and Super Admins get a new password through "Forgot password".