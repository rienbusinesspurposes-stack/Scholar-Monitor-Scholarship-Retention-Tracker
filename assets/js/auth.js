/* =====================================================
   AUTH — session guard
   Loaded in <head> so it runs before the page shows.
   Frontend demo only: replace with real PHP sessions later.
===================================================== */

(function () {
    "use strict";

    var KEYS = {
        loggedIn:  "grantrackLoggedIn",
        studentId: "grantrackStudentID",
        remember:  "grantrackRemember"
    };

    var GranTrackSession = {
        isLoggedIn: function () {
            return localStorage.getItem(KEYS.loggedIn) === "true";
        },
        end: function () {
            Object.keys(KEYS).forEach(function (key) {
                localStorage.removeItem(KEYS[key]);
            });
        }
    };

    // Expose for nav.js (logout)
    window.GranTrackSession = GranTrackSession;

    if (!GranTrackSession.isLoggedIn()) {
        window.location.replace("login.html");
    }
})();
