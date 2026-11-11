/* =====================================================
   NAV — sidebar behavior
   1. Highlights the current page automatically
   2. Sidebar toggle (drawer on phones, collapse on desktop)
   3. Logout confirmation modal
===================================================== */

(function () {
    "use strict";

    var desktopQuery = window.matchMedia("(min-width: 961px)");

    document.addEventListener("DOMContentLoaded", function () {
        var sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        var menuToggle = document.querySelector(".menu-toggle");
        var backdrop = document.querySelector(".sidebar-backdrop");
        var logoutModal = document.getElementById("logout-modal");

        highlightCurrentPage(sidebar);
        setupDrawer(sidebar, menuToggle, backdrop);
        setupLogoutModal(logoutModal);
    });

    /* ---------- 1. Current page ---------- */

    function getFileName(path) {
        var clean = (path || "").split(/[?#]/)[0];
        return clean.split("/").pop() || "index.html";
    }

    function highlightCurrentPage(sidebar) {
        var currentPage = getFileName(window.location.pathname);

        sidebar.querySelectorAll(".nav-link").forEach(function (link) {
            var isCurrent = getFileName(link.getAttribute("href")) === currentPage;

            link.classList.toggle("active", isCurrent);

            if (isCurrent) {
                link.setAttribute("aria-current", "page");
            } else {
                link.removeAttribute("aria-current");
            }
        });
    }

    /* ---------- 2. Sidebar toggle: drawer on mobile, collapse on desktop ---------- */

    function setupDrawer(sidebar, menuToggle, backdrop) {
        if (!menuToggle) return;

        function isMobile() {
            return !desktopQuery.matches;
        }

        function isOpen() {
            return isMobile()
                ? sidebar.classList.contains("is-open")
                : !document.body.classList.contains("sidebar-collapsed");
        }

        function setState(open) {
            if (isMobile()) {
                document.body.classList.remove("sidebar-collapsed");
                sidebar.classList.toggle("is-open", open);
                if (backdrop) backdrop.classList.toggle("is-visible", open);
                document.body.classList.toggle("nav-open", open);
            } else {
                sidebar.classList.remove("is-open");
                if (backdrop) backdrop.classList.remove("is-visible");
                document.body.classList.remove("nav-open");
                document.body.classList.toggle("sidebar-collapsed", !open);
            }
            menuToggle.setAttribute("aria-expanded", String(open));
            menuToggle.setAttribute("aria-label", open ? "Hide navigation menu" : "Show navigation menu");
        }

        menuToggle.addEventListener("click", function () {
            setState(!isOpen());
        });

        if (backdrop) {
            backdrop.addEventListener("click", function () { setState(false); });
        }

        // Close the drawer after choosing a page on small screens
        sidebar.querySelectorAll(".nav-link").forEach(function (link) {
            link.addEventListener("click", function () {
                if (isMobile()) setState(false);
            });
        });

        // Reset when the window crosses the desktop breakpoint
        desktopQuery.addEventListener("change", function () {
            setState(!isMobile());
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && isMobile() && isOpen()) setState(false);
        });

        setState(!isMobile());
    }

    /* ---------- 3. Logout modal ---------- */

    function setupLogoutModal(modal) {
        var logoutButton = document.getElementById("logout-button");
        var closeButton = document.getElementById("close-logout-modal");
        var cancelButton = document.getElementById("cancel-logout");
        var confirmButton = document.getElementById("confirm-logout");

        if (!logoutButton || !modal) return;

        var lastFocused = null;

        function openModal() {
            lastFocused = document.activeElement;
            modal.classList.add("show");
            modal.setAttribute("aria-hidden", "false");
            if (cancelButton) cancelButton.focus();
        }

        function closeModal() {
            modal.classList.remove("show");
            modal.setAttribute("aria-hidden", "true");
            if (lastFocused && lastFocused.focus) lastFocused.focus();
        }

        logoutButton.addEventListener("click", function (event) {
            event.preventDefault();
            openModal();
        });

        [closeButton, cancelButton].forEach(function (button) {
            if (button) button.addEventListener("click", closeModal);
        });

        modal.addEventListener("click", function (event) {
            if (event.target === modal) closeModal();
        });

        if (confirmButton) {
            confirmButton.addEventListener("click", function () {
                window.GranTrackSession.end();
                window.location.replace("../login.html");
            });
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && modal.classList.contains("show")) {
                closeModal();
            }
        });
    }
})();
