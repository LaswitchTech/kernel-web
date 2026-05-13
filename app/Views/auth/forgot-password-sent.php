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

    <div class="auth-card">

        <div class="auth-card-logo">
            <span class="auth-card-icon">
                <i class="bi bi-activity"></i>
            </span>
        </div>

        <h1 class="auth-card-title">Check your email</h1>
        <p class="auth-card-subtitle">
            If an account with that email exists, we have sent a password reset link.
            Check your inbox and follow the instructions.
        </p>

        <div class="text-center mt-4">
            <a href="/auth/login" class="btn btn-outline-secondary">Back to sign in</a>
        </div>

    </div>

</div>

</body>
</html>
