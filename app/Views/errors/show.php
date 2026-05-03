<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $status ?> — <?= htmlspecialchars($heading) ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <style>
        .error-card { max-width: 520px; margin: 6rem auto; }
        .error-code { font-size: 5rem; font-weight: 800; line-height: 1; }
        .error-icon { font-size: 2.5rem; }
        pre { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); }
    </style>
</head>
<body>
<div class="container">
    <div class="card error-card shadow-sm">
        <div class="card-body p-5 text-center">
            <div class="mb-3">
                <i class="bi <?= $icon ?> error-icon text-muted"></i>
            </div>
            <h1 class="display-4 fw-bold mb-1"><?= $status ?></h1>
            <p class="text-muted mb-4"><?= htmlspecialchars($heading) ?></p>

            <?php if ($message): ?>
                <p class="mb-4"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>

            <?php if ($debug && $trace): ?>
                <div class="text-start mb-4">
                    <?php if ($location): ?>
                        <p><strong>Location:</strong> <code><?= $location ?></code></p>
                    <?php endif; ?>
                    <pre class="p-3 small mb-0"><?= $trace ?></pre>
                </div>
            <?php endif; ?>

            <?php if ($action): ?>
                <a href="<?= $action['href'] ?>" class="btn btn-primary"><?= $action['label'] ?></a>
            <?php elseif ($suggestion): ?>
                <p class="text-muted small"><?= $suggestion ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
