# DKIM-Setup fuer {{FULL_DOMAIN}}

Dieses Dokument beschreibt DKIM fuer eine neue Tool-Subdomain.

Werte:

- Subdomain: `{{SUBDOMAIN}}`
- Domain: `{{DOMAIN}}`
- Full Domain: `{{FULL_DOMAIN}}`

## 1. Schluessel generieren

```bash
sudo mkdir -p /etc/opendkim/keys/{{FULL_DOMAIN}}
sudo opendkim-genkey -b 2048 -d {{FULL_DOMAIN}} -D /etc/opendkim/keys/{{FULL_DOMAIN}} -s mail -v
sudo chown opendkim:opendkim /etc/opendkim/keys/{{FULL_DOMAIN}}/mail.private
sudo chmod 600 /etc/opendkim/keys/{{FULL_DOMAIN}}/mail.private
```

DNS-TXT-Wert fuer Cloudflare anzeigen:

```bash
sudo cat /etc/opendkim/keys/{{FULL_DOMAIN}}/mail.txt
```

## 2. signing.table ergaenzen

Datei: `/etc/opendkim/signing.table`

```bash
echo '*@{{FULL_DOMAIN}} mail._domainkey.{{FULL_DOMAIN}}' | sudo tee -a /etc/opendkim/signing.table
sudo tail -5 /etc/opendkim/signing.table
```

## 3. key.table ergaenzen

Datei: `/etc/opendkim/key.table`

```bash
echo 'mail._domainkey.{{FULL_DOMAIN}} {{FULL_DOMAIN}}:mail:/etc/opendkim/keys/{{FULL_DOMAIN}}/mail.private' | sudo tee -a /etc/opendkim/key.table
sudo tail -5 /etc/opendkim/key.table
```

## 4. Dienste neustarten

```bash
sudo systemctl restart opendkim
sudo systemctl restart postfix
sudo systemctl status opendkim postfix --no-pager
```

## 5. DNS-Eintrag in Cloudflare

Zone: `{{DOMAIN}}`

| Typ | Name | Wert |
|---|---|---|
| TXT | `mail._domainkey.{{SUBDOMAIN}}` | `v=DKIM1; h=sha256; k=rsa; p=<PUBLIC_KEY>` |

Den `<PUBLIC_KEY>` aus `mail.txt` entnehmen und alle Teile in Anfuehrungszeichen ohne Leerzeichen zusammenfuehren.

## 6. Schluessel testen

Nach DNS-Propagation:

```bash
sudo opendkim-testkey -d {{FULL_DOMAIN}} -s mail -vvv
```

Erwartete Ausgabe: `key OK`

## Hinweis

Keine inline Heredocs in automatisierten Terminals verwenden. Fuer Tabellenupdates lieber `echo '...' | sudo tee -a ...` nutzen.
