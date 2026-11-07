/* =====================================================
   FEATURE — document upload preview
   Load only on pages that have an upload form.
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var input = document.getElementById("document-upload");
    var label = document.getElementById("file-name");

    if (!input || !label) return;

    input.addEventListener("change", function () {
        label.textContent = this.files.length > 0
            ? "Selected file: " + this.files[0].name
            : "No file selected";
    });

});
