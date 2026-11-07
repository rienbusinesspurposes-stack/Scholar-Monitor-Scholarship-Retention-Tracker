// Navigation: sidebar, profile menu, logout

(function () {
    "use strict";

    var desktopQuery = window.matchMedia("(min-width: 961px)");

    document.addEventListener("DOMContentLoaded", function () {
        var sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        highlightCurrentPage(sidebar);
        setupSidebarToggle(sidebar);
        setupProfileMenu();
        setupLogoutModal();
    });

    // Active link
    function getFileName(path) {
        var clean = (path || "").split(/[?#]/)[0];
        return clean.split("/").pop() || "index.html";
    }

    function highlightCurrentPage(sidebar) {
        var current = getFileName(window.location.pathname);

        sidebar.querySelectorAll(".nav-link").forEach(function (link) {
            var isCurrent = getFileName(link.getAttribute("href")) === current;
            link.classList.toggle("active", isCurrent);
            isCurrent ? link.setAttribute("aria-current", "page") : link.removeAttribute("aria-current");
        });
    }

    // Sidebar toggle: drawer on mobile, collapse on desktop
    function setupSidebarToggle(sidebar) {
        var toggle = document.querySelector(".menu-toggle");
        var backdrop = document.querySelector(".sidebar-backdrop");
        if (!toggle) return;

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
            toggle.setAttribute("aria-expanded", String(open));
            toggle.setAttribute("aria-label", open ? "Hide navigation menu" : "Show navigation menu");
        }

        toggle.addEventListener("click", function () {
            setState(!isOpen());
        });

        if (backdrop) {
            backdrop.addEventListener("click", function () { setState(false); });
        }

        sidebar.querySelectorAll(".nav-link").forEach(function (link) {
            link.addEventListener("click", function () {
                if (isMobile()) setState(false);
            });
        });

        desktopQuery.addEventListener("change", function () {
            setState(!isMobile());
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && isMobile() && isOpen()) setState(false);
        });

        setState(!isMobile());
    }

    // Profile dropdown
    function setupProfileMenu() {
        var menu = document.querySelector(".profile-menu");
        if (!menu) return;

        var trigger = menu.querySelector(".profile-trigger");

        function setOpen(open) {
            menu.classList.toggle("is-open", open);
            trigger.setAttribute("aria-expanded", String(open));
        }

        trigger.addEventListener("click", function (event) {
            event.stopPropagation();
            setOpen(!menu.classList.contains("is-open"));
        });

        document.addEventListener("click", function (event) {
            if (!menu.contains(event.target)) setOpen(false);
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") setOpen(false);
        });
    }

    // Logout modal (any element with data-logout)
    function setupLogoutModal() {
        var modal = document.getElementById("logout-modal");
        if (!modal) return;

        var closeButton = document.getElementById("close-logout-modal");
        var cancelButton = document.getElementById("cancel-logout");
        var confirmButton = document.getElementById("confirm-logout");
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

        document.querySelectorAll("[data-logout]").forEach(function (link) {
            link.addEventListener("click", function (event) {
                event.preventDefault();
                openModal();
            });
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
                window.location.replace("login.html");
            });
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && modal.classList.contains("show")) closeModal();
        });
    }
})();
