/* =====================================================
   SETTINGS — super admin only
   General, grading rules, security, and mail settings.
   Grade lock, passing grade, retention GWA, password
   length and failed-login limit are enforced by the
   demo. Mail settings are stored for the PHP backend.
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var form = document.getElementById("settings-form");
    var errorBox = document.getElementById("settings-error");
    var s = GTData.settings;
    var f = form.elements;

    var NUMBER_FIELDS = ["passingGrade", "retentionGwa", "alertMargin", "minPasswordLength", "maxFailedLogins", "smtpPort"];
    var CHECK_FIELDS = ["gradeLock", "notifyOnGradeChange"];
    var TEXT_FIELDS = ["schoolName", "academicYear", "semester", "smtpHost", "fromEmail"];

    function fill() {
        TEXT_FIELDS.concat(NUMBER_FIELDS).forEach(function (k) { f[k].value = s[k]; });
        CHECK_FIELDS.forEach(function (k) { f[k].checked = !!s[k]; });
    }

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        errorBox.textContent = "";

        var next = {};
        TEXT_FIELDS.forEach(function (k) { next[k] = f[k].value.trim(); });
        NUMBER_FIELDS.forEach(function (k) { next[k] = Number(f[k].value); });
        CHECK_FIELDS.forEach(function (k) { next[k] = f[k].checked; });

        if (!next.schoolName || !next.academicYear) {
            errorBox.textContent = "School name and academic year are required.";
            return;
        }
        if (!(next.passingGrade >= 50 && next.passingGrade <= 100)) {
            errorBox.textContent = "Passing grade must be between 50 and 100.";
            return;
        }
        if (!(next.retentionGwa >= next.passingGrade && next.retentionGwa <= 100)) {
            errorBox.textContent = "Retention GWA must be between the passing grade and 100.";
            return;
        }
        if (!(next.alertMargin >= 0 && next.alertMargin <= 15)) {
            errorBox.textContent = "Alert margin must be between 0 and 15.";
            return;
        }
        if (!(next.minPasswordLength >= 6 && next.minPasswordLength <= 64)) {
            errorBox.textContent = "Minimum password length must be 6 to 64.";
            return;
        }
        if (!(next.maxFailedLogins >= 3 && next.maxFailedLogins <= 20)) {
            errorBox.textContent = "Failed login limit must be 3 to 20.";
            return;
        }
        if (next.fromEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(next.fromEmail)) {
            errorBox.textContent = "Enter a valid sender email address.";
            return;
        }

        var changed = Object.keys(next).filter(function (k) { return s[k] !== next[k]; });
        if (!changed.length) {
            GTUI.toast("No changes to save.", "info");
            return;
        }

        var detail = changed.map(function (k) {
            return k + " " + s[k] + " → " + next[k];
        }).join(", ");

        Object.assign(s, next);
        GTData.log("settings", "Updated settings", detail, "success");
        GTData.commit();
        GTUI.toast("Settings saved.");
    });

    document.getElementById("check-updates").addEventListener("click", function () {
        GTUI.toast("Update checks need the PHP backend. Not available in the demo.", "info");
    });

    document.getElementById("reset-demo").addEventListener("click", function () {
        GTUI.confirm({
            icon: "↺", title: "Reset demo data?",
            message: "All accounts, scholars, grades and logs return to the starting sample data. You will be signed out.",
            confirmText: "Reset"
        }).then(function (ok) {
            if (!ok) return;
            GTData.reset();
            GranTrackSession.end();
            window.location.replace("../login.html");
        });
    });

    fill();
});
