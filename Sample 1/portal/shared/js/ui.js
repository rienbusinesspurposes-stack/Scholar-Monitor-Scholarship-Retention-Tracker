/* =====================================================
   UI — small shared helpers: toasts, confirm dialog,
   modal open/close, escaping, date formatting.
===================================================== */

(function () {
    "use strict";

    var stack = null;

    function esc(value) {
        return String(value === null || value === undefined ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function toast(message, type) {
        if (!stack) {
            stack = document.createElement("div");
            stack.className = "toast-stack";
            stack.setAttribute("role", "status");
            stack.setAttribute("aria-live", "polite");
            document.body.appendChild(stack);
        }
        var el = document.createElement("div");
        el.className = "toast " + (type || "success");
        el.textContent = message;
        stack.appendChild(el);
        setTimeout(function () {
            el.classList.add("leaving");
            setTimeout(function () { el.remove(); }, 250);
        }, 3200);
    }

    /* ---------- modals ---------- */

    var lastFocused = null;

    function openModal(modal) {
        if (!modal) return;
        lastFocused = document.activeElement;
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        var first = modal.querySelector("input:not([type=hidden]):not([disabled]), select, textarea, .cancel-button");
        if (first) first.focus();
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        if (lastFocused && lastFocused.focus) lastFocused.focus();
    }

    // Any overlay: click outside, [data-close], or Escape closes it
    document.addEventListener("click", function (event) {
        var overlay = event.target.closest && event.target.closest(".modal-overlay");
        if (event.target.classList && event.target.classList.contains("modal-overlay")) {
            closeModal(event.target);
        } else if (overlay && event.target.closest("[data-close]")) {
            closeModal(overlay);
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key !== "Escape") return;
        var open = document.querySelector(".modal-overlay.show");
        if (open) closeModal(open);
    });

    /* ---------- confirm dialog ---------- */

    function confirmDialog(options) {
        return new Promise(function (resolve) {
            var overlay = document.createElement("div");
            overlay.className = "modal-overlay";
            overlay.setAttribute("role", "alertdialog");
            overlay.setAttribute("aria-modal", "true");
            overlay.innerHTML =
                '<div class="modal-box">' +
                '<div class="logout-modal-icon" aria-hidden="true">' + esc(options.icon || "!") + "</div>" +
                "<h2>" + esc(options.title || "Are you sure?") + "</h2>" +
                "<p>" + esc(options.message || "") + "</p>" +
                '<div class="logout-modal-actions">' +
                '<button type="button" class="cancel-button">Cancel</button>' +
                '<button type="button" class="confirm-logout-button">' + esc(options.confirmText || "Confirm") + "</button>" +
                "</div></div>";
            document.body.appendChild(overlay);

            var previous = document.activeElement;

            function done(result) {
                overlay.remove();
                if (previous && previous.focus) previous.focus();
                document.removeEventListener("keydown", onKey);
                resolve(result);
            }
            function onKey(e) { if (e.key === "Escape") done(false); }

            overlay.querySelector(".cancel-button").addEventListener("click", function () { done(false); });
            overlay.querySelector(".confirm-logout-button").addEventListener("click", function () { done(true); });
            overlay.addEventListener("click", function (e) { if (e.target === overlay) done(false); });
            document.addEventListener("keydown", onKey);

            overlay.classList.add("show");
            overlay.querySelector(".cancel-button").focus();
        });
    }

    /* ---------- formatting ---------- */

    function fmtDateTime(iso) {
        if (!iso) return "—";
        var d = new Date(iso);
        return d.toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) +
            ", " + d.toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" });
    }

    function fmtDate(iso) {
        if (!iso) return "—";
        return new Date(iso).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
    }

    function timeAgo(iso) {
        var mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
        if (mins < 1) return "just now";
        if (mins < 60) return mins + " min ago";
        var hrs = Math.round(mins / 60);
        if (hrs < 24) return hrs + " hr ago";
        return Math.round(hrs / 24) + " d ago";
    }

    function roleLabel(role) {
        return role === "superadmin" ? "Super Admin" : role === "teacher" ? "Teacher" : role;
    }

    window.GTUI = {
        esc: esc,
        toast: toast,
        openModal: openModal,
        closeModal: closeModal,
        confirm: confirmDialog,
        fmtDateTime: fmtDateTime,
        fmtDate: fmtDate,
        timeAgo: timeAgo,
        roleLabel: roleLabel
    };
})();
