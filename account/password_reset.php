<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$currentUser = initAuth();
$pdo = getAuthPdo();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT id AS user_id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $error = "Ungueltiger oder abgelaufener Link.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $newPassword = $_POST['password'] ?? '';
    if (strlen($newPassword) < 5) {
        $error = "Passwort muss mindestens 5 Zeichen lang sein.";
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $reset['user_id']]);

        $pdo->prepare("UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?")->execute([$reset['user_id']]);
        $success = "Passwort erfolgreich geaendert. Du kannst dich jetzt einloggen.";
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
    <title>Neues Passwort - <?= h(appName()) ?></title>
    <link rel="stylesheet" href="/style.css">
    <script src="/scripts/helpers.js"></script>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>

<main>
    <?php if ($error): ?>
        <p class="error-message form-message"><?= h($error) ?></p>
    <?php elseif ($success): ?>
        <p class="success-message form-message"><?= h($success) ?></p>
    <?php endif; ?>

    <?php if ($reset && !$success): ?>
    <form method="POST" class="form-block">
        <h1>Neues Passwort</h1>
        <div class="input-row">
            <input type="password" name="password" placeholder="Neues Passwort (mind. 5 Zeichen)" required autocomplete="new-password">
            <button type="button" onclick="togglePassword(this)" class="btn btn-secondary">Anzeigen</button>
        </div>
        <button type="submit">Passwort speichern</button>
        <p class="form-note text-center">
            <a href="/account/login.php">Zurueck zum Login</a>
        </p>
    </form>
    <?php else: ?>
    <div class="form-block text-center">
        <p class="form-note"><a href="/account/login.php">Zurueck zum Login</a></p>
    </div>
    <?php endif; ?>
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
