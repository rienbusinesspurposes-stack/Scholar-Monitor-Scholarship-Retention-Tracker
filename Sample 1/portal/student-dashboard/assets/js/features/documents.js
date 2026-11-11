/* =====================================================
   FEATURE — My Documents
   Left: pick a requirement and upload a file.
   Right: status of every requirement.
   The admin verifies each file (admin portal). The file is
   kept with GTFiles (shared), so the admin can open it.
===================================================== */

document.addEventListener("shell:ready", function (event) {
    "use strict";

    var student = event.detail;
    var esc = GTUI.esc;

    var MAX_MB = 5;
    var ALLOWED = ["pdf", "jpg", "jpeg", "png"];

    var PILL = {
        verified: ["success", "Verified"],
        pending: ["warning", "Pending"],
        rejected: ["danger", "Rejected"],
        missing: ["danger", "Missing"],
        overdue: ["danger", "Overdue"]
    };

    var select = document.getElementById("upload-req");
    var hint = document.getElementById("upload-hint");
    var form = document.getElementById("upload-form");
    var fileInput = document.getElementById("document-upload");
    var fileLabel = document.getElementById("file-name");
    var errorBox = document.getElementById("upload-error");
    var submit = document.getElementById("upload-submit");
    var statusBox = document.getElementById("doc-status");

    function render(keepPick) {
        var all = GTStudent.requirements(student.id);
        var open = all.filter(function (i) { return i.state !== "verified"; });
        var pick = keepPick || select.value;

        /* ----- upload form ----- */
        select.innerHTML = open.map(function (i) {
            return '<option value="' + esc(i.req.id) + '">' + esc(i.req.name) + " (" + PILL[i.state][1] + ")</option>";
        }).join("");
        if (open.some(function (i) { return i.req.id === pick; })) select.value = pick;

        var none = open.length === 0;
        select.disabled = none;
        submit.disabled = none;
        fileInput.disabled = none;
        updateHint();

        /* ----- status list ----- */
        statusBox.innerHTML = all.map(function (item) {
            var r = item.req, sub = item.sub, p = PILL[item.state];
            var meta = "Due " + GTUI.fmtDate(r.dueDate) + (r.required ? "" : " · optional");
            if (sub.fileName) meta += " · " + esc(sub.fileName);

            var extra = "";
            if (item.state === "rejected" && sub.note) extra = '<div class="doc-note">Reason: ' + esc(sub.note) + "</div>";
            var pickBtn = item.state !== "verified" && item.state !== "pending"
                ? ' <button type="button" class="link-button" data-pick="' + esc(r.id) + '">Upload</button>' : "";

            return '<div class="kv-row status-row"><div><span class="status-name">' + esc(r.name) + "</span>" +
                '<small class="status-meta">' + meta + pickBtn + "</small>" + extra + "</div>" +
                '<span class="pill ' + p[0] + '">' + p[1] + "</span></div>";
        }).join("");
    }

    function updateHint() {
        var item = GTStudent.requirements(student.id).filter(function (i) { return i.req.id === select.value; })[0];
        if (!item) { hint.textContent = "All your documents are verified. Nothing to upload."; return; }
        hint.textContent = item.state === "rejected" && item.sub.note
            ? "Rejected: " + item.sub.note
            : item.state === "pending" ? "Already submitted. Uploading again replaces the file." : item.req.description;
    }

    function resetFile() {
        fileInput.value = "";
        fileLabel.textContent = "No file selected";
        errorBox.textContent = "";
    }

    select.addEventListener("change", function () { updateHint(); errorBox.textContent = ""; });

    fileInput.addEventListener("change", function () {
        errorBox.textContent = "";
        var f = this.files[0];
        fileLabel.textContent = f ? f.name + " (" + (f.size / 1024 / 1024).toFixed(2) + " MB)" : "No file selected";
    });

    statusBox.addEventListener("click", function (e) {
        var btn = e.target.closest("[data-pick]");
        if (!btn) return;
        select.value = btn.getAttribute("data-pick");
        updateHint();
        select.scrollIntoView({ behavior: "smooth", block: "center" });
        document.getElementById("choose-label").focus();
    });

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        var file = fileInput.files[0];
        var ext = file ? file.name.split(".").pop().toLowerCase() : "";

        if (!file) { errorBox.textContent = "Please choose a file first."; return; }
        if (ALLOWED.indexOf(ext) === -1) { errorBox.textContent = "Only PDF, JPG or PNG files are allowed."; return; }
        if (file.size > MAX_MB * 1024 * 1024) { errorBox.textContent = "File is too large. Maximum size is " + MAX_MB + " MB."; return; }

        GTStudent.uploadDocument(student, select.value, file).then(function (result) {
            if (result === true) {
                GTUI.toast("Uploaded. Your document is now waiting for verification.");
                resetFile();
                render();
                GTShell.refresh();
            } else {
                errorBox.textContent = result;
            }
        });
    });

    render();
});
