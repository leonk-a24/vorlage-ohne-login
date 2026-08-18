# {{TOOL_NAME}}

PHP-Tool-Vorlage mit gemeinsamer Benutzerverwaltung, tool-eigener MariaDB-Datenbank, Mailversand und CapRover/Docker-Deployment.

Diese Vorlage enthaelt bewusst noch keine fachliche Tool-Logik. Die Startseite, API-Endpunkte und tool-eigenen Tabellen werden pro Tool ergaenzt.

## Enthalten

- Login, Logout, Registrierung, Passwort-Reset und Login-Code bei Rate-Limit
- Anbindung an die zentrale Auth/User-DB
- Tool-eigene Freischaltung ueber `{{TOOL_USERS_TABLE}}`
- Generisches Admin-Panel fuer Freischaltung, Tool-Admins und Selbstregistrierung
- Tool-DB-Verbindung ueber `TOOL_DB_*`
- Mailversand via native `mail()` oder SMTP
- Dockerfile und CapRover `captain-definition`
- Setup-Anleitungen fuer DKIM, Git, DB und CapRover ENV

## Initiale Anpassung

1. In `scripts/configure-template.ps1` oben `Subdomain`, `Domain` und `ToolName` eintragen.
2. Skript ausfuehren:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\configure-template.ps1
```

Das Skript ersetzt die Platzhalter in:

- `README.md`
- `.env.example`
- `setup.sql`
- `X DKIM_Setup.md`
- `X GIT_Setup.md`
- `X DB_Setup.md`
- `X CapRover_ENV_Setup.md`
- `X overview_user-management.md`
- `images/site.webmanifest`

## Danach

1. `setup.sql` in der Tool-DB und der zentralen Auth/User-DB ausfuehren.
2. `.env.example` in CapRover als Environment Variables uebernehmen und Secrets eintragen.
3. DKIM nach `X DKIM_Setup.md` einrichten.
4. Git nach `X GIT_Setup.md` initialisieren.
5. Fachlichen Tool-Code in `index.php`, optional in `api/` und in eigenen Includes ergaenzen.

## Wichtige ENV-Variablen

| Variable | Beschreibung |
|---|---|
| `APP_NAME` | Anzeigename des Tools |
| `APP_BASE_URL` | Oeffentliche URL, z. B. `https://{{FULL_DOMAIN}}` |
| `USER_MANAGEMENT_URL` | Zentrales Konto-/User-Tool |
| `AUTH_DB_*` | Zentrale Auth/User-DB |
| `TOOL_DB_*` | Tool-eigene Datenbank |
| `TOOL_USERS_TABLE` | Tool-Freischaltungstabelle in der Auth-DB |
| `TOOL_ADMIN_COLUMN` | Admin-Spalte in der Tool-Freischaltungstabelle |
| `SMTP_*`, `MAIL_FROM`, `MAIL_FROM_NAME` | Mailversand |

## Test-Checkliste

1. CapRover ENV Variablen setzen.
2. `setup.sql` in beiden Datenbanken ausfuehren.
3. App deployen.
4. Registrierung und Login testen.
5. User im Admin-Panel freischalten/deaktivieren.
6. Tool-Admin setzen und Admin-Zugriff pruefen.
7. Passwort-Reset und Login-Code-Mail testen.
