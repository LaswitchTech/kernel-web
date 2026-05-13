<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Check your email &mdash; <?= htmlspecialchars($appName) ?></title>

    <!-- Bootstrap 5.3.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
    <!-- App theme -->
    <link rel="stylesheet" href="/css">
</head>
<body>

<div class="d-flex align-items-center justify-content-center min-vh-100 w-100 p-3">

    <div class="auth-card" style="max-width: 420px; text-align: center;">

        <div class="auth-card-logo">
            <span class="auth-card-icon" style="color: #0d6efd;">
                <i class="bi bi-envelope-check"></i>
            </span>
        </div>

        <h1 class="auth-card-title">Check your email</h1>
        <p class="auth-card-subtitle">We've sent a verification link to your inbox. Click the link to activate your account.</p>

        <a href="/auth/login" class="btn btn-outline-primary mt-3">Back to sign in</a>

        <p class="mt-3">
            <a href="#" id="resend-link" class="small">Didn't receive it? Resend</a>
        </p>

    </div>

</div>

<script>
document.getElementById('resend-link').addEventListener('click', async function (e) {
    e.preventDefault();
    const res = await fetch('/auth/verify/resend', { method: 'POST' });
    if (res.ok) {
        this.textContent = 'Verification email sent!';
        this.classList.remove('small');
        this.classList.add('text-success');
        this.disabled = true;
    }
});
</script>

</body>
</html>
