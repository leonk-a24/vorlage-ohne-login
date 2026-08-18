<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';

$pdo = getAuthPdo();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['register_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['register_loaded_at'] = time();
}

$allowSelfRegistration = isSelfRegistrationAllowed();

if (!$allowSelfRegistration) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
        <title>Registrierung deaktiviert - <?= h(appName()) ?></title>
        <link rel="stylesheet" href="/style.css">
    </head>
    <body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>
    <main class="container narrow-page">
        <div class="card panel text-center">
            <h1>Registrierung deaktiviert</h1>
            <p>Die Registrierung ist derzeit nicht moeglich. Bitte wende dich an einen Administrator.</p>
            <a href="/account/login.php" class="btn btn-primary">Zum Login</a>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
    <?php
    exit;
}

$error = '';
$success = '';

function verifyTurnstile(?string $clientIp): bool {
    $secret = appEnv('TURNSTILE_SECRET_KEY', '');
    $token = $_POST['cf-turnstile-response'] ?? '';

    if ($secret === '') {
        return true;
    }

    if ($token === '') {
        return false;
    }

    $payload = ['secret' => $secret, 'response' => $token];
    if ($clientIp) {
        $payload['remoteip'] = $clientIp;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_TIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    if ($raw === false) {
        return false;
    }
    $result = json_decode($raw, true);
    return !empty($result['success']);
}

function tooManyRegistrationAttempts(?string $ip, string $email, string $username): bool {
    $pdo = getAuthPdo();

    try {
        if ($ip) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM auth_log
                WHERE ip_address = ?
                  AND event IN ('register_attempt', 'register_ok', 'register_blocked_turnstile', 'register_blocked_rate')
                  AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ");
            $stmt->execute([$ip]);
            if ((int)$stmt->fetchColumn() >= 8) {
                return true;
            }
        }

        if ($email !== '') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM auth_log
                WHERE identifier = ?
                  AND event IN ('register_attempt', 'register_ok')
                  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$email]);
            if ((int)$stmt->fetchColumn() >= 3) {
                return true;
            }
        }

        if ($username !== '') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM auth_log
                WHERE identifier = ?
                  AND event IN ('register_attempt', 'register_ok')
                  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$username]);
            if ((int)$stmt->fetchColumn() >= 5) {
                return true;
            }
        }
    } catch (PDOException $e) {
        error_log('registration rate limit unavailable: ' . $e->getMessage());
        return false;
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $clientIp = getClientIp();

    logAuthEvent($pdo, 'register_attempt', null, $email ?: $username);

    if (!empty($_POST['website'] ?? '')) {
        logAuthEvent($pdo, 'register_blocked_honeypot', null, $email ?: $username);
        $error = "Registrierung konnte nicht verarbeitet werden.";
    } elseif (!hash_equals($_SESSION['register_csrf'] ?? '', $_POST['csrf_register'] ?? '')) {
        logAuthEvent($pdo, 'register_blocked_csrf', null, $email ?: $username);
        $error = "Die Sitzung ist abgelaufen. Bitte lade die Seite neu.";
    } elseif (!isset($_SESSION['register_loaded_at']) || time() - (int)$_SESSION['register_loaded_at'] < 3) {
        logAuthEvent($pdo, 'register_blocked_too_fast', null, $email ?: $username);
        $error = "Bitte versuche es erneut.";
    } elseif (tooManyRegistrationAttempts($clientIp, $email, $username)) {
        logAuthEvent($pdo, 'register_blocked_rate', null, $email ?: $username);
        $error = "Zu viele Registrierungsversuche. Bitte versuche es spaeter erneut.";
    } elseif (!verifyTurnstile($clientIp)) {
        logAuthEvent($pdo, 'register_blocked_turnstile', null, $email ?: $username);
        $error = "Bot-Pruefung fehlgeschlagen. Bitte versuche es erneut.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ungueltige E-Mail-Adresse.";
    } elseif (strlen($username) < 3) {
        $error = "Benutzername muss mindestens 3 Zeichen lang sein.";
    } elseif (strlen($password) < 5) {
        $error = "Passwort muss mindestens 5 Zeichen lang sein.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            $error = "Benutzername oder E-Mail bereits vergeben.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $deleteToken = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, delete_token) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $email, $deleteToken]);
            $newUserId = (int)$pdo->lastInsertId();
            logAuthEvent($pdo, 'register_ok', $newUserId, $username);

            $autoApprove = $allowSelfRegistration ? 1 : 0;
            createToolUserIfMissing($pdo, $newUserId, $autoApprove, 0);

            if ($autoApprove) {
                try {
                    send_email(
                        $email,
                        'Willkommen bei ' . appName(),
                        "Hallo {$username},\n\n"
                        . "deine Registrierung war erfolgreich. Du kannst dich jetzt anmelden:\n"
                        . appBaseUrl() . "/account/login.php"
                    );
                } catch (Exception $e) {
                    error_log('User notification failed: ' . $e->getMessage());
                }
            }

            try {
                $adminStmt = $pdo->query("SELECT email FROM users WHERE is_global_admin = 1 AND email IS NOT NULL AND email != ''");
                $adminEmails = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($adminEmails)) {
                    $deleteLink = appBaseUrl() . '/account/delete_registration.php?token=' . $deleteToken;
                    if ($autoApprove) {
                        $subject = "Neue Registrierung (freigeschaltet): {$username} - " . appName();
                        $message = "Ein neuer Nutzer hat sich registriert und wurde automatisch freigeschaltet:\n\n"
                                 . "Benutzername: {$username}\n"
                                 . "E-Mail: {$email}\n\n"
                                 . "Falls die Registrierung unerwuenscht war:\n{$deleteLink}";
                    } else {
                        $subject = "Neue Registrierung (wartet): {$username} - " . appName();
                        $message = "Ein neuer Nutzer hat sich registriert und wartet auf Freischaltung:\n\n"
                                 . "Benutzername: {$username}\n"
                                 . "E-Mail: {$email}\n\n"
                                 . "Freischalten unter: " . appBaseUrl() . "/admin.php\n\n"
                                 . "Falls die Registrierung unerwuenscht war:\n{$deleteLink}";
                    }
                    send_email($adminEmails, $subject, $message);
                }
            } catch (Exception $e) {
                error_log('Admin notification failed: ' . $e->getMessage());
            }

            $success = $autoApprove
                ? "Registrierung erfolgreich! Du kannst dich jetzt anmelden."
                : "Registrierung erfolgreich! Dein Konto wird noch von einem Administrator freigeschaltet.";
        }
    }
}

$turnstileSiteKey = appEnv('TURNSTILE_SITE_KEY', '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    <title>Registrieren - <?= h(appName()) ?></title>
    <link rel="stylesheet" href="/style.css">
    <script src="/scripts/helpers.js"></script>
    <?php if ($turnstileSiteKey !== ''): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>

<main>
    <?php if ($success): ?>
        <div class="form-block">
            <p class="success-message"><?= h($success) ?></p>
            <p class="form-note text-center">
                <a href="/account/login.php">Zum Login</a>
            </p>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <p class="error-message form-message"><?= h($error) ?></p>
        <?php endif; ?>
        <form method="POST" class="form-block">
            <h1>Registrieren</h1>
            <input type="hidden" name="csrf_register" value="<?= h($_SESSION['register_csrf'] ?? '') ?>">

            <input
                type="text"
                name="website"
                value=""
                autocomplete="off"
                tabindex="-1"
                aria-hidden="true"
                class="honeypot"
            >

            <input type="text" name="username" placeholder="Benutzername (mind. 3 Zeichen)" required autocomplete="username"
                   value="<?= h($_POST['username'] ?? '') ?>">
            <input type="email" name="email" placeholder="E-Mail-Adresse" required autocomplete="email"
                   value="<?= h($_POST['email'] ?? '') ?>">
            <div class="input-row">
                <input type="password" name="password" placeholder="Passwort (mind. 5 Zeichen)" required autocomplete="new-password">
                <button type="button" onclick="togglePassword(this)" class="btn btn-secondary">Anzeigen</button>
            </div>
            <?php if ($turnstileSiteKey !== ''): ?>
                <div class="cf-turnstile" data-sitekey="<?= h($turnstileSiteKey) ?>"></div>
            <?php endif; ?>
            <button type="submit">Registrieren</button>
            <p class="form-note text-center">
                Schon registriert? <a href="/account/login.php">Hier einloggen</a>
            </p>
            <p class="form-note text-center">
                <a href="/account/password_reset_request.php">Passwort vergessen?</a>
            </p>
        </form>
    <?php endif; ?>
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
