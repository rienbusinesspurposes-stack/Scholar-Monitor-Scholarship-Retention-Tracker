/* =====================================================
   SCHOLARS — view, add and edit scholar records
   Delete is super admin only.
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var esc = GTUI.esc;
    var session = GranTrackSession.get();
    var isSuper = session.role === "superadmin";
    var settings = GTData.settings;

    var tbody = document.getElementById("scholars-body");
    var search = document.getElementById("scholar-search");
    var statusFilter = document.getElementById("scholar-status");
    var modal = document.getElementById("scholar-modal");
    var form = document.getElementById("scholar-form");
    var title = document.getElementById("scholar-modal-title");
    var errorBox = document.getElementById("scholar-error");

    var STATUS = {
        active: { label: "Active", cls: "success" },
        probation: { label: "Probation", cls: "warning" },
        inactive: { label: "Inactive", cls: "neutral" }
    };

    function render() {
        var term = search.value.trim().toLowerCase();
        var status = statusFilter.value;

        var rows = GTData.students.filter(function (s) {
            if (status !== "all" && s.status !== status) return false;
            return !term || (s.name + " " + s.studentNo + " " + s.course + " " + s.email).toLowerCase().indexOf(term) !== -1;
        });

        document.getElementById("scholar-count").textContent =
            rows.length + " of " + GTData.students.length + " scholars";

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="table-empty">No scholars match your search.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (s) {
            var gwa = GTData.studentGwa(s.id);
            var atRisk = gwa !== null && gwa < Number(settings.retentionGwa);
            var st = STATUS[s.status] || STATUS.active;

            return "<tr>" +
                '<td data-label="Scholar"><div class="person"><span class="person-avatar" aria-hidden="true">' + esc(s.name.charAt(0)) + "</span>" +
                "<div><strong>" + esc(s.name) + "</strong><span>" + esc(s.studentNo) + "</span></div></div></td>" +
                '<td data-label="Course">' + esc(s.course) + " · Year " + esc(s.year) + "</td>" +
                '<td data-label="Scholarship">' + esc(s.scholarship) + "</td>" +
                '<td data-label="GWA"><strong>' + (gwa === null ? "—" : gwa.toFixed(2)) + "</strong>" +
                (atRisk ? ' <span class="badge danger">Below ' + esc(settings.retentionGwa) + "</span>" : "") + "</td>" +
                '<td data-label="Status"><span class="badge ' + st.cls + '">' + st.label + "</span></td>" +
                '<td class="actions-cell"><button type="button" class="btn btn-sm btn-secondary" data-edit="' + s.id + '">Edit</button>' +
                (isSuper ? ' <button type="button" class="btn btn-sm btn-danger-ghost" data-delete="' + s.id + '">Delete</button>' : "") +
                "</td></tr>";
        }).join("");
    }

    function openForm(student) {
        errorBox.textContent = "";
        form.reset();
        form.elements.id.value = student ? student.id : "";
        title.textContent = student ? "Edit Scholar" : "Add Scholar";
        if (student) {
            ["name", "studentNo", "email", "course", "year", "scholarship", "status"].forEach(function (f) {
                form.elements[f].value = student[f];
            });
        }
        GTUI.openModal(modal);
    }

    document.getElementById("add-scholar").addEventListener("click", function () { openForm(null); });

    tbody.addEventListener("click", function (event) {
        var editBtn = event.target.closest("[data-edit]");
        var delBtn = event.target.closest("[data-delete]");

        if (editBtn) {
            openForm(GTData.students.filter(function (s) { return s.id === editBtn.getAttribute("data-edit"); })[0]);
        }

        if (delBtn && isSuper) {
            var id = delBtn.getAttribute("data-delete");
            var student = GTData.students.filter(function (s) { return s.id === id; })[0];
            GTUI.confirm({
                icon: "🗑", title: "Delete scholar?",
                message: student.name + " and all of their grades will be permanently removed.",
                confirmText: "Delete"
            }).then(function (ok) {
                if (!ok) return;
                GTData.removeStudent(id);
                GTData.log("scholar", "Deleted scholar", student.studentNo + " · " + student.name, "success");
                render();
                GTUI.toast("Scholar deleted.");
            });
        }
    });

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        errorBox.textContent = "";

        var f = form.elements;
        var data = {
            name: f.name.value.trim(),
            studentNo: f.studentNo.value.trim(),
            email: f.email.value.trim(),
            course: f.course.value.trim().toUpperCase(),
            year: Number(f.year.value),
            scholarship: f.scholarship.value,
            status: f.status.value
        };

        if (!data.name || !data.studentNo || !data.course) {
            errorBox.textContent = "Name, student number and course are required.";
            return;
        }
        if (data.email && !/^[^\s@]+@mcc\.edu\.ph$/i.test(data.email)) {
            errorBox.textContent = "Use an @mcc.edu.ph email address.";
            return;
        }

        var id = f.id.value;
        var duplicate = GTData.students.some(function (s) {
            return s.studentNo.toLowerCase() === data.studentNo.toLowerCase() && s.id !== id;
        });
        if (duplicate) {
            errorBox.textContent = "That student number already exists.";
            return;
        }

        if (id) {
            var existing = GTData.students.filter(function (s) { return s.id === id; })[0];
            Object.assign(existing, data);
            GTData.log("scholar", "Edited scholar", data.studentNo + " · " + data.name, "success");
            GTUI.toast("Scholar updated.");
        } else {
            data.id = GTData.nextId("student", "s");
            GTData.students.push(data);
            GTData.log("scholar", "Added scholar", data.studentNo + " · " + data.name, "success");
            GTUI.toast("Scholar added.");
        }

        GTData.commit();
        GTUI.closeModal(modal);
        render();
    });

    search.addEventListener("input", render);
    statusFilter.addEventListener("change", render);
    render();
});
