# Entwicklung

Dieses Kapitel beschreibt, wo zentrale Funktionen im Code liegen und welche
Konventionen bei Erweiterungen beachtet werden sollten.

## Projektstruktur

- `src/Controller`: Controller fuer Auth, Bookings, Mandanten, Users, Groups
- `src/Model/Table`: ORM-Tabellen, Validierung, Beziehungen und Rules
- `src/Model/Entity`: Mass-Assignment und Entity-Felder
- `templates`: CakePHP Templates fuer UI und Formulare
- `config/Migrations`: normale CakePHP-Migrationen und Produktionsscript
- `tests/TestCase`: PHPUnit-Tests
- `tests/Fixture`: Testdaten

## Zentrale Dateien

### Auth

- `src/Controller/AuthController.php`
- `src/Controller/AppController.php`
- `config/app_local.php`

`AuthController` kapselt OIDC Provider, Token Exchange, Signaturpruefung und
User-Anlage. `AppController` erzwingt Login, liest den aktuellen User und
entscheidet Admin-Status.

### Buchungen

- `src/Controller/BookingsController.php`
- `src/Model/Table/BookingsTable.php`
- `templates/Bookings/add.php`
- `templates/Bookings/edit.php`
- `templates/Bookings/index.php`

Die Sichtbarkeit von Buchungen wird in `bookingScopeConditions()` zentral
berechnet. Neue Queries fuer Buchungen sollten diese Methode verwenden, damit
Gruppen- und User-Scope erhalten bleiben.

### User und Gruppen

- `src/Controller/UsersController.php`
- `src/Controller/GroupsController.php`
- `src/Model/Table/UsersTable.php`
- `src/Model/Table/GroupsTable.php`

User- und Gruppenverwaltung ist Admins vorbehalten. `is_admin` ist optional und
wird bei fehlendem Checkbox-Wert auf `false` gesetzt.

### Mandanten

- `src/Controller/MandantenController.php`
- `src/Model/Table/MandantenTable.php`
- `templates/Mandanten/*`

Mandanten sind eigenstaendige Stammdaten und werden in Buchungen ueber
`mandant_id` referenziert.

## UI-Konventionen

- CakePHP FormHelper verwenden.
- Select2 ist im Standardlayout eingebunden.
- Mandant und PSP werden in Buchungsformularen als Auswahlfelder dargestellt.
- Die Admin-Option in User-Formularen bleibt eine Checkbox.
- Felder, die fachlich optional sind, muessen serverseitig Defaultwerte erhalten,
  falls HTML-Checkboxen oder alte Formulare keinen Wert senden.

## Neue Migrationen

Neue Strukturveraenderungen sollten als CakePHP-Migration in `config/Migrations`
angelegt werden. Fuer bestehende Produktionstabellen muss zusaetzlich geprueft
werden, ob `mysql_production_upgrade_20260501.sql` erweitert oder ein neues
Produktionsscript erstellt werden muss.

Nach Migrationen:

```bash
bin/cake migrations migrate
bin/cake schema_cache clear
bin/cake cache clear_all
vendor/bin/phpunit --colors=never
```

## Tests erweitern

Bei neuen Features mindestens diese Ebenen pruefen:

- Table-Test fuer Validierung und Rules
- Controller-Test fuer HTTP-Verhalten
- Auth-/Scope-Test, wenn User-, Gruppen- oder Adminlogik betroffen ist
- MySQL-Testlauf, wenn Migrationen oder SQL-Details betroffen sind

## Coding-Hinweise

- Bestehende CakePHP-Patterns bevorzugen.
- Keine Secrets in Code, Tests oder Doku.
- Fuer Datenbank-Queries ORM und Query Builder bevorzugen.
- Raw SQL nur fuer Migrationen oder MySQL-spezifische Sonderfaelle.
- Bei Buchungsabfragen immer auf Gruppen-/User-Scope achten.
