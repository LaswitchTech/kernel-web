<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link Expired &mdash; <?= htmlspecialchars($appName) ?></title>

    <!-- Bootstrap 5.3.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
    <!-- App theme — dynamically compiled LESS -->
    <link rel="stylesheet" href="/css">
</head>
<body>

<div class="d-flex align-items-center justify-content-center min-vh-100 w-100 p-3">

    <div class="auth-card" style="max-width: 420px; text-align: center;">

        <div class="auth-card-logo">
            <span class="auth-card-icon" style="color: #ffc107;">
                <i class="bi bi-clock-history"></i>
            </span>
        </div>

        <h1 class="auth-card-title">Link expired</h1>
        <p class="auth-card-subtitle">This verification link has expired. Request a new one below.</p>

        <form id="resend-form" class="mt-3">
            <button type="submit" class="btn btn-primary">Resend verification email</button>
        </form>

        <p class="mt-2">
            <a href="/auth/login" class="small">Back to sign in</a>
        </p>

    </div>

</div>

<script>
document.getElementById('resend-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    try {
        const res = await fetch('/auth/verify/resend', { method: 'POST' });
        if (res.ok) window.location.href = '/auth/verify/sent';
    } catch {
        window.location.href = '/auth/verify/sent';
    }
});
</script>

</body>
</html>
