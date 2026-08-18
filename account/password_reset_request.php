<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';

$pdo = getAuthPdo();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ungueltige E-Mail-Adresse.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")
                ->execute([$token, $expires, $user['id']]);

            $link = appBaseUrl() . "/account/password_reset.php?token=$token";
            $subject = "Passwort zuruecksetzen - " . appName();
            $message = "Du kannst dein Passwort ueber folgenden Link zuruecksetzen:\n\n$link\n\nDer Link ist 1 Stunde gueltig.";

            send_email($email, $subject, $message);
            logAuthEvent($pdo, 'reset_request', (int)$user['id'], $email);
            $success = "Eine E-Mail mit Anweisungen wurde gesendet.";
        } else {
            $error = "Es wurde kein Benutzer mit dieser E-Mail gefunden.";
        }
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
    <title>Passwort vergessen - <?= h(appName()) ?></title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>

<main>
    <?php if ($error): ?>
        <p class="error-message form-message"><?= h($error) ?></p>
    <?php elseif ($success): ?>
        <p class="success-message form-message"><?= h($success) ?></p>
    <?php endif; ?>

    <form method="POST" class="form-block">
        <h1>Passwort vergessen</h1>
        <input type="email" name="email" placeholder="E-Mail-Adresse" required autocomplete="email">
        <button type="submit">Link anfordern</button>
        <p class="form-note text-center">
            <a href="/account/login.php">Zurueck zum Login</a>
        </p>
    </form>
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
