/* =====================================================
   LOGIN — one sign-in page for staff and students.
   It checks the email against the staff accounts first, then
   the student list, starts the right session and sends the
   person to the right portal. Replace with one PHP login
   endpoint that returns the role.
===================================================== */

(function () {
    "use strict";

    var ADMIN_KEY = "grantrackAdminSession";
    var STUDENT_KEY = "grantrackStudentSession";

    // student.js calls GranTrackSession.start() after a student login
    window.GranTrackSession = {
        get: function () { return null; },
        start: function (student) {
            localStorage.setItem(STUDENT_KEY, JSON.stringify({
                id: student.id, name: student.name, email: student.email,
                studentNo: student.studentNo, role: "student"
            }));
        }
    };

    function startStaff(user) {
        localStorage.setItem(ADMIN_KEY, JSON.stringify({ id: user.id, name: user.name, email: user.email, role: user.role }));
    }

    function has(key) {
        try { return !!JSON.parse(localStorage.getItem(key)); } catch (e) { return false; }
    }

    function goTo(portal) {
        window.location.replace(portal === "student" ? "student-dashboard/index.html" : "admin-dashboard/index.html");
    }

    // Staff accounts that still exist (an admin may have been deleted)
    function staffSessionValid() {
        try {
            var s = JSON.parse(localStorage.getItem(ADMIN_KEY));
            return !!(s && GTData.userById(s.id));
        } catch (e) { return false; }
    }

    document.addEventListener("DOMContentLoaded", function () {

        if (has(ADMIN_KEY) && staffSessionValid()) return goTo("admin");
        if (has(STUDENT_KEY) && GTStudent.current()) return goTo("student");

        var form = document.getElementById("login-form");
        var emailInput = document.getElementById("login-email");
        var password = document.getElementById("password");
        var toggle = document.getElementById("toggle-password");
        var errorMessage = document.getElementById("login-error");
        var rememberMe = document.getElementById("remember-me");

        document.getElementById("login-year").textContent = new Date().getFullYear();

        toggle.addEventListener("click", function () {
            var hidden = password.type === "password";
            password.type = hidden ? "text" : "password";
            toggle.textContent = hidden ? "Hide" : "Show";
        });

        /* ----- demo account buttons ----- */
        function chip(box, label, email, pass) {
            var b = document.createElement("button");
            b.type = "button";
            b.textContent = label;
            b.addEventListener("click", function () {
                emailInput.value = email;
                password.value = pass;
                errorMessage.textContent = "";
                password.focus();
            });
            document.getElementById(box).appendChild(b);
        }

        chip("demo-staff", "Super Admin", "superadmin@mcc.edu.ph", "admin123");
        chip("demo-staff", "Teacher (CS)", "teacher@mcc.edu.ph", "teacher123");
        chip("demo-staff", "Teacher (Math, English)", "ana.lopez@mcc.edu.ph", "teacher123");

        [[0, "all good"], [3, "overdue document"], [4, "GWA close to limit"], [6, "rejected document"], [9, "on probation"]]
            .forEach(function (p) {
                var s = GTData.students[p[0]];
                if (s) chip("demo-students", s.name.split(" ")[0] + " (" + p[1] + ")", s.email, GTStudent.DEFAULT_PASSWORD);
            });

        /* ----- forgot password modal ----- */
        var forgot = document.getElementById("forgot-modal");
        function setForgot(open) { forgot.classList.toggle("show", open); }
        document.getElementById("forgot-password").addEventListener("click", function (e) { e.preventDefault(); setForgot(true); });
        document.getElementById("close-modal").addEventListener("click", function () { setForgot(false); });
        document.getElementById("modal-ok").addEventListener("click", function () { setForgot(false); });
        forgot.addEventListener("click", function (e) { if (e.target === forgot) setForgot(false); });
        document.addEventListener("keydown", function (e) { if (e.key === "Escape") setForgot(false); });

        /* ----- staff sign-in (same rules as before: lockout, disabled) ----- */
        function failStaff(email, reason, user) {
            GTData.log("login", "Failed sign-in", email + " (" + reason + ")", "failed",
                user ? { id: user.id, name: user.name, role: user.role } : { id: null, name: "Unknown", role: "-" });
        }

        function signInStaff(user, email, pass) {
            var generic = "Incorrect email or password.";

            if (user.status === "locked") {
                failStaff(email, "account locked", user);
                return "This account is locked after too many failed attempts. Ask a super admin to unlock it.";
            }
            if (user.status === "disabled") {
                failStaff(email, "account disabled", user);
                return "This account has been disabled. Contact the administrator.";
            }
            if (user.password !== pass) {
                user.failedAttempts = (user.failedAttempts || 0) + 1;
                var max = Number(GTData.settings.maxFailedLogins) || 5;
                var msg = generic;
                if (user.failedAttempts >= max) {
                    user.status = "locked";
                    failStaff(email, "wrong password, account locked", user);
                    msg = "Too many failed attempts. This account is now locked.";
                } else {
                    failStaff(email, "wrong password, attempt " + user.failedAttempts + " of " + max, user);
                }
                GTData.commit();
                return msg;
            }

            user.failedAttempts = 0;
            user.lastLogin = new Date().toISOString();
            GTData.commit();
            startStaff(user);
            GTData.log("login", "Signed in", email, "success", { id: user.id, name: user.name, role: user.role });
            return "";
        }

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            errorMessage.textContent = "";

            var email = emailInput.value.trim();
            var pass = password.value;

            if (!email || !pass) {
                errorMessage.textContent = "Please enter your email and password.";
                return;
            }

            // One person, one session: clear any old one first
            localStorage.removeItem(ADMIN_KEY);
            localStorage.removeItem(STUDENT_KEY);

            var staff = GTData.userByEmail(email);
            var portal;

            if (staff) {
                var err = signInStaff(staff, email, pass);
                if (err) { errorMessage.textContent = err; return; }
                portal = "admin";
            } else {
                // Not staff: try the scholar list (also logs unknown accounts)
                var result = GTStudent.login(email, pass);
                if (!result.ok) { errorMessage.textContent = result.error; return; }
                portal = "student";
            }

            if (rememberMe.checked) localStorage.setItem("grantrackRemember", "true");
            else localStorage.removeItem("grantrackRemember");

            goTo(portal);
        });
    });
})();
