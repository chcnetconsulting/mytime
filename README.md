# MyTime

MyTime ist eine interne Zeiterfassungsanwendung auf Basis von CakePHP 5.3.
Die Anwendung verwaltet Buchungen pro Ticket, PSP, Mandant, Benutzer und Gruppe.
Benutzer melden sich per OpenID Connect an; Microsoft Entra ID ist bewusst die
erste und standardmaessige Konfiguration, kann aber gegen Keycloak, Google oder
einen generischen OIDC-Provider getauscht werden.

## Funktionen

- Buchungen anlegen, bearbeiten, suchen und loeschen
- Ticket-Lookup beim Anlegen: vorhandene Ticketdaten werden als Vorschlag geladen
- PSP-Auswahl aus bestehenden Buchungen in Add- und Edit-Dialog
- Mandanten-CRUD und Mandanten-Auswahl in Buchungen
- Benutzer- und Gruppenverwaltung fuer gemeinsam bearbeitbare Buchungen
- Admin-Rechte per Entra-Besitzer-E-Mail oder `is_admin`
- OIDC-Login mit Entra, Keycloak, Google oder Custom Provider
- Monats-Export als XLSX
- Migrationen fuer neue Installationen und ein separates Produktions-Upgrade-Script

## Dokumentation

- [Konfiguration](docs/configuration.md)
- [Authentifizierung und Berechtigungen](docs/authentication.md)
- [Datenbank und Migrationen](docs/database.md)
- [Tests](docs/testing.md)
- [Entwicklung](docs/development.md)
- [Betrieb und Troubleshooting](docs/operations.md)
- [Kubernetes Deployment](k8s/README.md)

## Schnellstart lokal

```bash
composer install
cp config/.env.example config/.env
bin/cake migrations migrate
bin/cake server -p 8765
```

Danach ist die Anwendung unter `http://localhost:8765/` erreichbar.

Fuer lokale Smoke-Tests ohne OIDC kann Auth temporaer deaktiviert werden:

```bash
MYTIME_AUTH_DISABLED=true bin/cake server -p 8765
```

Diese Einstellung ist nur fuer lokale Entwicklung und Tests gedacht.

## Wichtige URLs

- `/` oder `/home`: Uebersicht
- `/bookings`: Buchungen
- `/bookings/add`: Buchung anlegen
- `/bookings/ticket-lookup?ticket=<ticket>`: JSON-Ticket-Lookup
- `/bookings/genxls/<jahr>/<monat>`: XLSX-Export
- `/mandanten`: Mandanten
- `/users`: Benutzerverwaltung, nur Admins
- `/groups`: Gruppenverwaltung, nur Admins
- `/auth/login`: OIDC-Login
- `/auth/callback`: OIDC-Redirect-URI
- `/auth/logout`: Logout

## Standard-Kommandos

```bash
vendor/bin/phpunit --colors=never
bin/cake migrations status
bin/cake migrations migrate
bin/cake schema_cache clear
bin/cake cache clear_all
```

## Architektur kurz

MyTime nutzt klassische CakePHP-Controller, ORM-Tabellen und Templates.
Die Authentifizierung liegt in `AuthController`; der Provider wird ueber
`config/app_local.php` und Umgebungsvariablen gewaehlt. Die Zugriffskontrolle
sitzt zentral in `AppController` und in den Admin-Controllern.

Buchungen gehoeren einem User, einer Gruppe und einem Mandanten. Normale User
sehen die Buchungen ihrer Gruppe; falls kein Gruppenbezug vorhanden ist, wird
auf eigene Buchungen eingeschraenkt. Admins koennen User und Gruppen verwalten
und haben Zugriff auf alle Buchungen.
