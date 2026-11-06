/* =====================================================
   LOGIN — footer year and show/hide password.
   The sign-in itself is handled by login.php, so the form submits normally.
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    const loginYear = document.getElementById("login-year");
    const password = document.getElementById("password");
    const togglePassword = document.getElementById("toggle-password");

    if (loginYear) {
        loginYear.textContent = new Date().getFullYear();
    }

    // Pages without a password field (the code page) skip this
    if (password && togglePassword) {
        togglePassword.addEventListener("click", function () {
            const hidden = password.type === "password";
            password.type = hidden ? "text" : "password";
            togglePassword.textContent = hidden ? "Hide" : "Show";
        });
    }

});