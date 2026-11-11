/* =====================================================
   FEATURE — Academic Records
   Grades per subject, entered by teachers (admin portal).
===================================================== */

document.addEventListener("shell:ready", function (event) {
    "use strict";

    var student = event.detail;
    var esc = GTUI.esc;
    var rows = GTStudent.records(student.id);
    var elig = GTStudent.eligibility(student.id);

    function score(v) { return v === null ? "—" : v; }

    var REMARK = {
        passed: ["success", "Passed"],
        failed: ["danger", "Failed"],
        incomplete: ["neutral", "Incomplete"]
    };

    /* ----- table ----- */
    var counted = rows.filter(function (r) { return r.grade !== null; });
    var units = counted.reduce(function (n, r) { return n + r.units; }, 0);
    var totalUnits = rows.reduce(function (n, r) { return n + r.units; }, 0);
    var passed = rows.filter(function (r) { return r.remark === "passed"; }).length;

    document.getElementById("records-body").innerHTML = rows.length ? rows.map(function (r) {
        var remark = REMARK[r.remark];
        return "<tr>" +
            '<td data-label="Subject"><strong>' + esc(r.title) + "</strong><br><small>" + esc(r.code) + " · " + esc(r.teacher) + "</small></td>" +
            '<td data-label="Units">' + r.units + "</td>" +
            '<td data-label="Midterm">' + score(r.midterm) + "</td>" +
            '<td data-label="Final">' + score(r.final) + "</td>" +
            '<td data-label="Grade" class="grade-cell' + (r.grade === null ? " na" : "") + '"><strong>' + (r.grade === null ? "—" : r.grade.toFixed(2)) + "</strong></td>" +
            '<td data-label="Remarks"><span class="pill ' + remark[0] + '">' + remark[1] + "</span></td></tr>";
    }).join("") : '<tr><td colspan="6" class="table-empty">No subjects found.</td></tr>';

    document.getElementById("rec-summary").innerHTML =
        '<span>Current GWA <strong>' + (elig.gwa === null ? "—" : elig.gwa.toFixed(2)) + "</strong></span>" +
        "<span>Needed <strong>" + elig.retention + "</strong></span>" +
        "<span>Units graded <strong>" + units + " / " + totalUnits + "</strong></span>" +
        "<span>Passed <strong>" + passed + " / " + rows.length + "</strong></span>";

    /* ----- footer ----- */
    var latest = rows.map(function (r) { return r.updatedAt; }).filter(Boolean).sort().pop();
    document.getElementById("rec-updated").textContent = latest
        ? "Last updated " + GTUI.fmtDateTime(latest) + "."
        : "No grades have been recorded yet.";

});
