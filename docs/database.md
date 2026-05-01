# Datenbank und Migrationen

MyTime verwendet CakePHP ORM und `cakephp/migrations`. Fuer neue Installationen
reichen die normalen Migrationen. Fuer eine bereits existierende Produktionsdatenbank
gibt es ein separates, idempotent gehaltenes MySQL-Upgrade-Script.

## Tabellen

### `bookings`

Speichert Zeitbuchungen.

Wichtige Felder:

- `id`
- `bookingdate`
- `ticket`
- `bookingpsp`
- `description`
- `minutes`
- `kunde`
- `mandant_id`
- `user_id`
- `group_id`
- `created`
- `modified`

Beziehungen:

- gehoert zu `mandanten`
- gehoert zu `users`
- gehoert zu `groups`

### `mandanten`

Mandanten/Kundenkontexte fuer Buchungen.

Wichtige Felder:

- `id`
- `name`
- `created`
- `modified`

Beziehung:

- hat viele `bookings`

### `users`

Lokale Benutzer, die aus OIDC-Logins entstehen oder durch Admins gepflegt werden.

Wichtige Felder:

- `id`
- `username`
- `email`
- `group_id`
- `is_admin`
- `created`
- `modified`

Beziehungen:

- gehoert zu `groups`
- hat viele `bookings`

### `groups`

Gruppen teilen Buchungen zwischen mehreren Usern.

Wichtige Felder:

- `id`
- `name`
- `created`
- `modified`

Beziehungen:

- hat viele `users`
- hat viele `bookings`

## Neue Installation

```bash
bin/cake migrations migrate
bin/cake migrations status
```

Danach sollten alle Migrationen als `up` angezeigt werden:

- `20260428193000_CreateUsers`
- `20260428193100_CreateBookings`
- `20260428210000_CreateMandantenAndLinkBookings`
- `20260429030000_AddUserIdToBookings`
- `20260429032000_AddGroupsForSharedBookings`

## Produktionsdatenbank upgraden

Fuer die echte MySQL-Datenbank liegt das Upgrade-Script hier:

```text
config/Migrations/mysql_production_upgrade_20260501.sql
```

Empfohlener Ablauf:

1. Datenbankdump erstellen.
2. Script zuerst gegen eine Kopie testen.
3. Anwendung stoppen oder Wartungsfenster nutzen.
4. Script gegen Produktion ausfuehren.
5. Migration-Status, Tabellen und Referenzen pruefen.
6. Schema-Cache leeren und App neu starten.

Beispiel:

```bash
mysqldump -u <user> -p <database> > backup-before-mytime-upgrade.sql
mysql -u <user> -p <database> < config/Migrations/mysql_production_upgrade_20260501.sql
bin/cake migrations status
bin/cake schema_cache clear
bin/cake cache clear_all
```

Das Script legt Backup-Tabellen an:

- `bookings_backup_20260501`
- `users_backup_20260501`

Diese Tabellen sollten erst nach erfolgreicher fachlicher Abnahme entfernt
werden.

## Pruefqueries nach Migration

```sql
SELECT COUNT(*) AS bookings FROM bookings;
SELECT COUNT(*) AS users FROM users;
SELECT COUNT(*) AS mandanten FROM mandanten;
SELECT COUNT(*) AS `groups` FROM `groups`;
```

Fehlende Referenzen pruefen:

```sql
SELECT COUNT(*) AS missing_booking_refs
FROM bookings b
LEFT JOIN users u ON u.id = b.user_id
LEFT JOIN `groups` g ON g.id = b.group_id
LEFT JOIN mandanten m ON m.id = b.mandant_id
WHERE u.id IS NULL OR g.id IS NULL OR m.id IS NULL;

SELECT COUNT(*) AS missing_user_groups
FROM users u
LEFT JOIN `groups` g ON g.id = u.group_id
WHERE g.id IS NULL;
```

Beide Werte sollten `0` sein.

## MySQL Hinweise

- MySQL 9 behandelt `groups` als problematischen Namen. Deshalb muessen
  Identifier gequotet werden.
- In `config/app_local.php` ist `DATABASE_QUOTE_IDENTIFIERS=true` vorgesehen.
- Foreign-Key-Spalten sind fuer MySQL signed angelegt, passend zu CakePHPs
  Standard-Integer-Primary-Keys.
- Nach Schema-Aenderungen immer den CakePHP Schema Cache leeren.

## Schema Cache

```bash
bin/cake schema_cache clear
bin/cake cache clear_all
```

Wenn nach einer Migration weiterhin alte Spaltenfehler erscheinen, laeuft oft
noch ein alter Serverprozess oder ein alter Schema-Cache.
