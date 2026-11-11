/* =====================================================
   SHELL — fills the parts every page shares:
   student name, number, avatar, unread counts, year.
   Needs auth.js, data.js and student.js loaded first.
===================================================== */

(function () {
    "use strict";

    function fillShell() {
        var student = GTStudent.current();

        if (!student) {
            // Account was removed by an admin while signed in
            GranTrackSession.end();
            window.location.replace("../login.html");
            return null;
        }

        var profile = GTStudent.getProfile(student.id);

        document.querySelectorAll("[data-student-name]").forEach(function (el) { el.textContent = student.name; });
        document.querySelectorAll("[data-student-no]").forEach(function (el) { el.textContent = student.studentNo; });
        document.querySelectorAll("[data-student-first]").forEach(function (el) { el.textContent = GTStudent.firstName(student); });
        document.querySelectorAll("[data-academic-year]").forEach(function (el) { el.textContent = GTData.settings.academicYear; });
        document.querySelectorAll("[data-semester]").forEach(function (el) { el.textContent = GTData.settings.semester; });
        document.querySelectorAll("[data-year]").forEach(function (el) { el.textContent = new Date().getFullYear(); });

        document.querySelectorAll("[data-student-avatar]").forEach(function (el) {
            if (profile.photo) {
                el.style.backgroundImage = "url(" + profile.photo + ")";
                el.classList.add("has-photo");
                el.textContent = "";
            } else {
                el.style.backgroundImage = "";
                el.classList.remove("has-photo");
                el.textContent = GTStudent.initials(student.name);
            }
        });

        refreshUnread(student);
        return student;
    }

    function refreshUnread(student) {
        student = student || GTStudent.current();
        if (!student) return;
        var count = GTStudent.unreadCount(student.id);

        document.querySelectorAll(".notification-count").forEach(function (el) {
            el.textContent = count;
            el.setAttribute("aria-label", count + " unread");
            el.hidden = count === 0;
        });
        document.querySelectorAll(".notification-dot").forEach(function (el) { el.hidden = count === 0; });
    }

    var student = null;

    document.addEventListener("DOMContentLoaded", function () {
        student = fillShell();
        if (student) document.dispatchEvent(new CustomEvent("shell:ready", { detail: student }));
    });

    window.GTShell = {
        student: function () { return student; },
        refresh: function () { fillShell(); }
    };
})();
