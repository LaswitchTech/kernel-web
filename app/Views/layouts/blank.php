<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            min-height: 100vh;
            background: #f8f9fa;
        }
        .landing-card {
            max-width: 640px;
            margin: 2rem auto;
        }
    </style>
</head>
<body>
<?= $content ?>
</body>
</html>
