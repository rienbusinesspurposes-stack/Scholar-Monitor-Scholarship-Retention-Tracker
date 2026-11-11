/* =====================================================
   REQUIREMENTS — admins and super admins
   Tab 1: Submissions   verify or reject submitted documents
   Tab 2: Requirements  add and edit what scholars must submit
   Tab 3: Scholarship   verify each scholar's scholarship status
   Every action is written to the audit log.
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var esc = GTUI.esc;
    var session = GranTrackSession.get();
    var isSuper = session.role === "superadmin";

    var tabs = document.querySelectorAll("[data-tab]");
    var panels = document.querySelectorAll("[data-panel]");

    var STATUS = {
        verified: { label: "Verified", cls: "success" },
        pending: { label: "Pending", cls: "warning" },
        rejected: { label: "Rejected", cls: "danger" },
        missing: { label: "Not submitted", cls: "neutral" }
    };

    var SCHOLAR_STATUS = {
        active: { label: "Active", cls: "success" },
        probation: { label: "Probation", cls: "warning" },
        inactive: { label: "Inactive", cls: "neutral" }
    };

    function actor() {
        return { id: session.id, name: session.name };
    }

    function today() {
        return new Date().toISOString().slice(0, 10);
    }

    function studentById(id) {
        return GTData.students.filter(function (s) { return s.id === id; })[0];
    }

    function reqById(id) {
        return GTData.requirements.filter(function (r) { return r.id === id; })[0];
    }

    function fmtDay(iso) {
        return iso ? GTUI.fmtDate(iso + "T00:00:00") : "—";
    }

    /* ---------- tabs ---------- */

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            var name = tab.getAttribute("data-tab");
            tabs.forEach(function (t) {
                var on = t === tab;
                t.classList.toggle("active", on);
                t.setAttribute("aria-selected", on ? "true" : "false");
            });
            panels.forEach(function (p) { p.hidden = p.getAttribute("data-panel") !== name; });
        });
    });

    /* =================== Submissions =================== */

    var reqSelect = document.getElementById("sub-requirement");
    var statusSelect = document.getElementById("sub-status");
    var subSearch = document.getElementById("sub-search");
    var subBody = document.getElementById("submissions-body");
    var rejectModal = document.getElementById("reject-modal");
    var rejectForm = document.getElementById("reject-form");
    var rejectError = document.getElementById("reject-error");

    function fillRequirementFilter() {
        var keep = reqSelect.value;
        reqSelect.innerHTML = '<option value="all">All requirements</option>' +
            GTData.requirements.map(function (r) {
                return '<option value="' + esc(r.id) + '">' + esc(r.name) + "</option>";
            }).join("");
        reqSelect.value = reqById(keep) ? keep : "all";
    }

    function renderStats() {
        var counts = { pending: 0, verified: 0, rejected: 0, missing: 0 };
        GTData.students.forEach(function (st) {
            GTData.requirements.forEach(function (r) {
                counts[GTData.submissionFor(st.id, r.id).status]++;
            });
        });
        Object.keys(counts).forEach(function (k) {
            document.getElementById("stat-" + k).textContent = counts[k];
        });
    }

    function renderSubmissions() {
        var term = subSearch.value.trim().toLowerCase();
        var rows = [];

        GTData.students.forEach(function (st) {
            GTData.requirements.forEach(function (r) {
                var sub = GTData.submissionFor(st.id, r.id);
                if (reqSelect.value !== "all" && r.id !== reqSelect.value) return;
                if (statusSelect.value !== "all" && sub.status !== statusSelect.value) return;
                if (term && (st.name + " " + st.studentNo + " " + r.name).toLowerCase().indexOf(term) === -1) return;
                rows.push({ st: st, r: r, sub: sub });
            });
        });

        document.getElementById("sub-count").textContent = rows.length + " submission" + (rows.length === 1 ? "" : "s");

        if (!rows.length) {
            subBody.innerHTML = '<tr><td colspan="6" class="table-empty">Nothing matches your filters.</td></tr>';
            return;
        }

        subBody.innerHTML = rows.map(function (row) {
            var sub = row.sub;
            var meta = STATUS[sub.status];
            var overdue = sub.status === "missing" && row.r.required && row.r.dueDate < today();
            var by = sub.verifiedBy ? (GTData.userById(sub.verifiedBy) || {}).name : "";
            var key = 'data-s="' + row.st.id + '" data-r="' + row.r.id + '"';
            var actions = "";

            if (sub.hasFile) {
                actions += '<button type="button" class="btn btn-sm btn-secondary" data-view ' + key + ">View file</button> " +
                    '<button type="button" class="btn btn-sm btn-secondary" data-download ' + key + ">Download</button> ";
            }
            if (sub.status === "pending" || sub.status === "rejected") {
                actions += '<button type="button" class="btn btn-sm btn-primary" data-verify ' + key + ">Verify</button> ";
            }
            if (sub.status === "pending") {
                actions += '<button type="button" class="btn btn-sm btn-danger-ghost" data-reject ' + key + ">Reject</button>";
            }
            if (sub.status === "verified") {
                actions += '<button type="button" class="btn btn-sm btn-secondary" data-undo ' + key + ">Undo</button>";
            }

            return "<tr>" +
                '<td data-label="Scholar"><div class="person"><span class="person-avatar" aria-hidden="true">' + esc(row.st.name.charAt(0)) + "</span>" +
                "<div><strong>" + esc(row.st.name) + "</strong><span>" + esc(row.st.studentNo) + "</span></div></div></td>" +
                '<td data-label="Requirement"><strong>' + esc(row.r.name) + "</strong><br><small>Due " + esc(fmtDay(row.r.dueDate)) +
                (row.r.required ? "" : " · optional") + "</small></td>" +
                '<td data-label="File" class="muted-cell">' + (sub.fileName ? esc(sub.fileName) + "<br><small>" + esc(GTUI.fmtDateTime(sub.submittedAt)) + "</small>" : "—") + "</td>" +
                '<td data-label="Status"><span class="badge ' + meta.cls + '">' + meta.label + "</span>" +
                (overdue ? ' <span class="badge danger">Overdue</span>' : "") +
                (sub.status === "rejected" && sub.note ? "<br><small>" + esc(sub.note) + "</small>" : "") +
                (by && sub.status !== "pending" ? "<br><small>by " + esc(by) + "</small>" : "") + "</td>" +
                '<td class="actions-cell">' + (actions || '<span class="muted-cell">Awaiting scholar</span>') + "</td></tr>";
        }).join("");
    }

    function setStatus(studentId, reqId, status, note) {
        var st = studentById(studentId);
        var r = reqById(reqId);
        var before = GTData.submissionFor(studentId, reqId).status;
        var isDecision = status === "verified" || status === "rejected";

        GTData.setSubmission(studentId, reqId, {
            status: status,
            note: note || "",
            verifiedBy: isDecision ? actor().id : null,
            verifiedAt: isDecision ? new Date().toISOString() : null
        });

        var verb = status === "verified" ? "Verified document" : status === "rejected" ? "Rejected document" : "Reset document to pending";
        GTData.log("document", verb, st.studentNo + " · " + r.name + " (" + before + " → " + status + ")" + (note ? " · " + note : ""), "success");
        refreshAll();
    }

    subBody.addEventListener("click", function (event) {
        var btn = event.target.closest("button");
        if (!btn) return;
        var s = btn.getAttribute("data-s");
        var r = btn.getAttribute("data-r");

        if (btn.hasAttribute("data-download")) {
            GTFiles.download(s + "|" + r).then(function (found) {
                if (!found) GTUI.toast("File not found in this browser.", "error");
            });
        } else if (btn.hasAttribute("data-view")) {
            GTFiles.open(s + "|" + r).then(function (found) {
                if (!found) GTUI.toast("File not found in this browser.", "error");
            });
        } else if (btn.hasAttribute("data-verify")) {
            setStatus(s, r, "verified");
            GTUI.toast("Document verified.");
        } else if (btn.hasAttribute("data-undo")) {
            setStatus(s, r, "pending");
            GTUI.toast("Moved back to pending.", "info");
        } else if (btn.hasAttribute("data-reject")) {
            rejectError.textContent = "";
            rejectForm.reset();
            rejectForm.elements.studentId.value = s;
            rejectForm.elements.reqId.value = r;
            document.getElementById("reject-target").textContent =
                studentById(s).name + " · " + reqById(r).name;
            GTUI.openModal(rejectModal);
        }
    });

    rejectForm.addEventListener("submit", function (event) {
        event.preventDefault();
        var reason = rejectForm.elements.reason.value.trim();
        if (reason.length < 5) {
            rejectError.textContent = "Please give the scholar a short reason.";
            return;
        }
        setStatus(rejectForm.elements.studentId.value, rejectForm.elements.reqId.value, "rejected", reason);
        GTUI.closeModal(rejectModal);
        GTUI.toast("Document rejected.", "info");
    });

    [reqSelect, statusSelect].forEach(function (el) { el.addEventListener("change", renderSubmissions); });
    subSearch.addEventListener("input", renderSubmissions);

    /* =================== Requirement types =================== */

    var typeBody = document.getElementById("types-body");
    var typeModal = document.getElementById("type-modal");
    var typeForm = document.getElementById("type-form");
    var typeError = document.getElementById("type-error");

    function renderTypes() {
        if (!GTData.requirements.length) {
            typeBody.innerHTML = '<tr><td colspan="5" class="table-empty">No requirements yet. Add the first one.</td></tr>';
            return;
        }

        typeBody.innerHTML = GTData.requirements.map(function (r) {
            var verified = 0, submitted = 0;
            GTData.students.forEach(function (st) {
                var s = GTData.submissionFor(st.id, r.id).status;
                if (s !== "missing") submitted++;
                if (s === "verified") verified++;
            });
            var passed = r.dueDate < today();

            return "<tr>" +
                '<td data-label="Requirement"><strong>' + esc(r.name) + "</strong><br><small>" + esc(r.description || "") + "</small></td>" +
                '<td data-label="Due date" class="nowrap">' + esc(fmtDay(r.dueDate)) + (passed ? ' <span class="badge neutral">Past</span>' : "") + "</td>" +
                '<td data-label="Required"><span class="badge ' + (r.required ? "crimson" : "neutral") + '">' + (r.required ? "Required" : "Optional") + "</span></td>" +
                '<td data-label="Progress">' + submitted + " submitted · " + verified + " verified</td>" +
                '<td class="actions-cell"><button type="button" class="btn btn-sm btn-secondary" data-edit="' + r.id + '">Edit</button>' +
                (isSuper ? ' <button type="button" class="btn btn-sm btn-danger-ghost" data-delete="' + r.id + '">Delete</button>' : "") + "</td></tr>";
        }).join("");
    }

    function openType(r) {
        typeError.textContent = "";
        typeForm.reset();
        typeForm.elements.id.value = r ? r.id : "";
        document.getElementById("type-modal-title").textContent = r ? "Edit Requirement" : "Add Requirement";
        if (r) {
            typeForm.elements.name.value = r.name;
            typeForm.elements.description.value = r.description || "";
            typeForm.elements.dueDate.value = r.dueDate;
            typeForm.elements.required.checked = !!r.required;
        } else {
            typeForm.elements.required.checked = true;
        }
        GTUI.openModal(typeModal);
    }

    document.getElementById("add-type").addEventListener("click", function () { openType(null); });

    typeBody.addEventListener("click", function (event) {
        var edit = event.target.closest("[data-edit]");
        var del = event.target.closest("[data-delete]");

        if (edit) openType(reqById(edit.getAttribute("data-edit")));

        if (del && isSuper) {
            var r = reqById(del.getAttribute("data-delete"));
            GTUI.confirm({
                icon: "🗑", title: "Delete requirement?",
                message: '"' + r.name + '" and every scholar submission for it will be removed.',
                confirmText: "Delete"
            }).then(function (ok) {
                if (!ok) return;
                GTData.removeRequirement(r.id);
                GTData.log("requirement", "Deleted requirement", r.name, "success");
                fillRequirementFilter();
                refreshAll();
                GTUI.toast("Requirement deleted.");
            });
        }
    });

    typeForm.addEventListener("submit", function (event) {
        event.preventDefault();
        typeError.textContent = "";

        var f = typeForm.elements;
        var data = {
            name: f.name.value.trim(),
            description: f.description.value.trim(),
            dueDate: f.dueDate.value,
            required: f.required.checked
        };

        if (!data.name || !data.dueDate) {
            typeError.textContent = "Name and due date are required.";
            return;
        }

        var id = f.id.value;
        var dup = GTData.requirements.some(function (r) {
            return r.name.toLowerCase() === data.name.toLowerCase() && r.id !== id;
        });
        if (dup) {
            typeError.textContent = "A requirement with that name already exists.";
            return;
        }

        if (id) {
            Object.assign(reqById(id), data);
            GTData.log("requirement", "Edited requirement", data.name + " (due " + data.dueDate + ")", "success");
            GTUI.toast("Requirement updated.");
        } else {
            data.id = GTData.nextId("req", "r");
            data.file = "document.pdf";
            GTData.requirements.push(data);
            GTData.log("requirement", "Added requirement", data.name + " (due " + data.dueDate + ")", "success");
            GTUI.toast("Requirement added.");
        }

        GTData.commit();
        GTUI.closeModal(typeModal);
        fillRequirementFilter();
        refreshAll();
    });

    /* =================== Scholarship status =================== */

    var statusBody = document.getElementById("status-body");
    var statusSearch = document.getElementById("status-search");

    function renderStatusTab() {
        var term = statusSearch.value.trim().toLowerCase();
        var rows = GTData.students.filter(function (s) {
            return !term || (s.name + " " + s.studentNo).toLowerCase().indexOf(term) !== -1;
        });

        if (!rows.length) {
            statusBody.innerHTML = '<tr><td colspan="5" class="table-empty">No scholars match your search.</td></tr>';
            return;
        }

        statusBody.innerHTML = rows.map(function (s) {
            var gwa = GTData.studentGwa(s.id);
            var meta = SCHOLAR_STATUS[s.status] || SCHOLAR_STATUS.active;
            var by = s.statusVerifiedBy ? (GTData.userById(s.statusVerifiedBy) || {}).name : null;

            return "<tr>" +
                '<td data-label="Scholar"><div class="person"><span class="person-avatar" aria-hidden="true">' + esc(s.name.charAt(0)) + "</span>" +
                "<div><strong>" + esc(s.name) + "</strong><span>" + esc(s.studentNo) + " · " + esc(s.scholarship) + "</span></div></div></td>" +
                '<td data-label="GWA"><strong>' + (gwa === null ? "—" : gwa.toFixed(2)) + "</strong></td>" +
                '<td data-label="Current"><span class="badge ' + meta.cls + '">' + meta.label + "</span></td>" +
                '<td data-label="Verified" class="muted-cell">' + (s.statusVerifiedAt ? esc(GTUI.fmtDateTime(s.statusVerifiedAt)) + (by ? "<br><small>by " + esc(by) + "</small>" : "") : "Not yet verified") + "</td>" +
                '<td class="actions-cell"><select class="select status-select" data-pick="' + s.id + '" aria-label="Scholarship status for ' + esc(s.name) + '">' +
                ["active", "probation", "inactive"].map(function (v) {
                    return '<option value="' + v + '"' + (v === s.status ? " selected" : "") + ">" + SCHOLAR_STATUS[v].label + "</option>";
                }).join("") +
                '</select> <button type="button" class="btn btn-sm btn-primary" data-confirm="' + s.id + '">Verify</button></td></tr>';
        }).join("");
    }

    statusBody.addEventListener("click", function (event) {
        var btn = event.target.closest("[data-confirm]");
        if (!btn) return;
        var id = btn.getAttribute("data-confirm");
        var s = studentById(id);
        var pick = statusBody.querySelector('[data-pick="' + id + '"]').value;
        var before = s.status;

        s.status = pick;
        s.statusVerifiedBy = session.id;
        s.statusVerifiedAt = new Date().toISOString();
        GTData.log("document", "Verified scholarship status", s.studentNo + " · " + s.name + " (" + before + (before === pick ? ", confirmed" : " → " + pick) + ")", "success");
        GTData.commit();
        refreshAll();
        GTUI.toast("Scholarship status verified.");
    });

    statusSearch.addEventListener("input", renderStatusTab);

    /* ---------- init ---------- */

    function refreshAll() {
        renderStats();
        renderSubmissions();
        renderTypes();
        renderStatusTab();
    }

    fillRequirementFilter();
    refreshAll();

    // A student uploaded something in another tab: update the lists live
    var pendingBefore = Number(document.getElementById("stat-pending").textContent);
    window.addEventListener("gt:datachanged", function () {
        refreshAll();
        var now = Number(document.getElementById("stat-pending").textContent);
        if (now > pendingBefore) GTUI.toast("New document submitted by a scholar.", "info");
        pendingBefore = now;
    });
});
