/* =====================================================
   FEATURE — My Profile
   - Records from the scholarship office are read-only.
   - Contact details, photo and password can be changed.
   Photo is resized and kept in localStorage (demo only).
===================================================== */

document.addEventListener("shell:ready", function (event) {
    "use strict";

    var student = event.detail;
    var profile = GTStudent.getProfile(student.id);

    /* ----- read-only record ----- */
    document.getElementById("pf-name").textContent = student.name;
    document.getElementById("pf-no").textContent = student.studentNo;
    document.getElementById("pf-email").textContent = student.email;
    document.getElementById("pf-course").textContent = student.course;
    document.getElementById("pf-year").textContent = GTStudent.yearLabel(student.year);
    document.getElementById("pf-scholarship").textContent = student.scholarship;
    document.getElementById("pf-card-name").textContent = student.name;
    document.getElementById("pf-card-no").textContent = student.studentNo;

    /* ----- contact form ----- */
    var contact = document.getElementById("contact-form");
    ["phone", "address", "guardian", "guardianPhone"].forEach(function (f) {
        contact.elements[f].value = profile[f] || "";
    });

    contact.addEventListener("submit", function (e) {
        e.preventDefault();
        var error = document.getElementById("contact-error");
        var phone = contact.elements.phone.value.trim();
        var gphone = contact.elements.guardianPhone.value.trim();
        var phoneOk = /^(\+?63|0)?9\d{9}$/;

        error.textContent = "";
        if (phone && !phoneOk.test(phone.replace(/[\s-]/g, ""))) {
            error.textContent = "Enter a valid mobile number, for example 0917 123 4567.";
            return;
        }
        if (gphone && !phoneOk.test(gphone.replace(/[\s-]/g, ""))) {
            error.textContent = "Enter a valid guardian mobile number.";
            return;
        }

        GTStudent.saveProfile(student, {
            phone: phone,
            address: contact.elements.address.value.trim(),
            guardian: contact.elements.guardian.value.trim(),
            guardianPhone: gphone
        });
        GTUI.toast("Contact details saved.");
    });

    /* ----- photo ----- */
    var input = document.getElementById("profile-upload");
    var preview = document.getElementById("profile-preview");

    function showPhoto(url) {
        preview.style.backgroundImage = url ? "url(" + url + ")" : "";
        preview.textContent = url ? "" : GTStudent.initials(student.name);
    }
    showPhoto(profile.photo);

    function resize(file, done) {
        var img = new Image();
        var url = URL.createObjectURL(file);
        img.onload = function () {
            var size = 192, canvas = document.createElement("canvas");
            canvas.width = canvas.height = size;
            var side = Math.min(img.width, img.height);
            canvas.getContext("2d").drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, size, size);
            URL.revokeObjectURL(url);
            done(canvas.toDataURL("image/jpeg", 0.85));
        };
        img.onerror = function () { URL.revokeObjectURL(url); done(null); };
        img.src = url;
    }

    input.addEventListener("change", function () {
        var file = this.files[0];
        if (!file) return;
        if (!file.type.startsWith("image/")) { GTUI.toast("Please choose an image file.", "error"); return; }
        if (file.size > 5 * 1024 * 1024) { GTUI.toast("Image must be 5 MB or smaller.", "error"); return; }

        resize(file, function (data) {
            if (!data) { GTUI.toast("Could not read that image.", "error"); return; }
            GTStudent.saveProfile(student, { photo: data });
            showPhoto(data);
            GTShell.refresh();
            GTUI.toast("Profile photo updated.");
        });
    });

    document.getElementById("photo-remove").addEventListener("click", function () {
        GTStudent.saveProfile(student, { photo: "" });
        showPhoto("");
        GTShell.refresh();
        GTUI.toast("Profile photo removed.", "info");
    });

    /* ----- password ----- */
    var pw = document.getElementById("password-form");
    var pwError = document.getElementById("password-error");
    document.getElementById("pw-min").textContent = GTData.settings.minPasswordLength;

    pw.addEventListener("submit", function (e) {
        e.preventDefault();
        pwError.textContent = "";

        if (pw.elements.next.value !== pw.elements.confirm.value) {
            pwError.textContent = "New passwords do not match.";
            return;
        }
        var err = GTStudent.changePassword(student, pw.elements.current.value, pw.elements.next.value);
        if (err) { pwError.textContent = err; return; }

        pw.reset();
        GTUI.toast("Password changed.");
    });
});
