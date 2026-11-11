/* =====================================================
   AUTH — student session guard
   Loaded in <head> so it runs before the page shows.
   The login page marks <html data-public> so it does not
   redirect itself.
   Frontend demo only: replace with real PHP sessions later.
===================================================== */

(function () {
    "use strict";

    var KEY = "grantrackStudentSession";

    var GranTrackSession = {
        get: function () {
            try { return JSON.parse(localStorage.getItem(KEY)); } catch (e) { return null; }
        },
        isLoggedIn: function () {
            return !!GranTrackSession.get();
        },
        // "role: student" keeps audit-log entries readable on the admin side
        start: function (student) {
            localStorage.setItem(KEY, JSON.stringify({
                id: student.id,
                name: student.name,
                email: student.email,
                studentNo: student.studentNo,
                role: "student"
            }));
        },
        end: function () {
            localStorage.removeItem(KEY);
        }
    };

    // Exposed for nav.js (logout) and data.js (audit trail)
    window.GranTrackSession = GranTrackSession;

    if (document.documentElement.hasAttribute("data-public")) return;

    if (!GranTrackSession.isLoggedIn()) {
        window.location.replace("../login.html");
    }
})();
