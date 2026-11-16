
// Confirm before deleting a record
document.addEventListener("click", function (event) {
    const deleteButton = event.target.closest("[data-confirm]");

    if (deleteButton) {
        const message = deleteButton.getAttribute("data-confirm");

        if (!confirm(message || "Are you sure you want to continue?")) {
            event.preventDefault();
        }
    }
});

// Automatically hide success and error messages
document.addEventListener("DOMContentLoaded", function () {
    const messages = document.querySelectorAll(
        ".alert.success, .alert.error, .message.success, .message.error"
    );

    messages.forEach(function (message) {
        setTimeout(function () {
            message.style.transition = "opacity 0.5s";
            message.style.opacity = "0";

            setTimeout(function () {
                message.style.display = "none";
            }, 500);
        }, 4000);
    });
});

// Prevent accidental double submission of forms
document.addEventListener("submit", function (event) {
    const form = event.target;

    if (form.dataset.preventDoubleSubmit === "true") {
        if (form.dataset.submitted === "true") {
            event.preventDefault();
            return;
        }

        form.dataset.submitted = "true";
    }
});
