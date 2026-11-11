/* =====================================================
   AUTO-REFRESH — when the other portal changes data,
   reload pages that only display things.
   Opt in with <body data-autorefresh>. It never reloads
   while a dialog is open or someone is typing.
===================================================== */

window.addEventListener("gt:datachanged", function () {
    if (!document.body.hasAttribute("data-autorefresh")) return;
    if (document.querySelector(".modal-overlay.show")) return;
    var a = document.activeElement;
    if (a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return;
    window.location.reload();
});
