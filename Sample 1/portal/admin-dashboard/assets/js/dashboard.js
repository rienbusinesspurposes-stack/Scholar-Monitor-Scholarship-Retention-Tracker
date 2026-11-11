/* =====================================================
   DASHBOARD — greeting, footer year, role-based overview
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    // Any element with data-year gets the current year
    document.querySelectorAll("[data-year]").forEach(function (element) {
        element.textContent = new Date().getFullYear();
    });

    var welcome = document.getElementById("welcome-message");
    if (!welcome) return;

    var session = GranTrackSession.get();
    var esc = GTUI.esc;
    var hour = new Date().getHours();
    var greeting = hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
    var first = session.name.replace(/^(Prof\.|Dr\.)\s*/i, "").split(" ")[0];

    welcome.textContent = greeting + ", " + first + "!";

    var settings = GTData.settings;
    var isSuper = session.role === "superadmin";
    var me = GTData.userById(session.id) || session;
    var mySubjects = GTData.subjectsFor(me);

    document.getElementById("dash-label").textContent = isSuper ? "SUPER ADMIN DASHBOARD" : "TEACHER DASHBOARD";
    document.getElementById("dash-lead").textContent = isSuper
        ? "Manage accounts, review activity, and keep the scholarship records accurate."
        : "Enter and update the grades of your students for the current term.";
    document.getElementById("dash-year").textContent = settings.academicYear;

    /* ---------- grade stats over the subjects this user can see ---------- */

    var students = GTData.students;
    var total = 0, graded = 0, sum = 0, failing = 0;

    mySubjects.forEach(function (sub) {
        students.forEach(function (st) {
            total++;
            var grade = GTData.computeGrade(GTData.gradeFor(st.id, sub.code));
            if (grade !== null) {
                graded++;
                sum += grade;
                if (grade < Number(settings.passingGrade)) failing++;
            }
        });
    });

    var average = graded ? (sum / graded).toFixed(2) : "—";
    var pending = total - graded;

    var flagged = GTData.alerts();
    var criticalCount = flagged.filter(function (a) { return a.level === "critical"; }).length;
    var alertText = flagged.length
        ? criticalCount + " critical, " + (flagged.length - criticalCount) + " warning of " + students.length + " scholars."
        : "No scholars near ineligibility.";
    var pendingDocs = Object.keys(GTData.submissionsRaw()).filter(function (k) { return GTData.submissionsRaw()[k].status === "pending"; }).length;

    var cards = [];

    if (isSuper) {
        var users = GTData.users;
        var day = Date.now() - 24 * 3600000;
        var failed24 = GTData.audit.filter(function (e) {
            return e.category === "login" && e.outcome === "failed" && new Date(e.ts).getTime() > day;
        }).length;
        var locked = users.filter(function (u) { return u.status === "locked"; }).length;

        cards = [
            { label: "Accounts", icon: "👤", value: users.length, text: locked ? locked + " locked account" + (locked > 1 ? "s" : "") + " need attention." : "All accounts in good standing.", link: "accounts.html", linkText: "Manage accounts →" },
            { label: "Near Ineligibility", icon: "🔔", value: flagged.length, warn: flagged.length > 0, text: alertText, link: "alerts.html", linkText: "Review alerts →" },
            { label: "Failed Logins (24h)", icon: "🛡", value: failed24, warn: failed24 > 0, text: "Failed sign-in attempts recently.", link: "audit-logs.html", linkText: "Open audit logs →" },
            { label: "Grade Editing", icon: "🔒", value: settings.gradeLock ? "Locked" : "Open", status: settings.gradeLock ? "warning" : "active", text: settings.gradeLock ? "Teachers cannot edit grades." : "Teachers can edit grades.", link: "settings.html", linkText: "Change in settings →" }
        ];
    } else {
        cards = [
            { label: "My Subjects", icon: "📖", value: mySubjects.length, text: "Subjects assigned to you.", link: "grades.html", linkText: "Open grades →" },
            { label: "Near Ineligibility", icon: "🔔", value: flagged.length, warn: flagged.length > 0, text: alertText, link: "alerts.html", linkText: "Review alerts →" },
            { label: "Grades Pending", icon: "🗒", value: pending, warn: pending > 0, text: "Entries still incomplete.", link: "grades.html", linkText: "Enter grades →" },
            { label: "Class Average", icon: "📈", value: average, text: failing + " failing grade" + (failing === 1 ? "" : "s") + " across your subjects." }
        ];
    }

    document.getElementById("summary-grid").innerHTML = cards.map(function (c) {
        var value = c.status
            ? '<span class="status-' + c.status + '">' + esc(c.value) + "</span>"
            : c.warn
                ? '<span class="status-warning">' + esc(c.value) + "</span>"
                : esc(c.value);
        return '<article class="summary-card">' +
            '<div class="card-header"><span class="card-label">' + esc(c.label) + '</span>' +
            '<span class="card-icon" aria-hidden="true">' + c.icon + "</span></div>" +
            "<h3>" + value + "</h3><p>" + esc(c.text) + "</p>" +
            (c.link ? '<a href="' + c.link + '" class="card-link">' + esc(c.linkText) + "</a>" : "") +
            "</article>";
    }).join("");

    /* ---------- left panel: subject progress (teacher) or recent logins (super admin) ---------- */

    var left = document.getElementById("panel-left");

    if (isSuper) {
        var logins = GTData.audit.filter(function (e) { return e.category === "login"; }).slice(0, 5);
        left.innerHTML = panelHeader("Recent Sign-ins", "Successful and failed attempts", "audit-logs.html") +
            logins.map(function (e) {
                var failed = e.outcome === "failed";
                return '<article class="alert-item">' +
                    '<div class="alert-icon ' + (failed ? "warning-icon" : "info-icon") + '" aria-hidden="true">' + (failed ? "!" : "i") + "</div>" +
                    '<div class="alert-details"><h3>' + esc(e.action) + "</h3><p>" + esc(e.detail || e.userName) + "</p>" +
                    "<span>" + esc(GTUI.timeAgo(e.ts)) + " · " + esc(e.userName) + "</span></div>" +
                    '<span class="alert-status ' + (failed ? "pending" : "new") + '">' + (failed ? "Failed" : "OK") + "</span></article>";
            }).join("");
    } else {
        left.innerHTML = panelHeader("My Subjects", "Grade entry progress", "grades.html") +
            (mySubjects.length ? mySubjects.map(function (sub) {
                var done = students.filter(function (st) {
                    return GTData.computeGrade(GTData.gradeFor(st.id, sub.code)) !== null;
                }).length;
                var pct = students.length ? Math.round((done / students.length) * 100) : 0;
                return '<article class="alert-item">' +
                    '<div class="alert-icon info-icon" aria-hidden="true">' + esc(sub.code.slice(0, 2)) + "</div>" +
                    '<div class="alert-details"><h3>' + esc(sub.code) + " · " + esc(sub.title) + "</h3>" +
                    '<div class="progress" aria-label="' + pct + '% graded"><span style="width:' + pct + '%"></span></div>' +
                    "<span>" + done + " of " + students.length + " students graded</span></div>" +
                    '<span class="alert-status ' + (pct === 100 ? "new" : "pending") + '">' + pct + "%</span></article>";
            }).join("") : '<p class="empty-note">No subjects are assigned to you yet. Ask a super admin to assign them.</p>');
    }

    /* ---------- right panel: recent grade changes ---------- */

    var right = document.getElementById("panel-right");
    var changes = GTData.audit.filter(function (e) {
        return e.category === "grade" && (isSuper || e.userId === session.id);
    }).slice(0, 4);

    right.innerHTML = panelHeader(isSuper ? "Recent Grade Changes" : "My Recent Edits", "Latest grade activity", "audit-logs.html", isSuper) +
        (changes.length ? changes.map(function (e) {
            var d = new Date(e.ts);
            return '<article class="deadline-item"><div class="deadline-date"><strong>' +
                String(d.getDate()).padStart(2, "0") + "</strong><span>" +
                d.toLocaleString("en", { month: "short" }) + "</span></div>" +
                '<div class="deadline-details"><h3>' + esc(e.detail.split(" · ").slice(0, 2).join(" · ")) + "</h3>" +
                "<p>" + esc(e.detail.split(" · ").slice(2).join(" · ")) + "</p>" +
                '<span class="deadline-normal">' + esc(e.userName) + "</span></div></article>";
        }).join("") : '<p class="empty-note">No grade changes yet.</p>');

    function panelHeader(title, sub, href, showLink) {
        var link = showLink === false ? "" : '<a href="' + href + '">View All</a>';
        return '<div class="panel-header"><div><h2>' + esc(title) + "</h2><p>" + esc(sub) + "</p></div>" + link + "</div>";
    }

    /* ---------- reminder ---------- */

    document.getElementById("reminder-text").textContent = pendingDocs
        ? pendingDocs + " submitted document" + (pendingDocs === 1 ? " is" : "s are") + " waiting for verification. " + (settings.gradeLock ? "Grade editing is locked." : "")
        : settings.gradeLock
        ? "Grade editing is currently locked by the super admin. Contact them if a correction is needed."
        : "Passing grade is " + settings.passingGrade + ". Scholars need a GWA of " + settings.retentionGwa + " or higher to keep their scholarship.";
});
