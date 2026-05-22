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

        /* Landing page cards: transparent bg so dark mode text stays readable */
        .landing-card .card-body {
            background: transparent;
        }
        .landing-card .card {
            background: transparent;
        }
        .landing-card a.card {
            background: var(--bs-tertiary-bg);
            border-color: var(--bs-border-color);
            color: var(--bs-body-color);
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .landing-card a.card:hover {
            background: var(--bs-secondary-bg);
            border-color: var(--bs-border-color-state, var(--bs-border-color));
        }
        .landing-card a.card .text-muted {
            color: var(--bs-secondary-color) !important;
        }
    </style>

    <?php echo \App\Core\HookRegistry::render('layout.head'); ?>
</head>
<body>
<?php use App\Core\ViewGlobals; $__devScope = get_defined_vars(); $__globals = ViewGlobals::contextFromScope($__devScope); extract($__globals); $__devScope = null; unset($__devScope); ?>
<?= $content ?>

<?php echo \App\Core\HookRegistry::render('layout.body.end'); ?>

<!-- Developer Tools -->
<?php $__devVars = get_defined_vars(); include __DIR__ . '/../partials/dev-tools-offcanvas.php'; unset($__devVars); ?>

</body>
</html>
