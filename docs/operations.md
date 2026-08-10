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

Struktur­änderungen laufen als CakePHP-Migration, im Cluster über den
Migration-Job:

```bash
kubectl -n mytime delete job mytime-migrations --ignore-not-found
kubectl apply -f k8s/06-migration-job.yaml
kubectl -n mytime logs -f job/mytime-migrations
```

Vorher sichern (der nächtliche Job legt ohnehin `pgsql92-mytime.dump` ab):

```bash
kubectl -n default exec pgsql92-0 -- \
  pg_dump -U postgres -Fc mytime > mytime-vor-upgrade.dump
```

Zurückspielen im Notfall:

```bash
kubectl -n default exec -i pgsql92-0 -- \
  pg_restore -U postgres -d mytime --clean < mytime-vor-upgrade.dump
```

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

### `column users.created does not exist`

Die Migration ist noch nicht gelaufen oder der Schema-Cache ist veraltet.

```bash
bin/cake migrations status
bin/cake migrations migrate
bin/cake schema_cache clear
bin/cake cache clear_all
```

### `duplicate key value violates unique constraint "…_pkey"`

Die Identity-Sequenz hinkt den vorhandenen IDs hinterher — typisch, nachdem
Daten mit festen IDs eingespielt wurden. PostgreSQL zieht den Zähler dabei
nicht mit, MySQL tat das.

```sql
SELECT setval(pg_get_serial_sequence('bookings', 'id'),
              COALESCE((SELECT MAX(id) FROM bookings), 0) + 1, false);
```

### `pg_hba.conf rejects connection … no encryption`

`DB_SSL`/`DB_SSLMODE` fehlen oder stehen auf `disable`. Spilo nimmt keine
unverschlüsselten Verbindungen an — in der ConfigMap müssen `DB_SSL=1` und
`DB_SSLMODE=require` stehen.

### `invalid value for parameter "client_encoding": "utf8mb4"`

Irgendwo steht noch die MySQL-Kodierung. Richtig ist `utf8`.

### Fehler bei `groups`

`groups` ist auch in PostgreSQL kein unproblematischer Name; Identifier werden
deshalb weiterhin gequotet:

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
