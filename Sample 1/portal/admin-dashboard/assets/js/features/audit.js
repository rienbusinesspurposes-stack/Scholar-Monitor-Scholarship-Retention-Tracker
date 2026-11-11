/* =====================================================
   AUDIT LOGS — super admin only
   Tabs: All activity, Sign-ins, Grade changes, Account changes.
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var esc = GTUI.esc;
    var tbody = document.getElementById("audit-body");
    var search = document.getElementById("audit-search");
    var outcome = document.getElementById("audit-outcome");
    var tabs = document.querySelectorAll("[data-tab]");
    var current = "all";

    var CATEGORY_LABEL = {
        login: "Sign-in", grade: "Grade", account: "Account", scholar: "Scholar", settings: "Settings", document: "Document", requirement: "Requirement"
    };

    // Which log categories each tab shows
    var TAB_FILTER = {
        all: null,
        login: ["login"],
        grade: ["grade"],
        account: ["account", "settings", "scholar", "document", "requirement"]
    };

    function visibleEntries() {
        var term = search.value.trim().toLowerCase();
        var cats = TAB_FILTER[current];

        return GTData.audit.filter(function (e) {
            if (cats && cats.indexOf(e.category) === -1) return false;
            if (outcome.value !== "all" && e.outcome !== outcome.value) return false;
            return !term || (e.userName + " " + e.action + " " + e.detail).toLowerCase().indexOf(term) !== -1;
        });
    }

    function updateSummary() {
        var day = Date.now() - 24 * 3600000;
        var logins = GTData.audit.filter(function (e) { return e.category === "login" && new Date(e.ts).getTime() > day; });
        document.getElementById("stat-success").textContent = logins.filter(function (e) { return e.outcome === "success" && e.action === "Signed in"; }).length;
        document.getElementById("stat-failed").textContent = logins.filter(function (e) { return e.outcome === "failed"; }).length;
        document.getElementById("stat-grade").textContent = GTData.audit.filter(function (e) { return e.category === "grade" && new Date(e.ts).getTime() > day; }).length;
        document.getElementById("stat-locked").textContent = GTData.users.filter(function (u) { return u.status === "locked"; }).length;
    }

    function render() {
        var rows = visibleEntries();
        var shown = rows.slice(0, 200);

        document.getElementById("audit-count").textContent =
            rows.length + " entr" + (rows.length === 1 ? "y" : "ies") + (rows.length > 200 ? " (showing latest 200)" : "");

        if (!shown.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No log entries match.</td></tr>';
            return;
        }

        tbody.innerHTML = shown.map(function (e) {
            var failed = e.outcome === "failed";
            return "<tr>" +
                '<td data-label="When" class="muted-cell nowrap">' + esc(GTUI.fmtDateTime(e.ts)) + "</td>" +
                '<td data-label="User"><strong>' + esc(e.userName) + "</strong>" +
                (e.role && e.role !== "-" ? "<br><small>" + esc(GTUI.roleLabel(e.role)) + "</small>" : "") + "</td>" +
                '<td data-label="Event"><span class="badge neutral">' + esc(CATEGORY_LABEL[e.category] || e.category) + "</span> " + esc(e.action) + "</td>" +
                '<td data-label="Details" class="detail-cell">' + esc(e.detail) + "</td>" +
                '<td data-label="Result"><span class="badge ' + (failed ? "danger" : "success") + '">' + (failed ? "Failed" : "Success") + "</span></td></tr>";
        }).join("");
    }

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            current = tab.getAttribute("data-tab");
            tabs.forEach(function (t) {
                var on = t === tab;
                t.classList.toggle("active", on);
                t.setAttribute("aria-selected", on ? "true" : "false");
            });
            render();
        });
    });

    search.addEventListener("input", render);
    outcome.addEventListener("change", render);

    document.getElementById("export-audit").addEventListener("click", function () {
        var lines = [["When", "User", "Role", "Category", "Action", "Details", "Result"]];
        visibleEntries().forEach(function (e) {
            lines.push([e.ts, e.userName, e.role, e.category, e.action, e.detail, e.outcome]);
        });
        var csv = lines.map(function (r) {
            return r.map(function (c) { return '"' + String(c === null || c === undefined ? "" : c).replace(/"/g, '""') + '"'; }).join(",");
        }).join("\r\n");
        var link = document.createElement("a");
        link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        link.download = "baluq-audit-log.csv";
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    });

    updateSummary();
    render();
});
