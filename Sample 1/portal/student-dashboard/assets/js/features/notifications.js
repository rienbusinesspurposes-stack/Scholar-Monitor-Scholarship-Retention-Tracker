/* =====================================================
   FEATURE — Notifications
   Built from the shared data: scholarship alerts, document
   reviews and grade updates. "Read" is remembered per
   student in localStorage.
===================================================== */

document.addEventListener("shell:ready", function (event) {
    "use strict";

    var student = event.detail;
    var esc = GTUI.esc;

    var ICON = {
        critical: ["!", "danger-icon"], danger: ["!", "danger-icon"], warning: ["!", "warning-icon"],
        success: ["✓", "success-icon"], info: ["i", "info-icon"]
    };

    var list = document.getElementById("notif-list");
    var empty = document.getElementById("notif-empty");
    var markAll = document.getElementById("mark-all");
    var tabs = document.querySelectorAll("#notif-tabs .tab");
    var filter = "all";

    function matches(n) {
        return filter === "all" || (filter === "unread" ? !n.read : n.kind === filter);
    }

    function render() {
        var all = GTStudent.notifications(student.id);
        var unread = all.filter(function (n) { return !n.read; });

        document.getElementById("tab-unread-count").textContent = unread.length;
        markAll.disabled = unread.length === 0;

        var shown = all.filter(matches);
        empty.hidden = shown.length > 0;
        list.hidden = shown.length === 0;

        list.innerHTML = shown.map(function (n) {
            var icon = ICON[n.level] || ICON.info;
            var tag = n.read ? "div" : "button type=\"button\"";
            var close = n.read ? "div" : "button";
            return "<" + tag + ' class="notif-item' + (n.read ? "" : " unread") + '" data-id="' + esc(n.id) + '">' +
                '<div class="alert-icon ' + icon[1] + '" aria-hidden="true">' + icon[0] + "</div>" +
                '<div class="notif-body"><h3>' + esc(n.title) + "</h3><p>" + esc(n.text) + "</p>" +
                "<time>" + (n.ts ? GTUI.fmtDateTime(n.ts) + " · " + GTUI.timeAgo(n.ts) : "Current status") + "</time></div>" +
                (n.level === "critical" || n.level === "danger" ? '<span class="pill danger">Urgent</span>'
                    : n.read ? '<span class="pill-text">Read</span>' : '<span class="pill info">New</span>') + "</" + close + ">";
        }).join("");
    }

    function readAndRefresh(ids) {
        GTStudent.markRead(student.id, ids);
        render();
        GTShell.refresh();
    }

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            filter = tab.getAttribute("data-filter");
            tabs.forEach(function (t) { t.classList.toggle("active", t === tab); });
            render();
        });
    });

    list.addEventListener("click", function (e) {
        var item = e.target.closest(".notif-item.unread");
        if (item) readAndRefresh([item.getAttribute("data-id")]);
    });

    markAll.addEventListener("click", function () {
        readAndRefresh(GTStudent.notifications(student.id).map(function (n) { return n.id; }));
    });

    render();
});
