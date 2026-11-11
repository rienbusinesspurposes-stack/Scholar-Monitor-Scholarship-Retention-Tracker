/* =====================================================
   ALERTS — scholars near ineligibility
   Computed live from grades, requirements and status
   by GTData.alerts(). Thresholds come from Settings.
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var esc = GTUI.esc;
    var list = document.getElementById("alerts-list");
    var levelSelect = document.getElementById("alert-level");
    var search = document.getElementById("alert-search");
    var settings = GTData.settings;

    document.getElementById("rule-text").textContent =
        "Flagged when GWA is below " + settings.retentionGwa + " (critical) or within " + settings.alertMargin +
        " points above it (warning), when a grade is below " + settings.passingGrade +
        ", when a required document is overdue or rejected, or when the scholar is on probation.";

    function render() {
        var all = GTData.alerts();
        var critical = all.filter(function (a) { return a.level === "critical"; }).length;

        document.getElementById("stat-critical").textContent = critical;
        document.getElementById("stat-warning").textContent = all.length - critical;
        document.getElementById("stat-total").textContent = all.length;
        document.getElementById("stat-ok").textContent = GTData.students.length - all.length;

        var term = search.value.trim().toLowerCase();
        var rows = all.filter(function (a) {
            if (levelSelect.value !== "all" && a.level !== levelSelect.value) return false;
            return !term || (a.student.name + " " + a.student.studentNo + " " + a.student.course).toLowerCase().indexOf(term) !== -1;
        });

        if (!rows.length) {
            list.innerHTML = '<div class="empty-state"><h2>' + (all.length ? "No alerts match" : "No scholars are near ineligibility") +
                "</h2><p>" + (all.length ? "Try another filter." : "Everyone is meeting the GWA, grade and document requirements.") + "</p></div>";
            return;
        }

        list.innerHTML = rows.map(function (a) {
            var s = a.student;
            var critical = a.level === "critical";

            return '<article class="alert-card ' + a.level + '">' +
                '<div class="alert-card-head">' +
                '<div class="person"><span class="person-avatar" aria-hidden="true">' + esc(s.name.charAt(0)) + "</span>" +
                "<div><strong>" + esc(s.name) + "</strong><span>" + esc(s.studentNo) + " · " + esc(s.course) + " " + esc(s.year) + " · " + esc(s.scholarship) + "</span></div></div>" +
                '<div class="alert-card-meta"><span class="gwa-pill">GWA <strong>' + (a.gwa === null ? "—" : a.gwa.toFixed(2)) + "</strong></span>" +
                '<span class="badge ' + (critical ? "danger" : "warning") + '">' + (critical ? "Critical" : "Warning") + "</span></div></div>" +
                '<ul class="reason-list">' + a.reasons.map(function (r) {
                    return '<li class="' + r.level + '">' + esc(r.text) + "</li>";
                }).join("") + "</ul>" +
                '<div class="alert-card-actions">' +
                '<a class="btn btn-sm btn-secondary" href="requirements.html">Review documents</a> ' +
                '<a class="btn btn-sm btn-secondary" href="grades.html">View grades</a></div>' +
                "</article>";
        }).join("");
    }

    levelSelect.addEventListener("change", render);
    search.addEventListener("input", render);
    render();
});
