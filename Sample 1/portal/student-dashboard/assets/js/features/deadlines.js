/* =====================================================
   FEATURE — Deadlines
   Requirement due dates, grouped by urgency.
===================================================== */

document.addEventListener("shell:ready", function (event) {
    "use strict";

    var student = event.detail;
    var esc = GTUI.esc;
    var items = GTStudent.requirements(student.id);

    var groups = [
        { title: "Overdue", badge: "danger", test: function (i) { return i.state === "overdue" || (i.state === "rejected" && i.daysLeft < 0); } },
        { title: "Due within 7 days", badge: "warning", test: function (i) { return GTStudent.needsAction(i) && i.daysLeft >= 0 && i.daysLeft <= 7; } },
        { title: "Upcoming", badge: "neutral", test: function (i) { return GTStudent.needsAction(i) && i.daysLeft > 7; } },
        { title: "Submitted", badge: "success", test: function (i) { return i.state === "pending" || i.state === "verified"; } }
    ];

    var used = [];
    var html = groups.map(function (g) {
        var rows = items.filter(function (i) { return used.indexOf(i) === -1 && g.test(i); });
        rows.forEach(function (i) { used.push(i); });
        if (!rows.length) return "";

        return '<section class="deadline-group"><h2 class="group-title">' + g.title +
            ' <span class="pill ' + g.badge + '">' + rows.length + "</span></h2>" +
            rows.map(function (item) {
                var d = GTStudent.dateParts(item.req.dueDate);
                var info = GTStudent.dueInfo(item);
                var tile = info.cls === "late" ? " late" : info.cls === "done" ? " done" : "";
                return '<article class="deadline-row">' +
                    '<div class="deadline-date' + tile + '"><strong>' + d.day + "</strong><span>" + d.mon + "</span></div>" +
                    '<div class="deadline-details"><h3>' + esc(item.req.name) + "</h3>" +
                    "<p>" + (item.req.required ? "Required document" : "Optional document") + " · " + esc(GTData.settings.semester) + ", AY " + esc(GTData.settings.academicYear) + "</p>" +
                    '<div class="when ' + info.cls + '">' + esc(info.text) + "</div></div>" +
                    (GTStudent.needsAction(item) ? '<a class="btn btn-primary btn-sm" href="documents.html">Go to documents</a>' : "") +
                    "</article>";
            }).join("") + "</section>";
    }).join("");

    var box = document.getElementById("deadline-groups");
    box.innerHTML = html;
    document.getElementById("deadline-empty").hidden = !!html;
});
