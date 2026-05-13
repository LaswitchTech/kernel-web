<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account &mdash; <?= htmlspecialchars($appName) ?></title>

    <!-- Bootstrap 5.3.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
    <!-- App theme -->
    <link rel="stylesheet" href="/css">
</head>
<body>

<div class="d-flex align-items-center justify-content-center min-vh-100 w-100 p-3">

    <div class="auth-card">

        <div class="auth-card-logo">
            <span class="auth-card-icon">
                <i class="bi bi-activity"></i>
            </span>
        </div>

        <h1 class="auth-card-title"><?= htmlspecialchars($appName) ?></h1>
        <p class="auth-card-subtitle">Create your account</p>

        <div id="error-msg" class="alert alert-danger d-none" role="alert"></div>
        <div id="field-errors" class="d-none"></div>

        <form id="register-form" action="/auth/register" method="POST">
            <div class="mb-3">
                <label for="display_name" class="form-label">Full name</label>
                <input
                    type="text"
                    id="display_name"
                    name="display_name"
                    class="form-control"
                    autocomplete="name"
                    autofocus
                    required
                >
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    autocomplete="username"
                    required
                >
                <div class="form-text">3–64 characters, letters, numbers, and hyphens only.</div>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    autocomplete="email"
                    required
                >
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    autocomplete="new-password"
                    required
                >
                <div class="form-text">At least 8 characters.</div>
            </div>
            <div class="mb-4">
                <label for="password_confirm" class="form-label">Confirm password</label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    class="form-control"
                    autocomplete="new-password"
                    required
                >
            </div>
            <button type="submit" class="btn btn-primary w-100" id="submit-btn">
                <span id="submit-label">Create Account</span>
                <span id="submit-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status"></span>
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="/auth/login">Already have an account? Sign in</a>
        </div>

    </div>

</div>

<script>
(function () {
    const form     = document.getElementById('register-form');
    const errorBox = document.getElementById('error-msg');
    const fieldBox = document.getElementById('field-errors');
    const btn      = document.getElementById('submit-btn');
    const label    = document.getElementById('submit-label');
    const spinner  = document.getElementById('submit-spinner');

    function setLoading(on) {
        btn.disabled = on;
        spinner.classList.toggle('d-none', !on);
        label.textContent = on ? 'Creating account&hellip;' : 'Create Account';
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
        fieldBox.innerHTML = '';
        fieldBox.classList.add('d-none');
    }

    function hideError() {
        errorBox.classList.add('d-none');
    }

    function showFieldErrors(errors) {
        errorBox.classList.add('d-none');
        let html = '<div class="d-flex flex-column gap-2">';
        for (const [field, msg] of Object.entries(errors)) {
            html += '<div class="alert alert-danger py-1 px-3 mb-0 small">' + msg + '</div>';
        }
        html += '</div>';
        fieldBox.innerHTML = html;
        fieldBox.classList.remove('d-none');
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        hideError();
        fieldBox.innerHTML = '';
        fieldBox.classList.add('d-none');

        setLoading(true);

        try {
            const formData = new FormData(form);
            const res = await fetch('/auth/register', {
                method: 'POST',
                body: formData,
            });

            const data = await res.json();

            if (res.ok && data.success) {
                if (data.requires_verification) {
                    window.location.href = '/auth/register/sent';
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } else if (data.errors) {
                showFieldErrors(data.errors);
            } else {
                showError(data.error ?? 'Registration failed. Please try again.');
            }
        } catch (err) {
            showError('Network error. Please try again.');
        } finally {
            setLoading(false);
        }
    });
})();
</script>
</body>
</html>
