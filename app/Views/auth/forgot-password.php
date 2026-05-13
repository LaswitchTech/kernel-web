<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password &mdash; <?= htmlspecialchars($appName) ?></title>

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
        <p class="auth-card-subtitle">Enter your email to reset your password</p>

        <div id="error-msg" class="alert alert-danger d-none" role="alert"></div>

        <form id="forgot-form" action="/auth/forgot-password" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    autofocus
                    required
                >
            </div>
            <button type="submit" class="btn btn-primary w-100" id="submit-btn">
                <span id="submit-label">Send Reset Link</span>
                <span id="submit-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status"></span>
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="/auth/login">Back to sign in</a>
        </div>

    </div>

</div>

<script>
(function () {
    const form     = document.getElementById('forgot-form');
    const errorBox = document.getElementById('error-msg');
    const btn      = document.getElementById('submit-btn');
    const label    = document.getElementById('submit-label');
    const spinner  = document.getElementById('submit-spinner');

    function setLoading(on) {
        btn.disabled = on;
        spinner.classList.toggle('d-none', !on);
        label.textContent = on ? 'Sending&hellip;' : 'Send Reset Link';
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errorBox.classList.add('d-none');
        setLoading(true);

        try {
            const res = await fetch('/auth/forgot-password', {
                method: 'POST',
                body: new FormData(form),
            });

            const data = await res.json();

            if (res.ok) {
                window.location.href = '/auth/forgot-password/sent';
            } else {
                showError(data.error ?? 'Request failed. Please try again.');
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
