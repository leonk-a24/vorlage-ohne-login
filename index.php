<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';
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
            <h1>Huhu</h1>
            <p class="subtitle">Die gemeinsame Auth-, Mail-, DB- und Admin-Basis ist eingerichtet. Also vielleicht. Mal schauen...</p>
        </div>
    </div>

    <section class="grid-2">
        <article class="card panel">
            <h2>Angemeldeter Benutzer</h2>
            <dl class="key-value-list">
                <div>
                    <dt>Benutzername</dt>
                    <dd>Gibts nüscht</dd>
                </div>
                <div>
                    <dt>E-Mail</dt>
                    <dd>irgendwas @sexdungeon</dd>
                </div>
                <div>
                    <dt>Rolle</dt>
                    <dd>Domina</dd>
                </div>
            </dl>
        </article>

        <article class="card panel">
            <h2>Nächster Schritt</h2>
            <p class="muted">Ersetze diese Startseite durch die eigentliche Tool-Oberfläche und lege deine tool-eigenen Tabellen in der Tool-DB an.</p>
        </article>
    </section>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
