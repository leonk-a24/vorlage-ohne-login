<?php

function appEnv(string $name, ?string $default = null): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default ?? '';
    }
    return (string)$value;
}

function appName(): string {
    return appEnv('APP_NAME', 'Tool Template');
}

function toolSlug(): string {
    $slug = appEnv('TOOL_SLUG', 'tool_template');
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $slug));
    $slug = trim($slug, '_');
    return $slug !== '' ? $slug : 'tool_template';
}

function toolUsersTable(): string {
    return appEnv('TOOL_USERS_TABLE', toolSlug() . '_users');
}

function toolAdminColumn(): string {
    return appEnv('TOOL_ADMIN_COLUMN', 'is_' . toolSlug() . '_admin');
}

function appBaseUrl(): string {
    $configured = rtrim(appEnv('APP_BASE_URL', ''), '/');
    if ($configured !== '') {
        return $configured;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO']
        ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    return rtrim($scheme . '://' . $host, '/');
}

function userManagementUrl(): string {
    return rtrim(appEnv('USER_MANAGEMENT_URL', ''), '/');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requireSqlIdentifier(string $identifier): string {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
        throw new InvalidArgumentException("Invalid SQL identifier: {$identifier}");
    }
    return $identifier;
}

function sqlIdentifier(string $identifier): string {
    return '`' . requireSqlIdentifier($identifier) . '`';
}
