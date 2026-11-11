/* =====================================================
   ACCOUNTS — super admin only
   Create accounts, change roles, assign subjects,
   disable / enable / unlock / delete.
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var esc = GTUI.esc;
    var session = GranTrackSession.get();

    var tbody = document.getElementById("accounts-body");
    var search = document.getElementById("account-search");
    var roleFilter = document.getElementById("account-role");
    var modal = document.getElementById("account-modal");
    var form = document.getElementById("account-form");
    var title = document.getElementById("account-modal-title");
    var errorBox = document.getElementById("account-error");
    var subjectBox = document.getElementById("subject-checks");
    var subjectField = document.getElementById("subject-field");
    var passwordHint = document.getElementById("password-hint");

    var STATUS = {
        active: { label: "Active", cls: "success" },
        disabled: { label: "Disabled", cls: "neutral" },
        locked: { label: "Locked", cls: "danger" }
    };

    function activeSuperAdmins() {
        return GTData.users.filter(function (u) { return u.role === "superadmin" && u.status === "active"; });
    }

    // True when this change would leave the system with no usable super admin
    function wouldRemoveLastSuper(user) {
        return user.role === "superadmin" && user.status === "active" && activeSuperAdmins().length <= 1;
    }

    function render() {
        var term = search.value.trim().toLowerCase();
        var role = roleFilter.value;

        var rows = GTData.users.filter(function (u) {
            if (role !== "all" && u.role !== role) return false;
            return !term || (u.name + " " + u.email).toLowerCase().indexOf(term) !== -1;
        });

        document.getElementById("account-count").textContent = rows.length + " of " + GTData.users.length + " accounts";

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="table-empty">No accounts match your search.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (u) {
            var st = STATUS[u.status] || STATUS.active;
            var isSelf = u.id === session.id;
            var subjects = GTData.subjectsFor(u).map(function (s) { return s.code; });

            var actions = '<button type="button" class="btn btn-sm btn-secondary" data-edit="' + u.id + '">Edit</button>';
            if (u.status === "locked") {
                actions += ' <button type="button" class="btn btn-sm btn-secondary" data-unlock="' + u.id + '">Unlock</button>';
            }
            if (!isSelf) {
                actions += ' <button type="button" class="btn btn-sm btn-secondary" data-toggle="' + u.id + '">' +
                    (u.status === "disabled" ? "Enable" : "Disable") + "</button>" +
                    ' <button type="button" class="btn btn-sm btn-danger-ghost" data-delete="' + u.id + '">Delete</button>';
            }

            return "<tr>" +
                '<td data-label="Account"><div class="person"><span class="person-avatar" aria-hidden="true">' +
                esc(u.name.replace(/^(Prof\.|Dr\.)\s*/i, "").charAt(0)) + "</span>" +
                "<div><strong>" + esc(u.name) + (isSelf ? ' <span class="you-tag">You</span>' : "") + "</strong><span>" + esc(u.email) + "</span></div></div></td>" +
                '<td data-label="Role"><span class="badge ' + (u.role === "superadmin" ? "crimson" : "neutral") + '">' + esc(GTUI.roleLabel(u.role)) + "</span></td>" +
                '<td data-label="Subjects">' + (u.role === "teacher" ? (subjects.length ? esc(subjects.join(", ")) : '<span class="muted-cell">None assigned</span>') : '<span class="muted-cell">All</span>') + "</td>" +
                '<td data-label="Status"><span class="badge ' + st.cls + '">' + st.label + "</span></td>" +
                '<td data-label="Last sign-in" class="muted-cell">' + (u.lastLogin ? esc(GTUI.fmtDateTime(u.lastLogin)) : "Never") + "</td>" +
                '<td class="actions-cell">' + actions + "</td></tr>";
        }).join("");
    }

    /* ---------- form ---------- */

    function renderSubjectChecks(user) {
        subjectBox.innerHTML = GTData.subjects.map(function (s) {
            var owner = s.teacherId && s.teacherId !== (user && user.id) ? GTData.userById(s.teacherId) : null;
            var checked = user && s.teacherId === user.id;
            return '<label class="check"><input type="checkbox" name="subjects" value="' + esc(s.code) + '"' + (checked ? " checked" : "") + ">" +
                "<span><strong>" + esc(s.code) + "</strong> " + esc(s.title) +
                (owner ? ' <em>(currently ' + esc(owner.name) + ")</em>" : "") + "</span></label>";
        }).join("");
    }

    function syncSubjectVisibility() {
        subjectField.hidden = form.elements.role.value !== "teacher";
    }

    function openForm(user) {
        errorBox.textContent = "";
        form.reset();
        form.elements.id.value = user ? user.id : "";
        title.textContent = user ? "Edit Account" : "Create Account";
        passwordHint.textContent = user
            ? "Leave blank to keep the current password."
            : "At least " + GTData.settings.minPasswordLength + " characters.";
        form.elements.password.required = !user;

        if (user) {
            form.elements.name.value = user.name;
            form.elements.email.value = user.email;
            form.elements.role.value = user.role;
        }
        renderSubjectChecks(user);
        syncSubjectVisibility();
        GTUI.openModal(modal);
    }

    document.getElementById("add-account").addEventListener("click", function () { openForm(null); });
    form.elements.role.addEventListener("change", syncSubjectVisibility);

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        errorBox.textContent = "";

        var f = form.elements;
        var id = f.id.value;
        var name = f.name.value.trim();
        var email = f.email.value.trim();
        var role = f.role.value;
        var password = f.password.value;
        var min = Number(GTData.settings.minPasswordLength) || 8;

        if (!name || !email) { errorBox.textContent = "Name and email are required."; return; }
        if (!/^[^\s@]+@mcc\.edu\.ph$/i.test(email)) { errorBox.textContent = "Use an @mcc.edu.ph email address."; return; }

        var taken = GTData.users.some(function (u) { return u.email.toLowerCase() === email.toLowerCase() && u.id !== id; });
        if (taken) { errorBox.textContent = "An account with that email already exists."; return; }

        if ((!id || password) && password.length < min) {
            errorBox.textContent = "Password must be at least " + min + " characters.";
            return;
        }

        var existing = id ? GTData.userById(id) : null;

        if (existing && existing.role === "superadmin" && role !== "superadmin" && wouldRemoveLastSuper(existing)) {
            errorBox.textContent = "You cannot remove the last active super admin.";
            return;
        }
        if (existing && existing.id === session.id && role !== session.role) {
            errorBox.textContent = "You cannot change your own role.";
            return;
        }

        var user = existing;
        var details;

        if (user) {
            var changes = [];
            if (user.role !== role) changes.push("role " + user.role + " → " + role);
            if (user.name !== name) changes.push("name");
            if (user.email !== email) changes.push("email");
            if (password) changes.push("password reset");
            user.name = name;
            user.email = email;
            user.role = role;
            if (password) user.password = password;
            details = email + (changes.length ? " (" + changes.join(", ") + ")" : "");
            GTData.log("account", "Edited account", details, "success");
        } else {
            user = {
                id: GTData.nextId("user", "u"), name: name, email: email, role: role,
                password: password, status: "active", failedAttempts: 0, createdAt: new Date().toISOString()
            };
            GTData.users.push(user);
            GTData.log("account", "Created account", email + " (" + role + ")", "success");
        }

        // Subject assignment (teachers only; each subject has one teacher)
        var chosen = Array.prototype.map.call(form.querySelectorAll('input[name="subjects"]:checked'), function (c) { return c.value; });
        GTData.subjects.forEach(function (s) {
            if (role === "teacher" && chosen.indexOf(s.code) !== -1) s.teacherId = user.id;
            else if (s.teacherId === user.id) s.teacherId = null;
        });

        GTData.commit();
        GTUI.closeModal(modal);
        render();
        GTUI.toast(existing ? "Account updated." : "Account created.");
    });

    /* ---------- row actions ---------- */

    tbody.addEventListener("click", function (event) {
        var btn = event.target.closest("button");
        if (!btn) return;

        var id = btn.getAttribute("data-edit") || btn.getAttribute("data-unlock") ||
            btn.getAttribute("data-toggle") || btn.getAttribute("data-delete");
        var user = GTData.userById(id);
        if (!user) return;

        if (btn.hasAttribute("data-edit")) {
            openForm(user);

        } else if (btn.hasAttribute("data-unlock")) {
            user.status = "active";
            user.failedAttempts = 0;
            GTData.log("account", "Unlocked account", user.email, "success");
            GTData.commit();
            render();
            GTUI.toast("Account unlocked.");

        } else if (btn.hasAttribute("data-toggle")) {
            if (user.status !== "disabled" && wouldRemoveLastSuper(user)) {
                GTUI.toast("You cannot disable the last active super admin.", "error");
                return;
            }
            var disabling = user.status !== "disabled";
            user.status = disabling ? "disabled" : "active";
            user.failedAttempts = 0;
            GTData.log("account", disabling ? "Disabled account" : "Enabled account", user.email, "success");
            GTData.commit();
            render();
            GTUI.toast(disabling ? "Account disabled." : "Account enabled.");

        } else if (btn.hasAttribute("data-delete")) {
            if (wouldRemoveLastSuper(user)) {
                GTUI.toast("You cannot delete the last active super admin.", "error");
                return;
            }
            GTUI.confirm({
                icon: "🗑", title: "Delete account?",
                message: user.name + " (" + user.email + ") will be permanently removed. Their subjects become unassigned.",
                confirmText: "Delete"
            }).then(function (ok) {
                if (!ok) return;
                GTData.subjects.forEach(function (s) { if (s.teacherId === user.id) s.teacherId = null; });
                GTData.users.splice(GTData.users.indexOf(user), 1);
                GTData.log("account", "Deleted account", user.email, "success");
                GTData.commit();
                render();
                GTUI.toast("Account deleted.");
            });
        }
    });

    search.addEventListener("input", render);
    roleFilter.addEventListener("change", render);
    render();
});
