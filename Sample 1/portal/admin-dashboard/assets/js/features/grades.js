/* =====================================================
   GRADES — teachers (and super admins) edit grades
   - Pick a subject, edit midterm / final inline
   - Final grade = average of midterm and final
   - Changes are saved together and written to the audit log
   - Teachers are blocked while the super admin has locked grading
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var esc = GTUI.esc;
    var session = GranTrackSession.get();
    var me = GTData.userById(session.id) || session;
    var isSuper = session.role === "superadmin";
    var settings = GTData.settings;

    var subjectSelect = document.getElementById("subject-select");
    var searchInput = document.getElementById("grade-search");
    var statusFilter = document.getElementById("status-filter");
    var tbody = document.getElementById("grades-body");
    var saveBar = document.getElementById("save-bar");
    var saveCount = document.getElementById("save-count");
    var saveButton = document.getElementById("save-grades");
    var discardButton = document.getElementById("discard-grades");
    var exportButton = document.getElementById("export-grades");
    var lockBanner = document.getElementById("lock-banner");
    var tableWrap = document.getElementById("grades-table-wrap");
    var emptyState = document.getElementById("grades-empty");

    var subjects = GTData.subjectsFor(me);
    var edits = {};          // studentId -> { midterm, final, invalid }
    var currentCode = null;

    var locked = settings.gradeLock && !isSuper;

    /* ---------- no subjects ---------- */

    if (!subjects.length) {
        tableWrap.hidden = true;
        document.getElementById("grades-toolbar").hidden = true;
        emptyState.hidden = false;
        return;
    }

    subjectSelect.innerHTML = subjects.map(function (s) {
        return '<option value="' + esc(s.code) + '">' + esc(s.code) + " — " + esc(s.title) + "</option>";
    }).join("");
    currentCode = subjects[0].code;

    if (settings.gradeLock) {
        lockBanner.hidden = false;
        lockBanner.querySelector("p").textContent = isSuper
            ? "Grade editing is locked for teachers. You can still edit as a super admin."
            : "Grade editing is locked by the super admin. You can view grades but cannot change them.";
    }

    /* ---------- helpers ---------- */

    function currentSubject() {
        return subjects.filter(function (s) { return s.code === currentCode; })[0];
    }

    function parseScore(text) {
        text = String(text).trim();
        if (text === "") return { value: null, ok: true };
        var n = Number(text);
        if (!isFinite(n) || n < 0 || n > 100) return { value: null, ok: false };
        return { value: Math.round(n * 100) / 100, ok: true };
    }

    function dirtyCount() {
        return Object.keys(edits).length;
    }

    function remarkBadge(remark) {
        var label = remark === "passed" ? "Passed" : remark === "failed" ? "Failed" : "Incomplete";
        var cls = remark === "passed" ? "success" : remark === "failed" ? "danger" : "warning";
        return '<span class="badge ' + cls + '">' + label + "</span>";
    }

    function updateSaveBar() {
        var n = dirtyCount();
        var invalid = Object.keys(edits).some(function (k) { return edits[k].invalid; });
        saveBar.classList.toggle("show", n > 0);
        saveCount.textContent = n + " unsaved change" + (n === 1 ? "" : "s") + (invalid ? " · fix the highlighted scores" : "");
        saveButton.disabled = invalid;
    }

    function updateStats() {
        var sub = currentSubject();
        var scores = [];
        var failing = 0;
        GTData.students.forEach(function (st) {
            var g = GTData.computeGrade(GTData.gradeFor(st.id, sub.code));
            if (g !== null) {
                scores.push(g);
                if (g < Number(settings.passingGrade)) failing++;
            }
        });
        var avg = scores.length ? (scores.reduce(function (a, b) { return a + b; }, 0) / scores.length).toFixed(2) : "—";
        document.getElementById("stat-students").textContent = GTData.students.length;
        document.getElementById("stat-graded").textContent = scores.length + " / " + GTData.students.length;
        document.getElementById("stat-average").textContent = avg;
        document.getElementById("stat-failing").textContent = failing;
        document.getElementById("subject-meta").textContent =
            sub.units + " unit" + (sub.units === 1 ? "" : "s") + " · passing grade " + settings.passingGrade +
            (isSuper ? "" : " · " + (me.name || ""));
    }

    /* ---------- render ---------- */

    function render() {
        var sub = currentSubject();
        var term = searchInput.value.trim().toLowerCase();
        var filter = statusFilter.value;

        var rows = GTData.students.filter(function (st) {
            var g = GTData.gradeFor(st.id, sub.code);
            var remark = GTData.remarkFor(GTData.computeGrade(g));
            if (filter !== "all" && remark !== filter) return false;
            if (!term) return true;
            return (st.name + " " + st.studentNo + " " + st.course).toLowerCase().indexOf(term) !== -1;
        });

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="table-empty">No students match your filters.</td></tr>';
            updateStats();
            return;
        }

        tbody.innerHTML = rows.map(function (st) {
            var saved = GTData.gradeFor(st.id, sub.code);
            var e = edits[st.id];
            var mid = e ? e.midRaw : saved.midterm;
            var fin = e ? e.finRaw : saved.final;
            var grade = GTData.computeGrade({
                midterm: e ? e.midterm : saved.midterm,
                final: e ? e.final : saved.final
            });
            var updatedBy = saved.updatedBy ? (GTData.userById(saved.updatedBy) || {}).name : null;

            return '<tr data-id="' + st.id + '"' + (e ? ' class="is-dirty"' : "") + ">" +
                '<td data-label="Student"><div class="person"><span class="person-avatar" aria-hidden="true">' + esc(st.name.charAt(0)) + "</span>" +
                "<div><strong>" + esc(st.name) + "</strong><span>" + esc(st.studentNo) + " · " + esc(st.course) + " " + st.year + "</span></div></div></td>" +
                '<td data-label="Midterm">' + scoreInput("midterm", mid, st.name, e && e.midInvalid) + "</td>" +
                '<td data-label="Final">' + scoreInput("final", fin, st.name, e && e.finInvalid) + "</td>" +
                '<td data-label="Grade" class="grade-cell"><strong data-grade>' + (grade === null ? "—" : grade.toFixed(2)) + "</strong></td>" +
                '<td data-label="Remarks" data-remark>' + remarkBadge(GTData.remarkFor(grade)) + "</td>" +
                '<td data-label="Last updated" class="muted-cell">' + (saved.updatedAt ? esc(GTUI.fmtDateTime(saved.updatedAt)) + (updatedBy ? "<br><small>" + esc(updatedBy) + "</small>" : "") : "—") + "</td>" +
                "</tr>";
        }).join("");

        updateStats();
    }

    function scoreInput(field, value, name, invalid) {
        return '<input type="text" inputmode="decimal" class="grade-input" data-field="' + field + '"' +
            ' value="' + (value === null || value === undefined ? "" : esc(value)) + '"' +
            ' placeholder="—" maxlength="6"' +
            ' aria-label="' + (field === "midterm" ? "Midterm" : "Final") + " score for " + esc(name) + '"' +
            (invalid ? ' aria-invalid="true"' : "") +
            (locked ? " disabled" : "") + ">";
    }

    /* ---------- editing ---------- */

    tbody.addEventListener("input", function (event) {
        var input = event.target;
        if (!input.classList.contains("grade-input")) return;

        var row = input.closest("tr");
        var id = row.getAttribute("data-id");
        var sub = currentSubject();
        var saved = GTData.gradeFor(id, sub.code);

        var midInput = row.querySelector('[data-field="midterm"]');
        var finInput = row.querySelector('[data-field="final"]');
        var mid = parseScore(midInput.value);
        var fin = parseScore(finInput.value);

        midInput.setAttribute("aria-invalid", mid.ok ? "false" : "true");
        finInput.setAttribute("aria-invalid", fin.ok ? "false" : "true");

        var changed = mid.value !== saved.midterm || fin.value !== saved.final || !mid.ok || !fin.ok;

        if (changed) {
            edits[id] = {
                midterm: mid.value, final: fin.value,
                midRaw: midInput.value, finRaw: finInput.value,
                midInvalid: !mid.ok, finInvalid: !fin.ok,
                invalid: !mid.ok || !fin.ok
            };
        } else {
            delete edits[id];
        }

        row.classList.toggle("is-dirty", changed);

        var grade = mid.ok && fin.ok ? GTData.computeGrade({ midterm: mid.value, final: fin.value }) : null;
        row.querySelector("[data-grade]").textContent = grade === null ? "—" : grade.toFixed(2);
        row.querySelector("[data-remark]").innerHTML = remarkBadge(GTData.remarkFor(grade));

        updateSaveBar();
    });

    // Enter moves down to the same column in the next row
    tbody.addEventListener("keydown", function (event) {
        if (event.key !== "Enter" || !event.target.classList.contains("grade-input")) return;
        event.preventDefault();
        var field = event.target.getAttribute("data-field");
        var next = event.target.closest("tr").nextElementSibling;
        if (next) {
            var target = next.querySelector('[data-field="' + field + '"]');
            if (target) { target.focus(); target.select(); }
        }
    });

    saveButton.addEventListener("click", function () {
        var sub = currentSubject();
        var changedRows = 0;

        // Teachers may only save subjects assigned to them (re-checked here, not only in the UI)
        if (!isSuper && sub.teacherId !== me.id) {
            GTUI.toast("You are not assigned to this subject.", "error");
            return;
        }
        if (locked) {
            GTUI.toast("Grade editing is locked.", "error");
            return;
        }

        Object.keys(edits).forEach(function (id) {
            var e = edits[id];
            if (GTData.saveGrade(id, sub.code, e.midterm, e.final)) changedRows++;
        });

        edits = {};
        updateSaveBar();
        render();
        GTUI.toast(changedRows + " grade" + (changedRows === 1 ? "" : "s") + " saved for " + sub.code + ".");
    });

    discardButton.addEventListener("click", function () {
        edits = {};
        updateSaveBar();
        render();
    });

    /* ---------- filters ---------- */

    subjectSelect.addEventListener("change", function () {
        if (!dirtyCount()) {
            currentCode = subjectSelect.value;
            render();
            return;
        }
        GTUI.confirm({
            icon: "!", title: "Discard changes?",
            message: "You have unsaved grade changes. Switching subjects will discard them.",
            confirmText: "Discard"
        }).then(function (ok) {
            if (ok) {
                edits = {};
                currentCode = subjectSelect.value;
                updateSaveBar();
                render();
            } else {
                subjectSelect.value = currentCode;
            }
        });
    });

    searchInput.addEventListener("input", render);
    statusFilter.addEventListener("change", render);

    window.addEventListener("beforeunload", function (event) {
        if (dirtyCount()) {
            event.preventDefault();
            event.returnValue = "";
        }
    });

    /* ---------- CSV export ---------- */

    exportButton.addEventListener("click", function () {
        var sub = currentSubject();
        var lines = [["Student No", "Name", "Course", "Midterm", "Final", "Grade", "Remarks"]];
        GTData.students.forEach(function (st) {
            var g = GTData.gradeFor(st.id, sub.code);
            var grade = GTData.computeGrade(g);
            lines.push([st.studentNo, st.name, st.course + " " + st.year,
                g.midterm === null ? "" : g.midterm, g.final === null ? "" : g.final,
                grade === null ? "" : grade, GTData.remarkFor(grade)]);
        });
        var csv = lines.map(function (r) {
            return r.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(",");
        }).join("\r\n");
        var link = document.createElement("a");
        link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        link.download = "grades-" + sub.code + ".csv";
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    });

    render();
});
