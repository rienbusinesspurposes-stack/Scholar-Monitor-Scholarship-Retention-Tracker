/* =====================================================
   FEATURE — My Scholarship
   Status, GWA meter, eligibility checklist, active alerts.
===================================================== */

document.addEventListener("shell:ready", function (event) {
    "use strict";

    var student = event.detail;
    var esc = GTUI.esc;
    var elig = GTStudent.eligibility(student.id);
    var settings = GTData.settings;

    /* ----- details and renewal requirements ----- */
    function row(label, value) {
        return '<div class="kv-row"><span>' + esc(label) + "</span><strong>" + value + "</strong></div>";
    }

    document.getElementById("sch-details").innerHTML =
        row("Scholarship", esc(student.scholarship)) +
        row("Academic year", esc(settings.academicYear)) +
        row("Semester", esc(settings.semester)) +
        row("Course", esc(student.course) + " · " + esc(GTStudent.yearLabel(student.year))) +
        row("Status", '<span class="pill ' + elig.level + '">' + esc(elig.label) + "</span>");

    var PILL = {
        verified: ["success", "Verified"], pending: ["warning", "Pending"],
        rejected: ["danger", "Rejected"], missing: ["danger", "Missing"], overdue: ["danger", "Overdue"]
    };
    var reqs = GTStudent.requirements(student.id).filter(function (i) { return i.req.required; });

    document.getElementById("sch-reqs").innerHTML = reqs.length ? reqs.map(function (i) {
        var p = PILL[i.state];
        return row(i.req.name, '<span class="pill ' + p[0] + '">' + p[1] + "</span>");
    }).join("") : '<p class="empty-note">No required documents.</p>';

    /* ----- GWA meter (scale 60 to 100) ----- */
    var MIN = 60, MAX = 100;
    function pct(v) { return Math.max(0, Math.min(100, (v - MIN) / (MAX - MIN) * 100)); }

    var fill = document.getElementById("meter-fill");
    var mark = document.getElementById("meter-mark");
    mark.style.left = pct(elig.retention) + "%";
    mark.querySelector("span").textContent = "Minimum " + elig.retention;

    var note = document.getElementById("gwa-note");

    if (elig.gwa === null) {
        document.getElementById("gwa-value").textContent = "—";
        fill.style.width = "0%";
        note.textContent = "Your GWA will appear once your midterm and final grades are recorded.";
    } else {
        document.getElementById("gwa-value").textContent = elig.gwa.toFixed(2);
        fill.style.width = pct(elig.gwa) + "%";
        var diff = elig.gwa - elig.retention;

        if (diff < 0) {
            fill.classList.add("danger");
            note.textContent = "You are " + Math.abs(diff).toFixed(2) + " points below the minimum GWA. Improve your grades to keep your scholarship.";
        } else if (diff < elig.margin) {
            fill.classList.add("warning");
            note.textContent = "You are only " + diff.toFixed(2) + " points above the minimum. Be careful with your remaining grades.";
        } else {
            note.textContent = "You are " + diff.toFixed(2) + " points above the minimum. Keep it up!";
        }
    }

    /* ----- checklist ----- */
    document.getElementById("eligibility-list").innerHTML = elig.checks.map(function (c) {
        var cls = c.ok === null ? "none" : c.ok ? "ok" : "bad";
        var sym = c.ok === null ? "–" : c.ok ? "✓" : "✕";
        return '<li><span class="check-mark ' + cls + '" aria-hidden="true">' + sym + "</span>" +
            "<div><strong>" + esc(c.title) + "</strong><span>" + esc(c.detail) + "</span></div></li>";
    }).join("");

    /* ----- alerts ----- */
    var alerts = GTStudent.alerts(student.id);
    var alertBox = document.getElementById("sch-alerts");

    alertBox.innerHTML = alerts.length ? alerts.map(function (a) {
        return '<article class="alert-item"><div class="alert-icon ' + (a.level === "critical" ? "danger-icon" : "warning-icon") +
            '" aria-hidden="true">!</div><div class="alert-details"><h3>' + (a.level === "critical" ? "Urgent" : "Warning") +
            "</h3><p>" + esc(a.text) + "</p></div></article>";
    }).join("") : '<p class="empty-note">No alerts. You meet all scholarship requirements right now.</p>';

    /* ----- rules ----- */
    document.getElementById("rule-passing").textContent = settings.passingGrade;
    document.getElementById("rule-retention").textContent = settings.retentionGwa;
});
