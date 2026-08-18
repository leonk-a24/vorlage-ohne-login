<?php

require_once __DIR__ . '/config.php';

function getAuthPdo(): PDO {
    static $authPdo = null;

    if ($authPdo !== null) {
        return $authPdo;
    }

    $authPdo = createConfiguredPdo(
        appEnv('AUTH_DB_HOST', 'localhost'),
        appEnv('AUTH_DB_PORT', '3306'),
        appEnv('AUTH_DB_NAME', 'Hestia_Admin_users'),
        appEnv('AUTH_DB_USER', 'root'),
        appEnv('AUTH_DB_PASS', ''),
        'AUTH_DB'
    );

    return $authPdo;
}

function getToolPdo(): PDO {
    static $toolPdo = null;

    if ($toolPdo !== null) {
        return $toolPdo;
    }

    $toolPdo = createConfiguredPdo(
        appEnv('TOOL_DB_HOST', 'localhost'),
        appEnv('TOOL_DB_PORT', '3306'),
        appEnv('TOOL_DB_NAME', toolSlug()),
        appEnv('TOOL_DB_USER', 'root'),
        appEnv('TOOL_DB_PASS', ''),
        'TOOL_DB'
    );

    return $toolPdo;
}

function createConfiguredPdo(string $host, string $port, string $db, string $user, string $pass, string $label): PDO {
    $charset = 'utf8mb4';
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset};connect_timeout=2";
    return createPdoWithRetry($dsn, $user, $pass, pdoOptions(), $label);
}

function pdoOptions(): array {
    return [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 2,
    ];
}

function createPdoWithRetry(string $dsn, string $user, string $pass, array $options, string $dbName): PDO {
    $retries = 3;
    $waitUs = 500000;

    while ($retries-- > 0) {
        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            if ($retries === 0) {
                error_log("{$dbName} Connection Failed: " . $e->getMessage());
                if (in_array(appEnv('APP_ENV', 'production'), ['prod', 'production'], true)) {
                    http_response_code(500);
                    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Datenbankfehler</title></head><body>";
                    echo "<h1>Datenbank nicht erreichbar</h1>";
                    echo "<p><strong>" . h($dbName) . "</strong> konnte keine Verbindung herstellen.</p>";
                    echo "<p>Bitte pruefe die CapRover ENV-Variablen und die MariaDB-Erreichbarkeit.</p>";
                    echo "</body></html>";
                    exit;
                }
                throw $e;
            }
            usleep($waitUs);
        }
    }

    throw new RuntimeException("{$dbName} connection failed");
}

function getToolSetting(string $name, ?string $default = null): ?string {
    try {
        $stmt = getToolPdo()->prepare("SELECT value FROM settings WHERE name = ?");
        $stmt->execute([$name]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    } catch (Throwable $e) {
        error_log('Tool setting unavailable: ' . $e->getMessage());
        return $default;
    }
}

function setToolSetting(string $name, string $value): void {
    $stmt = getToolPdo()->prepare("
        INSERT INTO settings (name, value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$name, $value]);
}

function isSelfRegistrationAllowed(): bool {
    return getToolSetting('allow_self_registration', '1') === '1';
}
