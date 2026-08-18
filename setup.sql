-- ============================================================
-- {{TOOL_NAME}} - Tool-DB setup
-- Run this section in the tool-owned database.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `{{TOOL_DB_NAME}}`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `{{TOOL_DB_NAME}}`;

CREATE TABLE IF NOT EXISTS settings (
    name       VARCHAR(100) NOT NULL,
    value      TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (name, value) VALUES
    ('allow_self_registration', '1')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Add tool-specific tables below this line.


-- ============================================================
-- {{TOOL_NAME}} - Auth/User-DB setup
-- Run this section in the central Auth/User DB: {{AUTH_DB_NAME}}.
-- ============================================================

USE `{{AUTH_DB_NAME}}`;

CREATE TABLE IF NOT EXISTS tools (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL UNIQUE,
    display_name    VARCHAR(150) NOT NULL,
    url             VARCHAR(255) NOT NULL,
    users_table     VARCHAR(100) NOT NULL,
    approved_column VARCHAR(100) NOT NULL DEFAULT 'is_approved',
    admin_column    VARCHAR(100) DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{TOOL_USERS_TABLE}}` (
    `user_id`              INT(11) NOT NULL,
    `is_approved`          TINYINT(1) NOT NULL DEFAULT 0,
    `{{TOOL_ADMIN_COLUMN}}` TINYINT(1) NOT NULL DEFAULT 0,
    `added_at`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `added_by`             INT(11) DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_{{TOOL_SLUG}}_users_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tools (name, display_name, url, users_table, approved_column, admin_column, is_active)
VALUES (
    '{{TOOL_SLUG}}',
    '{{TOOL_NAME}}',
    '{{APP_BASE_URL}}',
    '{{TOOL_USERS_TABLE}}',
    'is_approved',
    '{{TOOL_ADMIN_COLUMN}}',
    1
)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    url = VALUES(url),
    users_table = VALUES(users_table),
    approved_column = VALUES(approved_column),
    admin_column = VALUES(admin_column),
    is_active = VALUES(is_active);

-- Optional: approve all existing users for this tool.
-- INSERT INTO `{{TOOL_USERS_TABLE}}` (user_id, is_approved)
-- SELECT id, 1 FROM users
-- ON DUPLICATE KEY UPDATE is_approved = is_approved;
