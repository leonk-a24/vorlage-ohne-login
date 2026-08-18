<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$currentUser = initAuth();
$pdo = getAuthPdo();

if (!$currentUser) {
    header("Location: /account/login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';

    if (!password_verify($current, $currentUser['password_hash'])) {
        $error = "Aktuelles Passwort ist falsch.";
    } elseif (strlen($new) < 5) {
        $error = "Neues Passwort ist zu kurz.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $currentUser['id']]);
        $success = "Passwort erfolgreich geaendert.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    <title>Einstellungen - <?= h(appName()) ?></title>
    <link rel="stylesheet" href="/style.css">
    <script src="/scripts/helpers.js"></script>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>

<main class="container narrow-page">
    <div class="page-header">
        <h1>Einstellungen</h1>
    </div>

    <section class="card panel">
        <h2>Kontoinformationen</h2>
        <dl class="key-value-list">
            <div>
                <dt>Benutzername</dt>
                <dd><?= h($currentUser['username']) ?></dd>
            </div>
            <div>
                <dt>E-Mail</dt>
                <dd><?= h($currentUser['email'] ?? '-') ?></dd>
            </div>
        </dl>
    </section>

    <?php if ($error): ?>
        <p class="error-message"><?= h($error) ?></p>
    <?php elseif ($success): ?>
        <p class="success-message"><?= h($success) ?></p>
    <?php endif; ?>

    <form method="POST" class="card panel stack">
        <h2>Passwort aendern</h2>
        <div class="input-row">
            <input type="password" name="current_password" placeholder="Aktuelles Passwort" required autocomplete="current-password">
            <button type="button" onclick="togglePassword(this)" class="btn btn-secondary">Anzeigen</button>
        </div>
        <div class="input-row">
            <input type="password" name="new_password" placeholder="Neues Passwort (min. 5 Zeichen)" required autocomplete="new-password">
            <button type="button" onclick="togglePassword(this)" class="btn btn-secondary">Anzeigen</button>
        </div>
        <button type="submit" class="btn btn-primary">Passwort aendern</button>
    </form>
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
