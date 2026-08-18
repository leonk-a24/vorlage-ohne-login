# DB-Setup fuer {{TOOL_NAME}}

## Werte

| Wert | Inhalt |
|---|---|
| Tool | `{{TOOL_NAME}}` |
| URL | `{{APP_BASE_URL}}` |
| Tool-DB | `{{TOOL_DB_NAME}}` |

## 1. Tool-DB anlegen

In der MariaDB fuer tool-eigene Daten:

```sql
CREATE TABLE IF NOT EXISTS settings (
    name       VARCHAR(100) NOT NULL,
    value      TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (name, value) VALUES
    ('allow_self_registration', '1')
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

Die komplette Version liegt in `setup.sql`.