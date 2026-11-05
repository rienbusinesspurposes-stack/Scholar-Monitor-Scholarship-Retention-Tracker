/* =====================================================
   DASHBOARD — greeting and footer year
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    var welcome = document.getElementById("welcome-message");

    if (welcome) {
        var hour = new Date().getHours();
        var greeting = "Good evening";

        if (hour < 12) {
            greeting = "Good morning";
        } else if (hour < 18) {
            greeting = "Good afternoon";
        }

        welcome.textContent = greeting + ", iSKOLAR!";
    }

    // Any element with data-year gets the current year
    document.querySelectorAll("[data-year]").forEach(function (element) {
        element.textContent = new Date().getFullYear();
    });

});
