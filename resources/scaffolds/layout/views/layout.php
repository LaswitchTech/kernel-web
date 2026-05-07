<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{title}}</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="/css">
    <!-- Theme CSS -->
    <link rel="stylesheet" href="/assets/lib/layouts/{{slug}}/less/theme.less">

    <?php echo \App\Core\HookRegistry::render('layout.head'); ?>
</head>
<body>
    <?php echo \App\Core\HookRegistry::render('layout.body.start'); ?>

    <div class="app-shell">
        <div class="app-main" id="app-main">
            <main class="app-content">
                {{content}}
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <?php echo \App\Core\HookRegistry::render('layout.body.end'); ?>
</body>
</html>
