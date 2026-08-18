-- ============================================================
-- {{TOOL_NAME}} - Tool-DB setup
-- Run this section in the tool-owned database.
-- ============================================================

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
