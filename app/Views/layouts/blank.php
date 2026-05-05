<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '') ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
    <!-- App theme — dynamically compiled LESS -->
    <link rel="stylesheet" href="/css">

    <!-- Custom auth page centering -->
    <style>
        body {
            display: flex;
            align-items: center;
            min-height: 100vh;
            background: var(--bs-body-bg);
        }
        .landing-card {
            max-width: 640px;
            margin: 2rem auto;
        }
    </style>

    <?php echo \App\Core\HookRegistry::render('layout.head'); ?>
</head>
<body>
<?= $content ?>

<?php echo \App\Core\HookRegistry::render('layout.body.end'); ?>
</body>
</html>
