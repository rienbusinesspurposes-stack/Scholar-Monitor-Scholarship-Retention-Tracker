/* =====================================================
   STUDENT — everything the student portal needs, built on
   top of GTData (data.js, shared with the admin portal).

   Each function below is a place to swap in a PHP call:
     GTStudent.login(...)           -> POST /api/login
     GTStudent.requirements(id)     -> GET  /api/students/:id/requirements
     GTStudent.uploadDocument(...)  -> POST /api/submissions (multipart)
===================================================== */

(function () {
    "use strict";

    var DEFAULT_PASSWORD = "student123";   // demo only
    var PW_KEY = "grantrackStudentPasswords";
    var PROFILE_KEY = "grantrackStudentProfile_";
    var READ_KEY = "grantrackStudentRead_";

    /* ---------- storage helpers ---------- */

    function readJSON(key, fallback) {
        try {
            var raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) { return fallback; }
    }

    function writeJSON(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* storage blocked */ }
    }

    /* ---------- dates (same UTC-day rule as GTData.alerts) ---------- */

    function today() { return new Date().toISOString().slice(0, 10); }

    function daysUntil(iso) {
        var a = Date.parse(iso + "T00:00:00Z");
        var b = Date.parse(today() + "T00:00:00Z");
        return Math.round((a - b) / 86400000);
    }

    /* ---------- accounts ---------- */

    function studentById(id) {
        return GTData.students.filter(function (s) { return s.id === id; })[0] || null;
    }

    function studentByEmail(email) {
        email = String(email || "").trim().toLowerCase();
        return GTData.students.filter(function (s) { return s.email.toLowerCase() === email; })[0] || null;
    }

    function passwordFor(student) {
        return readJSON(PW_KEY, {})[student.id] || DEFAULT_PASSWORD;
    }

    function login(email, password) {
        var student = studentByEmail(email);
        var generic = "Incorrect email or password.";

        function fail(reason) {
            GTData.log("login", "Failed sign-in", email + " (" + reason + ")", "failed",
                student ? { id: student.id, name: student.name, role: "student" } : { id: null, name: "Unknown", role: "-" });
        }

        if (!student) { fail("unknown account"); return { ok: false, error: generic }; }

        if (student.status === "inactive") {
            fail("account inactive");
            return { ok: false, error: "This account is inactive. Please contact your scholarship administrator." };
        }

        if (password !== passwordFor(student)) { fail("wrong password"); return { ok: false, error: generic }; }

        GranTrackSession.start(student);
        GTData.log("login", "Signed in", email, "success", { id: student.id, name: student.name, role: "student" });
        return { ok: true, student: student };
    }

    function current() {
        var s = GranTrackSession.get();
        return s ? studentById(s.id) : null;
    }

    function changePassword(student, oldPass, newPass) {
        var min = Number(GTData.settings.minPasswordLength) || 8;
        if (oldPass !== passwordFor(student)) return "Your current password is incorrect.";
        if (newPass.length < min) return "New password must be at least " + min + " characters.";
        if (newPass === oldPass) return "New password must be different from the current one.";
        var all = readJSON(PW_KEY, {});
        all[student.id] = newPass;
        writeJSON(PW_KEY, all);
        GTData.log("account", "Changed password", student.studentNo, "success",
            { id: student.id, name: student.name, role: "student" });
        return "";
    }

    function firstName(student) {
        return String(student.name).split(" ")[0];
    }

    function initials(name) {
        var parts = String(name).replace(/\b(Jr|Sr|III|II)\.?$/i, "").trim().split(/\s+/);
        return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : "")).toUpperCase();
    }

    /* ---------- profile (contact details the student may edit) ---------- */

    function getProfile(id) {
        return Object.assign({ phone: "", address: "", guardian: "", guardianPhone: "", photo: "" },
            readJSON(PROFILE_KEY + id, {}));
    }

    function saveProfile(student, data) {
        writeJSON(PROFILE_KEY + student.id, Object.assign(getProfile(student.id), data));
    }

    /* ---------- academics ---------- */

    function records(id) {
        return GTData.subjects.map(function (sub) {
            var g = GTData.gradeFor(id, sub.code);
            var grade = GTData.computeGrade(g);
            var teacher = GTData.userById(sub.teacherId);
            return {
                code: sub.code, title: sub.title, units: sub.units,
                teacher: teacher ? teacher.name : "—",
                midterm: g.midterm, final: g.final, grade: grade,
                remark: GTData.remarkFor(grade),
                updatedAt: g.updatedAt
            };
        });
    }

    /* ---------- requirements & documents ---------- */

    // state: verified | pending | rejected | missing | overdue
    function requirements(id) {
        return GTData.requirements.map(function (r) {
            var sub = GTData.submissionFor(id, r.id);
            var left = daysUntil(r.dueDate);
            var state = sub.status;
            if (state === "missing" && left < 0) state = "overdue";
            return { req: r, sub: sub, state: state, daysLeft: left };
        }).sort(function (a, b) { return a.req.dueDate < b.req.dueDate ? -1 : 1; });
    }

    // Needs action from the student: not yet submitted, overdue, or rejected
    function needsAction(item) {
        return item.state === "missing" || item.state === "overdue" || item.state === "rejected";
    }

    // Saves the file (GTFiles, shared with the admin portal) and marks the
    // requirement "pending" so it appears in the admin's Submissions tab.
    // Resolves true on success, or an error message string.
    function uploadDocument(student, reqId, file) {
        var r = GTData.requirements.filter(function (x) { return x.id === reqId; })[0];
        if (!r) return Promise.resolve("That requirement no longer exists.");
        var before = GTData.submissionFor(student.id, reqId);
        if (before.status === "verified") return Promise.resolve("This document is already verified and can no longer be changed.");

        return GTFiles.put(student.id + "|" + reqId, file).then(function () {
            GTData.setSubmission(student.id, reqId, {
                status: "pending", fileName: file.name, hasFile: true,
                submittedAt: new Date().toISOString(),
                verifiedBy: null, verifiedAt: null, note: ""
            });
            GTData.log("document", before.status === "rejected" ? "Resubmitted document" : "Uploaded document",
                student.studentNo + " · " + r.name + " (" + file.name + ")", "success",
                { id: student.id, name: student.name, role: "student" });
            return true;
        }, function () {
            return "Could not save the file in this browser. Check that storage is not blocked.";
        });
    }

    /* ---------- display helpers ---------- */

    var MONTHS = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];

    function dateParts(iso) {
        var p = iso.split("-");
        return { day: p[2], mon: MONTHS[Number(p[1]) - 1] };
    }

    // Short "when" text and color class for a requirement item
    function dueInfo(item) {
        var n = item.daysLeft;
        if (item.state === "verified") return { text: "Verified", cls: "done" };
        if (item.state === "pending") return { text: "Submitted, waiting for verification", cls: "done" };
        var prefix = item.state === "rejected" ? "Resubmit · " : "";
        if (n < 0) return { text: prefix + "Overdue by " + (-n) + " day" + (n === -1 ? "" : "s"), cls: "late" };
        if (n === 0) return { text: prefix + "Due today", cls: "soon" };
        if (n <= 7) return { text: prefix + "Due in " + n + " day" + (n === 1 ? "" : "s"), cls: "soon" };
        return { text: prefix + "Due in " + n + " days", cls: "ok" };
    }

    var YEAR_LABEL = { 1: "1st Year", 2: "2nd Year", 3: "3rd Year", 4: "4th Year" };

    /* ---------- scholarship eligibility ---------- */

    function eligibility(id) {
        var st = GTData.settings;
        var student = studentById(id);
        var gwa = GTData.studentGwa(id);
        var retention = Number(st.retentionGwa);
        var passing = Number(st.passingGrade);

        var failing = records(id).filter(function (r) { return r.grade !== null && r.grade < passing; });
        var reqs = requirements(id).filter(function (i) { return i.req.required; });
        var bad = reqs.filter(needsAction);

        var checks = [
            {
                ok: gwa === null ? null : gwa >= retention,
                title: "Maintain a GWA of " + retention + " or higher",
                detail: gwa === null ? "No complete grades yet." : "Your GWA is " + gwa.toFixed(2) + "."
            },
            {
                ok: !failing.length,
                title: "No failing grades (below " + passing + ")",
                detail: failing.length ? "Failing: " + failing.map(function (f) { return f.code; }).join(", ") + "." : "All recorded grades are passing."
            },
            {
                ok: !bad.length,
                title: "Required documents submitted",
                detail: bad.length ? bad.length + (bad.length > 1 ? " documents need" : " document needs") + " your action." : "All required documents are submitted."
            },
            {
                ok: student.status !== "probation",
                title: "Good standing",
                detail: student.status === "probation" ? "Your scholarship is on probation." : "No probation on record."
            }
        ];

        var label = "Active", level = "success";
        if (student.status === "inactive") { label = "Inactive"; level = "neutral"; }
        else if (student.status === "probation") { label = "Probation"; level = "warning"; }
        else if (myAlerts(id).some(function (a) { return a.level === "critical"; })) { label = "At risk"; level = "danger"; }
        else if (myAlerts(id).length) { label = "Needs attention"; level = "warning"; }

        return { gwa: gwa, retention: retention, passing: passing, margin: Number(st.alertMargin) || 0, checks: checks, label: label, level: level, failing: failing };
    }

    /* ---------- alerts & notifications ---------- */

    function myAlerts(id) {
        var mine = GTData.alerts().filter(function (a) { return a.student.id === id; })[0];
        return mine ? mine.reasons : [];
    }

    // Everything is derived from the shared data, so when an admin verifies
    // a document or enters a grade, it shows up here without any extra step.
    function notifications(id) {
        var out = [];

        myAlerts(id).forEach(function (r) {
            out.push({
                id: "alert:" + r.text, kind: "alert", level: r.level,
                title: r.level === "critical" ? "Scholarship at risk" : "Needs attention",
                text: r.text, ts: null
            });
        });

        GTData.requirements.forEach(function (req) {
            var sub = GTData.submissionFor(id, req.id);
            if (sub.status === "verified" && sub.verifiedAt) {
                out.push({ id: "doc:v:" + req.id + ":" + sub.verifiedAt, kind: "doc", level: "success",
                    title: "Document verified", text: req.name + " was verified by the scholarship office.", ts: sub.verifiedAt });
            } else if (sub.status === "rejected" && sub.verifiedAt) {
                out.push({ id: "doc:r:" + req.id + ":" + sub.verifiedAt, kind: "doc", level: "danger",
                    title: "Document rejected", text: req.name + ": " + (sub.note || "Please upload a new copy."), ts: sub.verifiedAt });
            } else if (sub.status === "pending" && sub.submittedAt) {
                out.push({ id: "doc:p:" + req.id + ":" + sub.submittedAt, kind: "doc", level: "info",
                    title: "Document received", text: req.name + " is waiting to be verified.", ts: sub.submittedAt });
            }
        });

        GTData.subjects.forEach(function (sub) {
            var g = GTData.gradeFor(id, sub.code);
            if (g.updatedAt) {
                out.push({ id: "grade:" + sub.code + ":" + g.updatedAt, kind: "grade", level: "info",
                    title: "Grade updated", text: sub.code + " · " + sub.title + " was updated.", ts: g.updatedAt });
            }
        });

        var rank = { critical: 0, warning: 1 };
        out.sort(function (a, b) {
            if (!a.ts && !b.ts) return (rank[a.level] || 0) - (rank[b.level] || 0);
            if (!a.ts) return -1;
            if (!b.ts) return 1;
            return a.ts < b.ts ? 1 : -1;
        });

        var read = readJSON(READ_KEY + id, {});
        out.forEach(function (n) { n.read = !!read[n.id]; });
        return out;
    }

    function markRead(id, ids) {
        var read = readJSON(READ_KEY + id, {});
        ids.forEach(function (x) { read[x] = 1; });
        writeJSON(READ_KEY + id, read);
    }

    function unreadCount(id) {
        return notifications(id).filter(function (n) { return !n.read; }).length;
    }

    window.GTStudent = {
        DEFAULT_PASSWORD: DEFAULT_PASSWORD,
        login: login,
        current: current,
        changePassword: changePassword,
        firstName: firstName,
        initials: initials,
        getProfile: getProfile,
        saveProfile: saveProfile,
        records: records,
        requirements: requirements,
        needsAction: needsAction,
        uploadDocument: uploadDocument,
        eligibility: eligibility,
        alerts: myAlerts,
        notifications: notifications,
        markRead: markRead,
        unreadCount: unreadCount,
        daysUntil: daysUntil,
        dateParts: dateParts,
        dueInfo: dueInfo,
        yearLabel: function (y) { return YEAR_LABEL[y] || y + "th Year"; },
        today: today
    };
})();
