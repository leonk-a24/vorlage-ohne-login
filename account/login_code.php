<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$currentUser = initAuth();
if ($currentUser) {
    header('Location: /');
    exit;
}

if (empty($_SESSION['login_code_user_id'])) {
    header('Location: /account/login.php');
    exit;
}

$pdo = getAuthPdo();
$pendingUserId = (int)$_SESSION['login_code_user_id'];
$ip = getClientIp();
$error = '';
$resent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
        $stmt->execute([$pendingUserId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u && !empty($u['email'])) {
            generateAndSendLoginCode($pdo, $u, appName());
            logAuthEvent($pdo, 'code_sent', $pendingUserId, $u['username'], 'Resend requested');
            unset($_SESSION['code_sent_user']);
        }
        $resent = true;
    } else {
        $code = trim($_POST['code'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            $error = "Bitte gib einen gueltigen 6-stelligen Code ein.";
        } else {
            $stmt = $pdo->prepare(
                "SELECT id FROM login_codes
                 WHERE user_id = ? AND code = ? AND expires_at > NOW() AND used = 0
                 LIMIT 1"
            );
            $stmt->execute([$pendingUserId, $code]);
            $codeRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($codeRow) {
                $pdo->prepare("UPDATE login_codes SET used = 1 WHERE id = ?")
                    ->execute([$codeRow['id']]);

                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$pendingUserId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    clearLoginAttempts($pdo, $ip, mb_strtolower($user['username']));
                    clearLoginAttempts($pdo, $ip, mb_strtolower($user['email'] ?? ''));

                    $appStatus = loadToolStatus($pdo, (int)$user['id']);
                    if (!$appStatus['is_approved']) {
                        logAuthEvent($pdo, 'login_blocked', (int)$user['id'], $user['username'], 'Code OK but not approved');
                        unset($_SESSION['login_code_user_id'], $_SESSION['code_sent_user']);
                        $error = "Login fehlgeschlagen.";
                    } else {
                        loginUser($user, $pdo);
                        unset($_SESSION['login_code_user_id'], $_SESSION['code_sent_user']);
                        logAuthEvent($pdo, 'login_code_ok', (int)$user['id'], $user['username']);
                        header('Location: /');
                        exit;
                    }
                } else {
                    $error = "Login fehlgeschlagen.";
                }
            } else {
                logAuthEvent($pdo, 'login_code_fail', $pendingUserId, '');
                $error = "Code ungueltig oder abgelaufen.";
            }
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
    <title>Login-Code eingeben - <?= h(appName()) ?></title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>

<main>
    <div class="form-block">
        <h1>Login-Code</h1>
        <p class="form-note">Ein 6-stelliger Code wurde an deine E-Mail-Adresse gesendet.</p>

        <?php if ($error): ?>
            <p class="error-message"><?= h($error) ?></p>
        <?php elseif ($resent): ?>
            <p class="success-message">Ein neuer Code wurde gesendet.</p>
        <?php endif; ?>

        <form method="POST" class="stack">
            <input type="text" name="code" placeholder="6-stelliger Code" maxlength="6"
                   pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" required>
            <button type="submit">Code bestaetigen</button>
        </form>

        <form method="POST" class="stack">
            <button type="submit" name="resend" value="1" class="btn btn-secondary">Code erneut senden</button>
        </form>

        <p class="form-note text-center">
            <a href="/account/login.php">Zurueck zum Login</a>
        </p>
    </div>
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
