# Tests

Die Anwendung hat PHPUnit-Tests fuer Controller, Tabellen und Auth-Flows.
Standardmaessig laufen Tests gegen SQLite. Zusaetzlich kann gegen MySQL getestet
werden, insbesondere fuer Migrationen und MySQL-spezifische Identifier-Themen.

## Standard-Testlauf

```bash
vendor/bin/phpunit --colors=never
```

Alternativ ueber Composer:

```bash
composer test
```

## MySQL-Testdatenbank

Fuer lokale MySQL-Tests wird eine separate Datenbank verwendet, zum Beispiel
`mytimetests`. Die Produktionsdatenbank darf nicht als Testdatenbank genutzt
werden.

Beispiel:

```bash
DATABASE_TEST_HOST=127.0.0.1 \
DATABASE_TEST_USER=mytime \
DATABASE_TEST_PASS='<password>' \
DATABASE_TEST_NAME=mytimetests \
DATABASE_TEST_ENCODING=utf8mb4 \
DATABASE_TEST_QUOTE_IDENTIFIERS=true \
vendor/bin/phpunit --colors=never
```

Wenn `DATABASE_TEST_HOST` gesetzt ist, nutzt CakePHP die MySQL-Testkonfiguration.
Ohne diese Variable faellt die Test-Datasource auf SQLite unter `tmp/tests.sqlite`
zurueck.

## Migrationen testen

Fuer neue Installationen:

```bash
bin/cake migrations migrate
bin/cake migrations status
```

Fuer das Produktions-Upgrade:

```bash
mysql -u <user> -p mytimetests < production-dump.sql
mysql -u <user> -p mytimetests < config/Migrations/mysql_production_upgrade_20260501.sql
bin/cake migrations status
```

Danach die Pruefqueries aus [Datenbank und Migrationen](database.md) ausfuehren.

## Lokaler Smoke-Test

Mit echter lokaler Datenbank, aber ohne OIDC:

```bash
MYTIME_AUTH_DISABLED=true bin/cake server -p 8765
```

Dann im Browser pruefen:

- `http://localhost:8765/bookings`
- `http://localhost:8765/bookings/add`
- `http://localhost:8765/bookings/ticket-lookup?ticket=<ticket>`
- `http://localhost:8765/users`
- `http://localhost:8765/groups`
- `http://localhost:8765/mandanten`

Wieder in normalem Auth-Modus starten:

```bash
bin/cake server -p 8765
```

## Erwartete Testabdeckung

Vorhandene Tests decken ab:

- OIDC Login-Redirect fuer Entra, Keycloak und Google
- OIDC Callback-Validierung
- Bookings CRUD und Zugriffsscope
- Ticket-Lookup
- Mandanten CRUD
- User CRUD inklusive optionaler Admin-Checkbox
- Gruppen CRUD und Admin-Schutz
- Tabellenvalidierung und Beziehungen

Bei Aenderungen an Auth, Sichtbarkeit oder Migrationen sollten sowohl PHPUnit
als auch ein MySQL-Smoke-Test laufen.
