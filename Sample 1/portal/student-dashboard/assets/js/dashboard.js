/* =====================================================
   DASHBOARD — greeting, summary cards, recent alerts,
   next deadlines. Data comes from GTStudent (student.js).
===================================================== */

document.addEventListener("DOMContentLoaded", function () {
    var welcome = document.getElementById("welcome-message");
    if (!welcome) return;

    var hour = new Date().getHours();
    welcome.dataset.greeting = hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
});

document.addEventListener("shell:ready", function (event) {
    "use strict";

    var student = event.detail;
    var esc = GTUI.esc;
    var welcome = document.getElementById("welcome-message");
    if (!welcome) return;

    welcome.textContent = (welcome.dataset.greeting || "Hello") + ", " + GTStudent.firstName(student) + "!";

    var elig = GTStudent.eligibility(student.id);
    var reqs = GTStudent.requirements(student.id);
    var actions = reqs.filter(function (i) { return i.req.required && GTStudent.needsAction(i); });
    var upcoming = reqs.filter(function (i) { return GTStudent.needsAction(i) && i.daysLeft >= 0; });

    /* ----- warning banner ----- */
    var banner = document.getElementById("risk-banner");
    if (elig.level === "danger" || elig.level === "warning" && elig.label === "Probation") {
        banner.hidden = false;
        banner.querySelector("strong").textContent = elig.label === "Probation"
            ? "Your scholarship is on probation"
            : "Your scholarship is at risk";
    }

    /* ----- profile card ----- */
    document.getElementById("profile-course").textContent = student.course + " · " + GTStudent.yearLabel(student.year);
    var badge = document.getElementById("profile-badge");
    badge.textContent = student.scholarship;
    badge.classList.add("level-" + elig.level);

    /* ----- summary cards ----- */
    var pillClass = { success: "status-active", warning: "status-warning", danger: "status-danger", neutral: "status-neutral" };
    var statusEl = document.getElementById("sum-status");
    statusEl.innerHTML = '<span class="' + pillClass[elig.level] + '">' + esc(elig.label) + "</span>";
    document.getElementById("sum-status-text").textContent = {
        success: "Your scholarship is in good standing.",
        warning: "Check the items that need your attention.",
        danger: "Please resolve your alerts as soon as possible.",
        neutral: "Contact the administrator about your scholarship."
    }[elig.level];

    document.getElementById("sum-gwa").textContent = elig.gwa === null ? "—" : elig.gwa.toFixed(2);
    document.getElementById("sum-gwa-text").textContent = elig.gwa === null
        ? "No complete grades recorded yet."
        : "Minimum to keep the scholarship: " + elig.retention + ".";

    var missing = document.getElementById("sum-missing");
    missing.innerHTML = actions.length
        ? '<span class="status-warning">' + actions.length + "</span>"
        : '<span class="status-active">0</span>';
    document.getElementById("sum-missing-text").textContent = actions.length
        ? "Required document" + (actions.length > 1 ? "s" : "") + " needing your action."
        : "All required documents are submitted.";

    document.getElementById("sum-deadlines").textContent = upcoming.length;

    /* ----- recent alerts ----- */
    var icons = {
        critical: ["!", "danger-icon"], danger: ["!", "danger-icon"], warning: ["!", "warning-icon"],
        success: ["✓", "success-icon"], info: ["i", "info-icon"]
    };
    var list = GTStudent.notifications(student.id).slice(0, 3);
    var alertBox = document.getElementById("recent-alerts");

    alertBox.innerHTML = list.length ? list.map(function (n) {
        var icon = icons[n.level] || icons.info;
        return '<article class="alert-item">' +
            '<div class="alert-icon ' + icon[1] + '" aria-hidden="true">' + icon[0] + "</div>" +
            '<div class="alert-details"><h3>' + esc(n.title) + "</h3><p>" + esc(n.text) + "</p>" +
            "<span>" + (n.ts ? GTUI.fmtDate(n.ts) : "Current") + "</span></div>" +
            (n.read ? "" : '<span class="alert-status new">New</span>') + "</article>";
    }).join("") : '<p class="empty-note">No alerts right now.</p>';

    /* ----- next deadlines ----- */
    var next = reqs.filter(GTStudent.needsAction).slice(0, 3);
    var deadlineBox = document.getElementById("next-deadlines");

    deadlineBox.innerHTML = next.length ? next.map(function (item) {
        var d = GTStudent.dateParts(item.req.dueDate);
        var info = GTStudent.dueInfo(item);
        var cls = info.cls === "ok" ? "deadline-normal" : "deadline-warning";
        return '<article class="deadline-item">' +
            '<div class="deadline-date' + (info.cls === "late" ? " late" : "") + '"><strong>' + d.day + "</strong><span>" + d.mon + "</span></div>" +
            '<div class="deadline-details"><h3>' + esc(item.req.name) + "</h3>" +
            "<p>" + (item.req.required ? "Required document" : "Optional document") + "</p>" +
            '<span class="' + cls + '">' + esc(info.text) + "</span></div></article>";
    }).join("") : '<p class="empty-note">You are all caught up. No pending deadlines.</p>';
});
