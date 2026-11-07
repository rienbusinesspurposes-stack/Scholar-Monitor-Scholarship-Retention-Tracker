// Dashboard content

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

        // The page puts the student's first name in data-name; without it the greeting stays plain
        var name = welcome.getAttribute("data-name");
        welcome.textContent = greeting + (name ? ", " + name : "") + "!";
    }

    document.querySelectorAll("[data-year]").forEach(function (element) {
        element.textContent = new Date().getFullYear();
    });

});