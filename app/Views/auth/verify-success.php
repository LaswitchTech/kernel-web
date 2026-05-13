<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verified &mdash; <?= htmlspecialchars($appName) ?></title>

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
            <span class="auth-card-icon" style="color: #198754;">
                <i class="bi bi-patch-check-fill"></i>
            </span>
        </div>

        <h1 class="auth-card-title">Email verified!</h1>
        <p class="auth-card-subtitle">Your email address has been successfully verified. You can now sign in to your account.</p>

        <p class="mt-3">
            <a href="/auth/login" class="btn btn-primary">Sign in</a>
        </p>

    </div>

</div>

</body>
</html>
