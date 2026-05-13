<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invalid Link &mdash; <?= htmlspecialchars($appName) ?></title>

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
            <span class="auth-card-icon" style="color: #dc3545;">
                <i class="bi bi-exclamation-triangle"></i>
            </span>
        </div>

        <h1 class="auth-card-title">Invalid link</h1>
        <p class="auth-card-subtitle">This verification link is invalid. Please request a new one.</p>

        <form id="resend-form" class="mt-3">
            <button type="submit" class="btn btn-primary">Request new link</button>
        </form>

        <p class="mt-2">
            <a href="/auth/login" class="small">Back to sign in</a>
        </p>

    </div>

</div>

<script>
document.getElementById('resend-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = this.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Sending...';
    try {
        const res = await fetch('/auth/verify/resend', { method: 'POST' });
        if (res.ok) {
            this.remove();
            const msg = document.createElement('p');
            msg.className = 'mt-3 text-success';
            msg.textContent = 'A new verification link has been sent. Check your inbox.';
            this.parentNode.insertBefore(msg, this.nextSibling);
        }
    } catch {
        btn.disabled = false;
        btn.textContent = 'Request new link';
    }
});
</script>

</body>
</html>
