
document.addEventListener("DOMContentLoaded", function () {

    const loginForm = document.getElementById("login-form");
    const studentID = document.getElementById("student-id");
    const password = document.getElementById("password");
    const togglePassword = document.getElementById("toggle-password");
    const errorMessage = document.getElementById("login-error");
    const rememberMe = document.getElementById("remember-me");
    const loginYear = document.getElementById("login-year");

    // Current year
    if (loginYear) {
        loginYear.textContent = new Date().getFullYear();
    }

    // Show or hide password
    togglePassword.addEventListener("click", function () {

        if (password.type === "password") {
            password.type = "text";
            togglePassword.textContent = "Hide";
        } else {
            password.type = "password";
            togglePassword.textContent = "Show";
        }

    });

    //Forgot Password Modal
    const forgotLink = document.getElementById("forgot-password");
    const forgotModal = document.getElementById("forgot-modal");
    const closeModal = document.getElementById("close-modal");
    const modalOk = document.getElementById("modal-ok");

    function openForgotModal() {
        forgotModal.classList.add("show");
    }

    function closeForgotModal() {
        forgotModal.classList.remove("show");
    }

    // Open modal
    forgotLink.addEventListener("click", function (event) {
        event.preventDefault();
        openForgotModal();
    });

    // Close using X
    closeModal.addEventListener("click", closeForgotModal);

    // Close using Got it button
    modalOk.addEventListener("click", closeForgotModal);

    // Close by clicking outside the modal
    forgotModal.addEventListener("click", function (event) {
        if (event.target === forgotModal) {
            closeForgotModal();
        }
    });

    // Close using Escape key
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeForgotModal();
        }
    });


    // Login form
    loginForm.addEventListener("submit", function (event) {

        event.preventDefault();

        const enteredID = studentID.value.trim();
        const enteredPassword = password.value;

        errorMessage.textContent = "";

        // Check empty fields
        if (!enteredID || !enteredPassword) {
            errorMessage.textContent =
                "Please enter your Email and password.";
            return;
        }

        // FRONTEND DEMO ONLY:
        // This does not verify a real account.
        // Your team will replace this with PHP authentication.

        localStorage.setItem("grantrackLoggedIn", "true");
        localStorage.setItem("grantrackStudentID", enteredID);

        // Remember me is only a demo preference.
        if (rememberMe.checked) {
            localStorage.setItem("grantrackRemember", "true");
        } else {
            localStorage.removeItem("grantrackRemember");
        }

        // Go to student dashboard
        window.location.href = "index.html";

    });

});
