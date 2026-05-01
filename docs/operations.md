# Betrieb und Troubleshooting

Dieses Dokument beschreibt die wichtigsten Betriebsablaeufe fuer MyTime.

## Lokaler Server

```bash
bin/cake server -p 8765
```

Die Anwendung ist dann unter `http://localhost:8765/` erreichbar.

Wenn bereits ein Server auf dem Port laeuft, zuerst den alten Prozess beenden
oder einen anderen Port verwenden.

## Deployment-Checkliste

1. Code deployen.
2. Umgebungsvariablen setzen.
3. Datenbankmigrationen ausfuehren.
4. Schema- und App-Cache leeren.
5. PHP/Webserver neu starten.
6. Login und zentrale Seiten testen.

Kommandos:

```bash
composer install --no-dev --optimize-autoloader
bin/cake migrations migrate
bin/cake schema_cache clear
bin/cake cache clear_all
```

## Produktives Datenbank-Upgrade

Bei bestehenden MyTime-Datenbanken das Produktionsscript verwenden:

```bash
mysqldump -u <user> -p <database> > backup-before-mytime-upgrade.sql
mysql -u <user> -p <database> < config/Migrations/mysql_production_upgrade_20260501.sql
bin/cake migrations status
bin/cake schema_cache clear
bin/cake cache clear_all
```

Erst nach erfolgreichem fachlichem Test sollten die Backup-Tabellen aus dem
Script entfernt werden.

## Logs

Wichtige Dateien:

- `logs/error.log`
- `logs/debug.log`

Bei HTTP 500 zuerst `logs/error.log` lesen.

## Haefige Fehler

### Redirect URI mismatch

Fehler:

```text
AADSTS50011
```

Ursache: Die Redirect URI in Entra stimmt nicht exakt mit der Anfrage ueberein.
Beide Seiten muessen identisch sein, inklusive Protokoll, Host, Port und Pfad.

Lokal:

```text
http://localhost:8765/auth/callback
```

### Invalid client secret

Fehler:

```text
AADSTS7000215
```

Ursache: Es wurde die Secret-ID statt des Secret-Werts konfiguriert oder der
Secret-Wert ist abgelaufen. In Entra einen neuen Client Secret erzeugen und den
Wert sofort kopieren.

### `/auth/callback` nicht gefunden

Moegliche Ursachen:

- CakePHP-Server laeuft nicht auf dem erwarteten Port.
- Redirect URI zeigt auf einen anderen Port oder Host.
- Reverse Proxy leitet den Pfad nicht an CakePHP weiter.

Pruefen:

```bash
bin/cake server -p 8765
```

### Internal Error nach Login

Moegliche Ursachen:

- Datenbankschema ist nicht migriert.
- User kann wegen fehlender Gruppe nicht gespeichert werden.
- Schema-Cache ist alt.
- OIDC Claims enthalten keine nutzbare E-Mail.

Pruefen:

```bash
bin/cake migrations status
bin/cake schema_cache clear
bin/cake cache clear_all
```

Danach `logs/error.log` lesen.

### `Unknown column Users.created`

Die Datenbank wurde noch nicht auf die neue Struktur gebracht oder der
Schema-Cache ist veraltet.

Loesung:

```bash
mysql -u <user> -p <database> < config/Migrations/mysql_production_upgrade_20260501.sql
bin/cake schema_cache clear
bin/cake cache clear_all
```

### MySQL-Fehler bei `groups`

`groups` kann in modernen MySQL-Versionen problematisch sein. In der App sind
Identifier-Quotes vorgesehen.

Pruefen:

```bash
DATABASE_QUOTE_IDENTIFIERS=true
DATABASE_TEST_QUOTE_IDENTIFIERS=true
```

### Tests greifen auf falsche Datenbank zu

Fuer Tests immer `mytimetests` oder eine andere separate Testdatenbank verwenden.
Nie die echte Datenbank als Testdatenbank setzen.

```bash
DATABASE_TEST_NAME=mytimetests
```

## Health Smoke-Test

Nach Deployment oder Migration:

1. `/auth/login` oeffnen und OIDC Login pruefen.
2. `/bookings` oeffnen.
3. Eine Buchung anlegen.
4. Dieselbe Buchung bearbeiten.
5. Ticket-Lookup mit bekanntem Ticket pruefen.
6. XLSX Export fuer einen Monat erzeugen.
7. Als Admin `/users` und `/groups` oeffnen.
8. Mit zweitem User pruefen, ob Gruppenbuchungen sichtbar und bearbeitbar sind.
