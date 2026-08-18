<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$currentUser = initAuth();
$pdo = getAuthPdo();

if ($currentUser) {
    header('Location: /');
    exit;
}

$error = '';
$ip = getClientIp();
$rateLimit = 5;
$dummyHash = '$2y$10$' . str_repeat('A', 53);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $identifier = mb_strtolower($username);

    if (countLoginAttempts($pdo, $ip, $identifier) >= $rateLimit) {
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $rlUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($rlUser && !empty($rlUser['email'])) {
            if (empty($_SESSION['code_sent_user']) || $_SESSION['code_sent_user'] !== (int)$rlUser['id']) {
                generateAndSendLoginCode($pdo, $rlUser, appName());
                logAuthEvent($pdo, 'code_sent', (int)$rlUser['id'], $identifier, 'Pre-check rate limit');
                $_SESSION['code_sent_user'] = (int)$rlUser['id'];
            }
            $_SESSION['login_code_user_id'] = (int)$rlUser['id'];
        }
        header('Location: /account/login_code.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $passOk = password_verify($password, $user['password_hash'] ?? $dummyHash);

    if ($user && $passOk) {
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            try {
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            } catch (PDOException $e) {
                error_log('password rehash failed: ' . $e->getMessage());
            }
        }

        $appStatus = loadToolStatus($pdo, (int)$user['id']);
        if (!$appStatus['is_approved']) {
            logAuthEvent($pdo, 'login_blocked', (int)$user['id'], $identifier, 'Not approved');
            $error = "Login fehlgeschlagen.";
        } else {
            clearLoginAttempts($pdo, $ip, $identifier);

            if (!empty($_POST['remember_me'])) {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', time() + 86400 * 365);
                try {
                    $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at, user_agent, ip_address)
                                   VALUES (?, ?, ?, ?, ?)")
                        ->execute([
                            $user['id'],
                            $token,
                            $expiry,
                            mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
                            $ip,
                        ]);
                    setcookie('remember_me', $token, [
                        'expires' => strtotime($expiry),
                        'path' => '/',
                        'httponly' => true,
                        'secure' => true,
                        'samesite' => 'Lax',
                    ]);
                } catch (PDOException $e) {
                    error_log('user_tokens INSERT failed: ' . $e->getMessage());
                }
            }

            loginUser($user, $pdo);
            logAuthEvent($pdo, 'login_ok', (int)$user['id'], $identifier);
            header('Location: /');
            exit;
        }
    } else {
        $attempts = recordLoginAttempt($pdo, $ip, $identifier);
        logAuthEvent($pdo, 'login_fail', $user ? (int)$user['id'] : null, $identifier);

        if ($attempts >= $rateLimit && $user && !empty($user['email'])) {
            if (empty($_SESSION['code_sent_user']) || $_SESSION['code_sent_user'] !== (int)$user['id']) {
                generateAndSendLoginCode($pdo, $user, appName());
                logAuthEvent($pdo, 'code_sent', (int)$user['id'], $identifier, 'Post-fail rate limit');
                $_SESSION['code_sent_user'] = (int)$user['id'];
            }
            $_SESSION['login_code_user_id'] = (int)$user['id'];
            header('Location: /account/login_code.php');
            exit;
        }

        $error = "Login fehlgeschlagen.";
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
    <title>Anmelden - <?= h(appName()) ?></title>
    <link rel="stylesheet" href="/style.css">
    <script src="/scripts/helpers.js"></script>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>

<main>
    <?php if ($error): ?>
        <p class="error-message form-message"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="POST" class="form-block">
        <h1>Anmelden</h1>
        <input type="text" name="username" placeholder="Benutzername oder E-Mail" required autocomplete="username">
        <div class="input-row">
            <input type="password" name="password" placeholder="Passwort" required autocomplete="current-password">
            <button type="button" onclick="togglePassword(this)" class="btn btn-secondary">Anzeigen</button>
        </div>
        <label class="checkbox-label">
            <input type="checkbox" name="remember_me" checked> Eingeloggt bleiben
        </label>
        <button type="submit">Einloggen</button>
        <p class="form-note text-center">
            <a href="/account/password_reset_request.php">Passwort vergessen?</a>
        </p>
        <?php if (isSelfRegistrationAllowed()): ?>
            <p class="form-note text-center">
                Noch keinen Account? <a href="/account/register.php">Jetzt registrieren</a>
            </p>
        <?php endif; ?>
    </form>
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
