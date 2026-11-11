/* =====================================================
   AUTH — session guard with roles
   Loaded in <head> so it runs before the page shows.
   Restrict a page by adding data-roles to the script tag:
     <script src="assets/js/auth.js" data-roles="superadmin"></script>
   Frontend demo only: replace with real PHP sessions later.
===================================================== */

(function () {
    "use strict";

    var KEY = "grantrackAdminSession";
    var tag = document.currentScript;
    var allowed = ((tag && tag.getAttribute("data-roles")) || "").split(",").filter(Boolean);

    var GranTrackSession = {
        get: function () {
            try { return JSON.parse(localStorage.getItem(KEY)); } catch (e) { return null; }
        },
        isLoggedIn: function () {
            return !!GranTrackSession.get();
        },
        start: function (user) {
            localStorage.setItem(KEY, JSON.stringify({
                id: user.id, name: user.name, email: user.email, role: user.role
            }));
        },
        end: function () {
            localStorage.removeItem(KEY);
        }
    };

    // Expose for nav.js (logout) and data.js (audit trail)
    window.GranTrackSession = GranTrackSession;

    // The login page loads this file too, so it must not redirect itself
    if (document.documentElement.hasAttribute("data-public")) return;

    var session = GranTrackSession.get();

    if (!session) {
        window.location.replace("../login.html");
    } else if (allowed.length && allowed.indexOf(session.role) === -1) {
        window.location.replace("index.html");
    }
})();
