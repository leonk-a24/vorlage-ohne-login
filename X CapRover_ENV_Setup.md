# CapRover ENV Setup fuer {{TOOL_NAME}}

App-URL: `{{APP_BASE_URL}}`

## 1. Environment Variables eintragen

In CapRover:

`Apps -> {{SUBDOMAIN}} -> App Configs -> Environmental Variables`

Die Werte aus `.env.example` uebernehmen und die Secrets/Hosts ergaenzen.

## 2. Pflichtwerte

| Variable | Wert |
|---|---|
| `APP_NAME` | `{{TOOL_NAME}}` |
| `TOOL_SLUG` | `{{TOOL_SLUG}}` |
| `APP_BASE_URL` | `{{APP_BASE_URL}}` |
| `USER_MANAGEMENT_URL` | `{{USER_MANAGEMENT_URL}}` |
| `AUTH_DB_NAME` | `{{AUTH_DB_NAME}}` |
| `TOOL_DB_NAME` | `{{TOOL_DB_NAME}}` |
| `TOOL_USERS_TABLE` | `{{TOOL_USERS_TABLE}}` |
| `TOOL_ADMIN_COLUMN` | `{{TOOL_ADMIN_COLUMN}}` |

## 3. DB-Zugaenge

```env
AUTH_DB_HOST=<auth-db-hostname>
AUTH_DB_PORT=3306
AUTH_DB_NAME={{AUTH_DB_NAME}}
AUTH_DB_USER=<auth-db-user>
AUTH_DB_PASS=<auth-db-password>

TOOL_DB_HOST=<tool-db-hostname>
TOOL_DB_PORT=3306
TOOL_DB_NAME={{TOOL_DB_NAME}}
TOOL_DB_USER=<tool-db-user>
TOOL_DB_PASS=<tool-db-password>
```

## 4. Mail

```env
MAIL_METHOD=smtp
SMTP_HOST=<smtp-host>
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_AUTH=true
SMTP_USERNAME=<smtp-user>
SMTP_PASSWORD=<smtp-password>
MAIL_FROM=no-reply@{{FULL_DOMAIN}}
MAIL_FROM_NAME={{TOOL_NAME}}
```

## 5. Optional: Turnstile

Leer lassen, wenn kein Bot-Schutz fuer Registrierung genutzt werden soll.

```env
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

## 6. Deploy

Nach dem Setzen der ENV Variablen die App neu deployen oder neu starten.
