/* =====================================================
   FEATURE — profile photo preview
   Load only on the profile page.
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var input = document.getElementById("profile-upload");
    var preview = document.getElementById("profile-preview");

    if (!input || !preview) return;

    input.addEventListener("change", function () {
        var file = this.files[0];

        if (file && file.type.startsWith("image/")) {
            preview.src = URL.createObjectURL(file);
        }
    });

});
