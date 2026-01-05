// ==============================
// Toggle between login & register
// ==============================
const container = document.querySelector(".container");
const registerBtn = document.querySelector(".register-btn");
const loginBtn = document.querySelector(".login-btn");

registerBtn.addEventListener("click", () => {
    container.classList.add("active");
});

loginBtn.addEventListener("click", () => {
    container.classList.remove("active");
});

// ========================================
// Auto-show register if redirected w/ errors
// ========================================
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get("form") === "register") {
    container.classList.add("active");
}

// =====================
// Submit register form
// =====================
function submitRegister() {
    document.getElementById("registerForm").submit();
}

// =================================
// Open register confirmation modal
// =================================
function openRegisterModal() {
    const form = document.getElementById("registerForm");

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const passwordInput = form.querySelector('input[name="password"]');
    const confirmInput  = form.querySelector('input[name="confirm_password"]');
    const errorText     = document.getElementById("passwordError");

    if (passwordInput.value !== confirmInput.value) {
        errorText.style.display = "block";
        confirmInput.focus();
        return;
    }

    errorText.style.display = "none";

    const modal = new bootstrap.Modal(
        document.getElementById("registerConfirmModal")
    );
    modal.show();
}

// =================================
// Hide password error while typing
// =================================
document
    .querySelector('input[name="confirm_password"]')
    ?.addEventListener("input", () => {
        document.getElementById("passwordError").style.display = "none";
    });

// =================================
// Show / hide PASSWORD ONLY (eye icon)
// =================================
const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("registerPassword");

if (togglePassword && passwordInput) {
    togglePassword.addEventListener("click", () => {
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            togglePassword.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            passwordInput.type = "password";
            togglePassword.classList.replace("fa-eye-slash", "fa-eye");
        }
    });
}
