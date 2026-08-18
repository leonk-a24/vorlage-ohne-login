<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

if (!defined('EXCEPTION_HANDLER_SET')) {
    define('EXCEPTION_HANDLER_SET', true);
    set_exception_handler(function (Throwable $e) {
        error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Fehler</title>'
            . '<link rel="stylesheet" href="/style.css"></head><body>'
            . '<main class="container" style="padding-top:3rem;">'
            . '<div class="card" style="padding:1.5rem;border-left:4px solid #c0392b;">'
            . '<h2 style="margin-top:0;">Ein Fehler ist aufgetreten</h2>'
            . '<p>Bitte versuche es erneut oder wende dich an den Administrator.</p>'
            . '<a href="/" class="btn btn-primary">Zur Startseite</a>'
            . '</div></main></body></html>';
        exit;
    });
}

function createToolUserIfMissing(PDO $authPdo, int $userId, int $approved, int $admin = 0, ?int $addedBy = null): void {
    $table = sqlIdentifier(toolUsersTable());
    $adminColumn = sqlIdentifier(toolAdminColumn());

    $columns = "user_id, {$adminColumn}, is_approved";
    $placeholders = "?, ?, ?";
    $values = [$userId, $admin, $approved];

    if ($addedBy !== null) {
        $columns .= ", added_by";
        $placeholders .= ", ?";
        $values[] = $addedBy;
    }

    $stmt = $authPdo->prepare("
        INSERT INTO {$table} ({$columns})
        VALUES ({$placeholders})
        ON DUPLICATE KEY UPDATE is_approved = is_approved
    ");
    $stmt->execute($values);
}

function setToolApproval(PDO $authPdo, int $userId, int $approved, int $addedBy): void {
    $table = sqlIdentifier(toolUsersTable());
    $adminColumn = sqlIdentifier(toolAdminColumn());

    $stmt = $authPdo->prepare("
        INSERT INTO {$table} (user_id, {$adminColumn}, is_approved, added_by)
        VALUES (?, 0, ?, ?)
        ON DUPLICATE KEY UPDATE is_approved = VALUES(is_approved), added_by = VALUES(added_by)
    ");
    $stmt->execute([$userId, $approved, $addedBy]);
}

function setToolAdmin(PDO $authPdo, int $userId, int $admin, int $addedBy): void {
    $table = sqlIdentifier(toolUsersTable());
    $adminColumn = sqlIdentifier(toolAdminColumn());

    $stmt = $authPdo->prepare("
        INSERT INTO {$table} (user_id, {$adminColumn}, added_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE {$adminColumn} = VALUES({$adminColumn}), added_by = VALUES(added_by)
    ");
    $stmt->execute([$userId, $admin, $addedBy]);
}

function fetchToolStatus(PDO $authPdo, int $userId): ?array {
    $table = sqlIdentifier(toolUsersTable());
    $adminColumn = sqlIdentifier(toolAdminColumn());

    $stmt = $authPdo->prepare("
        SELECT {$adminColumn} AS is_admin, is_approved
        FROM {$table}
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return null;
    }

    return [
        'is_admin' => (int)$row['is_admin'],
        'is_approved' => (int)$row['is_approved'],
    ];
}

function autoApproveIfEnabled(PDO $authPdo, int $userId): int {
    $autoApprove = isSelfRegistrationAllowed() ? 1 : 0;
    createToolUserIfMissing($authPdo, $userId, $autoApprove, 0);
    return $autoApprove;
}

function loadToolStatus(PDO $authPdo, int $userId): array {
    try {
        $status = fetchToolStatus($authPdo, $userId);
        if ($status !== null) {
            return $status;
        }

        $approved = autoApproveIfEnabled($authPdo, $userId);
        return ['is_admin' => 0, 'is_approved' => $approved];
    } catch (PDOException $e) {
        error_log('Tool status unavailable: ' . $e->getMessage());
        return ['is_admin' => 0, 'is_approved' => 0];
    }
}

function initAuth(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $cacheTimeout = 300;

    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_data']) && !empty($_SESSION['user_data_loaded_at'])) {
        if ((time() - $_SESSION['user_data_loaded_at']) < $cacheTimeout) {
            return $_SESSION['user_data'];
        }
    }

    if (!empty($_SESSION['user_id'])) {
        $authPdo = getAuthPdo();
        $stmt = $authPdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $appStatus = loadToolStatus($authPdo, (int)$user['id']);
            $user['is_tool_admin'] = $appStatus['is_admin'];
            $user['is_approved'] = $appStatus['is_approved'];
            $_SESSION['user_data'] = $user;
            $_SESSION['user_data_loaded_at'] = time();
            return $user;
        }

        clearUserSession();
        return null;
    }

    if (!empty($_COOKIE['remember_me'])) {
        $authPdo = getAuthPdo();
        $token = $_COOKIE['remember_me'];
        $stmt = $authPdo->prepare("
            SELECT u.* FROM user_tokens ut
            JOIN users u ON ut.user_id = u.id
            WHERE ut.token = ? AND ut.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $appStatus = loadToolStatus($authPdo, (int)$user['id']);
            $user['is_tool_admin'] = $appStatus['is_admin'];
            $user['is_approved'] = $appStatus['is_approved'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_data'] = $user;
            $_SESSION['user_data_loaded_at'] = time();
            return $user;
        }
        setcookie('remember_me', '', time() - 3600, '/', '', true, true);
    }

    return null;
}

function clearUserSession(): void {
    unset($_SESSION['user_id'], $_SESSION['user_data'], $_SESSION['user_data_loaded_at']);
}

function invalidateUserCache(): void {
    unset($_SESSION['user_data'], $_SESSION['user_data_loaded_at']);
}

function requireAuth(): void {
    $user = initAuth();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Nicht eingeloggt']]);
        exit;
    }
    if (!$user['is_approved']) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => ['code' => 'NOT_APPROVED', 'message' => 'Konto noch nicht freigeschaltet']]);
        exit;
    }
}

function requirePageAuth(string $loginUrl = '/account/login.php'): void {
    $user = initAuth();
    if (!$user) {
        header('Location: ' . $loginUrl);
        exit;
    }
    if (!$user['is_approved']) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Zugriff verweigert</title>'
            . '<link rel="stylesheet" href="/style.css"></head><body><main class="container" style="padding-top:3rem;">'
            . '<div class="card" style="padding:1.5rem;border-left:4px solid #c0392b;">'
            . '<h2 style="margin-top:0;">Konto nicht freigeschaltet</h2>'
            . '<p>Dein Konto wurde noch nicht fuer ' . h(appName()) . ' freigeschaltet.</p>'
            . '<a href="/account/login.php" class="btn btn-primary">Zum Login</a>'
            . '</div></main></body></html>';
        exit;
    }
}

function loginUser(array $user, PDO $authPdo): void {
    $appStatus = loadToolStatus($authPdo, (int)$user['id']);
    $user['is_tool_admin'] = $appStatus['is_admin'];
    $user['is_approved'] = $appStatus['is_approved'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_data'] = $user;
    $_SESSION['user_data_loaded_at'] = time();
}

function currentUserId(): int {
    $user = initAuth();
    if (!$user || !$user['is_approved']) {
        throw new Exception('Not authenticated');
    }
    return (int)$user['id'];
}

function currentUser(): ?array {
    return initAuth();
}

function isAdmin(?array $user): bool {
    return isset($user['is_tool_admin']) && (int)$user['is_tool_admin'] === 1;
}

function ipMatchesCidr(string $ip, string $cidr): bool {
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }
    [$subnet, $bits] = explode('/', $cidr, 2);
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    if ($ipLong === false || $subnetLong === false) {
        return false;
    }
    $mask = -1 << (32 - (int)$bits);
    return ($ipLong & $mask) === ($subnetLong & $mask);
}

function isTrustedProxyIp(string $ip): bool {
    $trustedProxies = appEnv('TRUSTED_PROXIES', '10.0.1.96');
    foreach (array_filter(array_map('trim', explode(',', $trustedProxies))) as $trustedProxy) {
        if (ipMatchesCidr($ip, $trustedProxy)) {
            return true;
        }
    }
    return false;
}

function getClientIp(): string {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return '';
    }
    if (isTrustedProxyIp($remoteAddr)) {
        foreach (array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) && !isTrustedProxyIp($ip)) {
                return $ip;
            }
        }
        if (filter_var($_SERVER['HTTP_X_REAL_IP'] ?? '', FILTER_VALIDATE_IP)) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
    }
    return $remoteAddr;
}

function buildAuthLogDetail(?string $detail = null): string {
    return json_encode([
        'detail' => $detail,
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'x_real_ip' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
        'x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function logAuthEvent(PDO $pdo, string $event, ?int $userId, string $identifier, ?string $detail = null): void {
    try {
        $pdo->prepare(
            "INSERT INTO auth_log (user_id, event, identifier, ip_address, user_agent, detail)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $userId,
            $event,
            mb_substr($identifier, 0, 255),
            mb_substr(getClientIp(), 0, 45),
            mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
            buildAuthLogDetail($detail),
        ]);
    } catch (PDOException $e) {
        error_log('logAuthEvent failed: ' . $e->getMessage());
    }
}

function countLoginAttempts(PDO $pdo, string $ip, string $identifier): int {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND identifier = ?
               AND attempted_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );
        $stmt->execute([$ip, mb_strtolower($identifier)]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function recordLoginAttempt(PDO $pdo, string $ip, string $identifier): int {
    try {
        $pdo->prepare("INSERT INTO login_attempts (ip_address, identifier) VALUES (?, ?)")
            ->execute([$ip, mb_strtolower($identifier)]);
    } catch (PDOException $e) {
        error_log('recordLoginAttempt failed: ' . $e->getMessage());
    }
    return countLoginAttempts($pdo, $ip, $identifier);
}

function clearLoginAttempts(PDO $pdo, string $ip, string $identifier): void {
    try {
        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND identifier = ?")
            ->execute([$ip, mb_strtolower($identifier)]);
    } catch (PDOException $e) {
        error_log('clearLoginAttempts failed: ' . $e->getMessage());
    }
}

function generateAndSendLoginCode(PDO $pdo, array $user, string $appName): bool {
    try {
        $pdo->prepare("UPDATE login_codes SET used = 1 WHERE user_id = ? AND used = 0")->execute([$user['id']]);
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO login_codes (user_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
            ->execute([$user['id'], $code]);
        return send_email(
            $user['email'],
            "Login-Code - {$appName}",
            "Hallo {$user['username']},\n\nHier ist dein einmaliger Login-Code (gueltig 15 Minuten):\n\n    {$code}\n\nFalls du das nicht warst, aendere bitte dein Passwort."
        );
    } catch (Exception $e) {
        error_log('generateAndSendLoginCode failed: ' . $e->getMessage());
        return false;
    }
}

function getUserDetailsCached(array $userIds): array {
    if (empty($userIds)) { return []; }
    if (!isset($_SESSION['user_details_cache'])) {
        $_SESSION['user_details_cache'] = [];
        $_SESSION['user_details_cache_time'] = [];
    }

    $result = [];
    $missingIds = [];
    foreach ($userIds as $userId) {
        $userId = (int)$userId;
        if (isset($_SESSION['user_details_cache'][$userId]) &&
            (time() - ($_SESSION['user_details_cache_time'][$userId] ?? 0)) < 300) {
            $result[$userId] = $_SESSION['user_details_cache'][$userId];
        } else {
            $missingIds[] = $userId;
        }
    }

    if ($missingIds) {
        $authPdo = getAuthPdo();
        $placeholders = implode(',', array_fill(0, count($missingIds), '?'));
        $stmt = $authPdo->prepare("SELECT id, username, email, is_approved, is_global_admin FROM users WHERE id IN ($placeholders)");
        $stmt->execute($missingIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $uid = (int)$user['id'];
            $result[$uid] = $user;
            $_SESSION['user_details_cache'][$uid] = $user;
            $_SESSION['user_details_cache_time'][$uid] = time();
        }
    }

    return $result;
}

function getUserDetailCached(int $userId): ?array {
    $result = getUserDetailsCached([$userId]);
    return $result[$userId] ?? null;
}
