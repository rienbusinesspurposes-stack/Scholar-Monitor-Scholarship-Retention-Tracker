/* =====================================================
   DATA — demo data store (localStorage)
   Frontend demo only. Every function here is the place
   to swap in a PHP/MySQL call later:
     GTData.getUsers()      -> GET  /api/users
     GTData.saveGrade(...)  -> POST /api/grades
     GTData.log(...)        -> written server-side
===================================================== */

(function () {
    "use strict";

    var DB_KEY = "grantrackAdminDB_v2";

    var DEFAULT_SETTINGS = {
        schoolName: "MCC BALUQ",
        academicYear: "2026–2027",
        semester: "1st Semester",
        gradeLock: false,
        passingGrade: 75,
        retentionGwa: 85,
        alertMargin: 2,
        minPasswordLength: 8,
        maxFailedLogins: 5,
        smtpHost: "smtp.mcc.edu.ph",
        smtpPort: 587,
        fromEmail: "no-reply@mcc.edu.ph",
        notifyOnGradeChange: true
    };

    function seed() {
        var now = Date.now();
        var hours = function (h) { return new Date(now - h * 3600000).toISOString(); };

        var users = [
            { id: "u1", name: "Maria Santos", email: "superadmin@mcc.edu.ph", password: "admin123", role: "superadmin", status: "active", failedAttempts: 0, createdAt: hours(2400) },
            { id: "u2", name: "Prof. Jose Ramirez", email: "teacher@mcc.edu.ph", password: "teacher123", role: "teacher", status: "active", failedAttempts: 0, createdAt: hours(2000) },
            { id: "u3", name: "Prof. Ana Lopez", email: "ana.lopez@mcc.edu.ph", password: "teacher123", role: "teacher", status: "active", failedAttempts: 0, createdAt: hours(1900) }
        ];

        var subjects = [
            { code: "CS101", title: "Introduction to Computing", units: 3, teacherId: "u2" },
            { code: "CS102", title: "Computer Programming 1", units: 3, teacherId: "u2" },
            { code: "MATH101", title: "College Algebra", units: 3, teacherId: "u3" },
            { code: "ENG101", title: "Purposive Communication", units: 3, teacherId: "u3" },
            { code: "PE101", title: "Physical Fitness", units: 2, teacherId: "u3" }
        ];

        var names = [
            ["Juan Dela Cruz", "BSIT", 1, "Full Scholarship"],
            ["Maria Clara Reyes", "BSIT", 1, "Full Scholarship"],
            ["Andres Bonifacio Jr.", "BSCS", 2, "Partial Scholarship"],
            ["Gabriela Silang", "BSCS", 2, "Full Scholarship"],
            ["Jose Mercado", "BSIT", 1, "Partial Scholarship"],
            ["Teresa Magbanua", "BSIT", 3, "Full Scholarship"],
            ["Emilio Aguinaldo III", "BSCS", 1, "Partial Scholarship"],
            ["Melchora Aquino", "BSIT", 2, "Full Scholarship"],
            ["Apolinario Cruz", "BSCS", 3, "Full Scholarship"],
            ["Josefa Rizal", "BSIT", 1, "Partial Scholarship"],
            ["Antonio Luna", "BSCS", 2, "Full Scholarship"],
            ["Marcela Agoncillo", "BSIT", 3, "Partial Scholarship"]
        ];

        var students = names.map(function (n, i) {
            return {
                id: "s" + (i + 1),
                studentNo: "2026-" + String(1001 + i),
                name: n[0],
                email: n[0].toLowerCase().replace(/[^a-z ]/g, "").split(" ")[0] + "." + String(1001 + i) + "@mcc.edu.ph",
                course: n[1],
                year: n[2],
                scholarship: n[3],
                status: i === 9 ? "probation" : "active"
            };
        });

        var grades = {};
        students.forEach(function (s, i) {
            subjects.forEach(function (sub, j) {
                var mid = 83 + ((i * 7 + j * 5) % 15);
                var fin = 82 + ((i * 11 + j * 3) % 16);
                if (i === 4) { mid = 80 + (j % 3); fin = 82 + (j % 2); }   // borderline scholar
                if ((i * j) % 9 === 8) mid = null;
                if ((i + j) % 5 === 0) fin = null;
                if (i === 9) { mid = 68 + j; fin = j % 2 ? 72 : null; }
                grades[s.id + "|" + sub.code] = {
                    midterm: mid,
                    final: fin,
                    updatedBy: mid === null && fin === null ? null : sub.teacherId,
                    updatedAt: mid === null && fin === null ? null : hours(30 + i * 3 + j)
                };
            });
        });

        var day = function (n) { return new Date(now + n * 86400000).toISOString().slice(0, 10); };

        var requirements = [
            { id: "r1", name: "Enrollment Form", description: "Signed enrollment form for the current semester.", dueDate: day(-1), required: true, file: "enrollment-form.pdf" },
            { id: "r2", name: "Certificate of Registration", description: "Official COR from the registrar.", dueDate: day(5), required: true, file: "cor.pdf" },
            { id: "r3", name: "Grade Report", description: "Report of grades from the previous term.", dueDate: day(24), required: true, file: "grade-report.pdf" },
            { id: "r4", name: "Scholarship Renewal Letter", description: "Letter of intent to continue the scholarship.", dueDate: day(40), required: false, file: "renewal-letter.pdf" }
        ];

        var submissions = {};
        students.forEach(function (st, i) {
            requirements.forEach(function (r, j) {
                var k = (i * 3 + j * 5) % 7;
                var status = k <= 3 ? "verified" : "pending";
                if (i === 6 && j === 1) status = "rejected";       // needs resubmission
                if (i === 3 && j === 0) status = "missing";        // overdue
                if (i === 7 && j === 1) status = "missing";        // due soon
                if (r.id === "r4") status = i % 3 === 0 ? "pending" : "missing";
                if (status === "missing") return;
                submissions[st.id + "|" + r.id] = {
                    status: status,
                    fileName: st.studentNo + "-" + r.file,
                    submittedAt: hours(20 + i * 4 + j * 2),
                    verifiedBy: status === "verified" || status === "rejected" ? "u2" : null,
                    verifiedAt: status === "verified" || status === "rejected" ? hours(10 + i) : null,
                    note: status === "rejected" ? "Image is blurry. Please upload a clearer copy." : ""
                };
            });
        });

        var audit = [
            { ts: hours(1), userId: "u2", userName: "Prof. Jose Ramirez", role: "teacher", category: "login", action: "Signed in", detail: "teacher@mcc.edu.ph", outcome: "success" },
            { ts: hours(5), userId: null, userName: "Unknown", role: "-", category: "login", action: "Failed sign-in", detail: "admin@mcc.edu.ph (unknown account)", outcome: "failed" },
            { ts: hours(5.1), userId: null, userName: "Unknown", role: "-", category: "login", action: "Failed sign-in", detail: "admin@mcc.edu.ph (unknown account)", outcome: "failed" },
            { ts: hours(9), userId: "u1", userName: "Maria Santos", role: "superadmin", category: "settings", action: "Updated settings", detail: "Passing grade set to 75", outcome: "success" },
            { ts: hours(26), userId: "u3", userName: "Prof. Ana Lopez", role: "teacher", category: "grade", action: "Grade changed", detail: "2026-1003 · MATH101 · Final 82 → 85", outcome: "success" },
            { ts: hours(28), userId: "u1", userName: "Maria Santos", role: "superadmin", category: "account", action: "Created account", detail: "ana.lopez@mcc.edu.ph (teacher)", outcome: "success" }
        ].map(function (e, i) { e.id = "a" + (i + 1); return e; });

        return {
            users: users,
            subjects: subjects,
            students: students,
            grades: grades,
            requirements: requirements,
            submissions: submissions,
            settings: JSON.parse(JSON.stringify(DEFAULT_SETTINGS)),
            audit: audit,
            counters: { user: 3, student: students.length, audit: audit.length, req: requirements.length }
        };
    }

    var db;

    function load() {
        try {
            var raw = localStorage.getItem(DB_KEY);
            if (raw) {
                db = JSON.parse(raw);
                db.settings = Object.assign({}, DEFAULT_SETTINGS, db.settings || {});
                db.requirements = db.requirements || [];
                db.submissions = db.submissions || {};
                return;
            }
        } catch (e) { /* fall through to seed */ }
        db = seed();
        commit();
    }

    function commit() {
        try { localStorage.setItem(DB_KEY, JSON.stringify(db)); } catch (e) { /* storage blocked */ }
    }

    load();

    // Another tab (e.g. the student portal) saved something: pick it up.
    // Both portals are on the same origin, so they share this storage.
    window.addEventListener("storage", function (event) {
        if (event.key !== DB_KEY) return;
        load();
        window.dispatchEvent(new CustomEvent("gt:datachanged"));
    });

    /* ---------- helpers ---------- */

    function nextId(kind, prefix) {
        db.counters[kind] = (db.counters[kind] || 0) + 1;
        return prefix + db.counters[kind];
    }

    function currentActor() {
        var s = window.GranTrackSession && window.GranTrackSession.get();
        return s ? { id: s.id, name: s.name, role: s.role } : { id: null, name: "Unknown", role: "-" };
    }

    function log(category, action, detail, outcome, actorOverride) {
        var actor = actorOverride || currentActor();
        db.audit.unshift({
            id: nextId("audit", "a"),
            ts: new Date().toISOString(),
            userId: actor.id,
            userName: actor.name,
            role: actor.role,
            category: category,
            action: action,
            detail: detail || "",
            outcome: outcome || "success"
        });
        if (db.audit.length > 1000) db.audit.length = 1000;
        commit();
    }

    /* ---------- grades ---------- */

    function gradeFor(studentId, code) {
        return db.grades[studentId + "|" + code] || { midterm: null, final: null, updatedBy: null, updatedAt: null };
    }

    // Final grade = average of midterm and final. Incomplete until both exist.
    function computeGrade(g) {
        if (g.midterm === null || g.midterm === undefined || g.final === null || g.final === undefined) return null;
        return Math.round(((g.midterm + g.final) / 2) * 100) / 100;
    }

    function remarkFor(grade) {
        if (grade === null) return "incomplete";
        return grade >= Number(db.settings.passingGrade) ? "passed" : "failed";
    }

    function studentGwa(studentId) {
        var total = 0, units = 0;
        db.subjects.forEach(function (sub) {
            var grade = computeGrade(gradeFor(studentId, sub.code));
            if (grade !== null) {
                total += grade * sub.units;
                units += sub.units;
            }
        });
        return units ? Math.round((total / units) * 100) / 100 : null;
    }

    function saveGrade(studentId, code, midterm, final) {
        var key = studentId + "|" + code;
        var before = gradeFor(studentId, code);
        var actor = currentActor();
        var student = db.students.filter(function (s) { return s.id === studentId; })[0];
        var changes = [];

        if (before.midterm !== midterm) changes.push("Midterm " + fmt(before.midterm) + " → " + fmt(midterm));
        if (before.final !== final) changes.push("Final " + fmt(before.final) + " → " + fmt(final));
        if (!changes.length) return false;

        db.grades[key] = { midterm: midterm, final: final, updatedBy: actor.id, updatedAt: new Date().toISOString() };
        log("grade", "Grade changed", (student ? student.studentNo : studentId) + " · " + code + " · " + changes.join(", "), "success");
        return true;
    }

    function fmt(v) { return v === null || v === undefined ? "—" : v; }

    /* ---------- requirements & alerts ---------- */

    function submissionFor(studentId, reqId) {
        return db.submissions[studentId + "|" + reqId] || { status: "missing", fileName: null, submittedAt: null, verifiedBy: null, verifiedAt: null, note: "" };
    }

    function isoDay(offsetDays) {
        return new Date(Date.now() + offsetDays * 86400000).toISOString().slice(0, 10);
    }

    // Scholars close to losing eligibility: low GWA, failing grades,
    // overdue or rejected requirements, or probation.
    function alerts() {
        var st = db.settings;
        var retention = Number(st.retentionGwa);
        var margin = Number(st.alertMargin) || 0;
        var passing = Number(st.passingGrade);
        var today = isoDay(0), soon = isoDay(7);
        var out = [];

        db.students.forEach(function (s) {
            if (s.status === "inactive") return;
            var reasons = [];
            var gwa = studentGwa(s.id);

            if (gwa !== null) {
                if (gwa < retention) {
                    reasons.push({ level: "critical", text: "GWA " + gwa.toFixed(2) + " is below the " + retention + " needed to keep the scholarship" });
                } else if (gwa < retention + margin) {
                    reasons.push({ level: "warning", text: "GWA " + gwa.toFixed(2) + " is only " + (gwa - retention).toFixed(2) + " above the " + retention + " minimum" });
                }
            }

            var failing = db.subjects.filter(function (sub) {
                var g = computeGrade(gradeFor(s.id, sub.code));
                return g !== null && g < passing;
            }).map(function (sub) { return sub.code; });
            if (failing.length) {
                reasons.push({ level: failing.length > 1 ? "critical" : "warning", text: "Failing " + failing.join(", ") });
            }

            db.requirements.forEach(function (r) {
                var sub = submissionFor(s.id, r.id);
                if (sub.status === "missing" && r.required) {
                    if (r.dueDate < today) reasons.push({ level: "critical", text: "Overdue: " + r.name });
                    else if (r.dueDate <= soon) reasons.push({ level: "warning", text: r.name + " not submitted, due " + r.dueDate });
                } else if (sub.status === "rejected") {
                    reasons.push({ level: r.dueDate < today ? "critical" : "warning", text: "Rejected: " + r.name });
                }
            });

            if (s.status === "probation") reasons.push({ level: "warning", text: "On scholarship probation" });

            if (reasons.length) {
                out.push({
                    student: s, gwa: gwa, reasons: reasons,
                    level: reasons.some(function (x) { return x.level === "critical"; }) ? "critical" : "warning"
                });
            }
        });

        out.sort(function (a, b) {
            if (a.level !== b.level) return a.level === "critical" ? -1 : 1;
            return b.reasons.length - a.reasons.length;
        });
        return out;
    }

    /* ---------- public API ---------- */

    window.GTData = {
        commit: commit,
        log: log,
        nextId: nextId,
        reset: function () { localStorage.removeItem(DB_KEY); load(); },

        get users() { return db.users; },
        get subjects() { return db.subjects; },
        get students() { return db.students; },
        get audit() { return db.audit; },
        get settings() { return db.settings; },
        get requirements() { return db.requirements; },

        submissionFor: submissionFor,
        setSubmission: function (studentId, reqId, data) {
            db.submissions[studentId + "|" + reqId] = Object.assign({}, submissionFor(studentId, reqId), data);
            commit();
        },
        removeRequirement: function (id) {
            db.requirements = db.requirements.filter(function (r) { return r.id !== id; });
            Object.keys(db.submissions).forEach(function (k) {
                if (k.split("|")[1] === id) delete db.submissions[k];
            });
            commit();
        },
        alerts: alerts,
        submissionsRaw: function () { return db.submissions; },

        userById: function (id) { return db.users.filter(function (u) { return u.id === id; })[0] || null; },
        userByEmail: function (email) {
            email = String(email || "").toLowerCase();
            return db.users.filter(function (u) { return u.email.toLowerCase() === email; })[0] || null;
        },
        subjectsFor: function (user) {
            if (!user) return [];
            return user.role === "superadmin"
                ? db.subjects.slice()
                : db.subjects.filter(function (s) { return s.teacherId === user.id; });
        },

        removeStudent: function (id) {
            db.students = db.students.filter(function (s) { return s.id !== id; });
            Object.keys(db.grades).forEach(function (k) {
                if (k.indexOf(id + "|") === 0) delete db.grades[k];
            });
            Object.keys(db.submissions).forEach(function (k) {
                if (k.indexOf(id + "|") === 0) delete db.submissions[k];
            });
            commit();
        },

        gradeFor: gradeFor,
        computeGrade: computeGrade,
        remarkFor: remarkFor,
        studentGwa: studentGwa,
        saveGrade: saveGrade
    };
})();
