<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password &mdash; <?= htmlspecialchars($appName) ?></title>

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

        <h1 class="auth-card-title">Reset your password</h1>
        <p class="auth-card-subtitle">Enter your new password below</p>

        <div id="error-msg" class="alert alert-danger d-none" role="alert"></div>

        <form id="reset-form" action="/auth/reset-password" method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="mb-3">
                <label for="password" class="form-label">New password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    autocomplete="new-password"
                    required
                >
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
                <span id="submit-label">Reset Password</span>
                <span id="submit-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status"></span>
            </button>
        </form>

    </div>

</div>

<script>
(function () {
    const form     = document.getElementById('reset-form');
    const errorBox = document.getElementById('error-msg');
    const btn      = document.getElementById('submit-btn');
    const label    = document.getElementById('submit-label');
    const spinner  = document.getElementById('submit-spinner');

    function setLoading(on) {
        btn.disabled = on;
        spinner.classList.toggle('d-none', !on);
        label.textContent = on ? 'Resetting&hellip;' : 'Reset Password';
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errorBox.classList.add('d-none');

        const password  = document.getElementById('password').value;
        const password2 = document.getElementById('password_confirm').value;

        if (password.length < 8) {
            showError('Password must be at least 8 characters.');
            return;
        }

        if (password !== password2) {
            showError('Passwords do not match.');
            return;
        }

        setLoading(true);

        try {
            const formData = new FormData(form);
            const res = await fetch('/auth/reset-password', {
                method: 'POST',
                body: formData,
            });

            const data = await res.json();

            if (res.ok) {
                window.location.href = '/auth/login?reset=success';
            } else {
                showError(data.error ?? 'Reset failed. Please try again.');
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
