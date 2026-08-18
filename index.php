<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';

requirePageAuth();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(appName()) ?></title>
    <link rel="stylesheet" href="/style.css">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>

<main class="container app-page">
    <div class="page-header">
        <div>
            <h1><?= h(appName()) ?></h1>
            <p class="subtitle">Die gemeinsame Auth-, Mail-, DB- und Admin-Basis ist eingerichtet.</p>
        </div>
    </div>

    <section class="grid-2">
        <article class="card panel">
            <h2>Angemeldeter Benutzer</h2>
            <dl class="key-value-list">
                <div>
                    <dt>Benutzername</dt>
                    <dd><?= h($currentUser['username'] ?? '') ?></dd>
                </div>
                <div>
                    <dt>E-Mail</dt>
                    <dd><?= h($currentUser['email'] ?? '-') ?></dd>
                </div>
                <div>
                    <dt>Rolle</dt>
                    <dd><?= isAdmin($currentUser) ? 'Tool-Admin' : 'User' ?></dd>
                </div>
            </dl>
        </article>

        <article class="card panel">
            <h2>Naechster Schritt</h2>
            <p class="muted">Ersetze diese Startseite durch die eigentliche Tool-Oberflaeche und lege deine tool-eigenen Tabellen in der Tool-DB an.</p>
            <?php if (!empty($currentUser['is_global_admin']) || isAdmin($currentUser)): ?>
                <a class="btn btn-primary" href="/admin.php">Admin oeffnen</a>
            <?php endif; ?>
        </article>
    </section>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
