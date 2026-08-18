# User-Verwaltung und Auth-Architektur

Gilt fuer: PHP-Tools mit zentraler Auth/User-DB und tool-eigener Datenbank.

## Zwei-Datenbank-Modell

Jedes Tool nutzt zwei Datenbanken:

| Datenbank | Zweck | PHP-Verbindung |
|---|---|---|
| Zentrale Auth/User-DB `{{AUTH_DB_NAME}}` | User, Tokens, Login-Codes, Auth-Log, Tool-Registry, Tool-Freischaltungen | `getAuthPdo()` |
| Tool-DB `{{TOOL_DB_NAME}}` | Tool-eigene Daten und tool-eigene Einstellungen | `getToolPdo()` |

Die Freischaltung liegt absichtlich in der Auth/User-DB, weil `{{TOOL_USERS_TABLE}}.user_id` per Foreign Key auf `users.id` zeigt. Foreign Keys ueber Server- oder DB-Grenzen hinweg sind nicht verlaesslich nutzbar.

## Relevante Tabellen

### Auth/User-DB

| Tabelle | Zweck |
|---|---|
| `users` | Globale User-Stammdaten |
| `user_tokens` | Remember-me-Tokens |
| `login_attempts` | Rate-Limit fuer Login |
| `login_codes` | 6-stellige Login-Codes nach Rate-Limit |
| `auth_log` | Audit-Log fuer Login, Registrierung und Reset |
| `tools` | Zentrale Tool-Registry |
| `{{TOOL_USERS_TABLE}}` | Freischaltung und Tool-Admin-Rolle fuer {{TOOL_NAME}} |

`users`, `user_tokens`, `login_attempts`, `login_codes` und `auth_log` gehoeren zur zentralen User-Verwaltung und werden in der Regel einmalig durch das User-Management-Tool angelegt. Neue Tools muessen normalerweise nur `tools` und die eigene `{{TOOL_USERS_TABLE}}` ergaenzen.

### Tool-DB

| Tabelle | Zweck |
|---|---|
| `settings` | Tool-eigene Einstellungen, aktuell `allow_self_registration` |
| weitere Tabellen | Fachliche Daten des jeweiligen Tools |

## Freischaltungstabelle

`{{TOOL_USERS_TABLE}}` hat immer diese Struktur:

```sql
CREATE TABLE `{{TOOL_USERS_TABLE}}` (
  `user_id` INT(11) NOT NULL,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
  `{{TOOL_ADMIN_COLUMN}}` TINYINT(1) NOT NULL DEFAULT 0,
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `added_by` INT(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

## Login-Flow

1. User meldet sich mit Benutzername/E-Mail und Passwort an.
2. Passwort wird gegen `users.password_hash` geprueft.
3. Das Tool liest `{{TOOL_USERS_TABLE}}`.
4. Wenn kein Eintrag existiert, prueft `autoApproveIfEnabled()` die Tool-DB-Einstellung `allow_self_registration`.
5. Nur freigeschaltete User duerfen ins Tool.
6. Tool-Admin ist, wer globaler Admin ist oder `{{TOOL_ADMIN_COLUMN}} = 1` hat.

## Registrierungs-Flow

Bei aktiver Selbstregistrierung:

```sql
INSERT INTO users (...)
INSERT INTO {{TOOL_USERS_TABLE}} (user_id, is_approved)
VALUES (<neue_user_id>, 1)
```

Bei deaktivierter Selbstregistrierung wird die Registrierung blockiert. Bestehende User koennen von Admins im Tool-Admin-Panel freigeschaltet werden.

## Tool-Registry

Jedes Tool wird in `tools` registriert:

```sql
INSERT INTO tools (name, display_name, url, users_table, approved_column, admin_column, is_active)
VALUES (
  '{{TOOL_SLUG}}',
  '{{TOOL_NAME}}',
  '{{APP_BASE_URL}}',
  '{{TOOL_USERS_TABLE}}',
  'is_approved',
  '{{TOOL_ADMIN_COLUMN}}',
  1
);
```

Das zentrale User-Management kann neue Tools dadurch automatisch anzeigen.

## Code-Konventionen

- `includes/config.php`: ENV, App-Name, SQL-Identifier, URLs
- `includes/db.php`: `getAuthPdo()`, `getToolPdo()`, Tool-Settings
- `includes/auth.php`: Login-Session, Freischaltung, Admin-Checks, Auth-Log
- `includes/mail_config.php`: nativer Mailversand oder SMTP
- `admin.php`: generische Tool-Freischaltung
- `index.php`: Platzhalter fuer die fachliche Tool-Oberflaeche

Tool-spezifischer Code gehoert in neue Includes, eigene API-Dateien und tool-eigene Tabellen. Die gemeinsame Auth-/Admin-Schicht sollte nur erweitert werden, wenn alle Tools davon profitieren.
