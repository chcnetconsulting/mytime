# Konfiguration

Die Anwendung liest lokale Einstellungen aus `config/app_local.php` und aus
Umgebungsvariablen. Fuer lokale Entwicklung kann `config/.env.example` nach
`config/.env` kopiert werden. Secrets gehoeren nicht ins Git.

## Basis

Wichtige allgemeine Variablen:

```bash
APP_NAME="MyTime"
DEBUG="true"
APP_DEFAULT_TIMEZONE="Europe/Vienna"
SECURITY_SALT="<lange-zufaellige-zeichenkette>"
```

In Produktion sollte `DEBUG=false` gesetzt sein.

## Datenbank

Die Datenbank ist **PostgreSQL**. Die Standard-Datasource nutzt bevorzugt
einzelne Variablen:

```bash
DB_HOST="127.0.0.1"
DB_PORT="5432"
DB_USERNAME="mytime"
DB_PASSWORD="<password>"
DB_DATABASE="mytime"
DB_SCHEMA="public"
DB_SSL="0"
DB_SSLMODE="prefer"
DATABASE_QUOTE_IDENTIFIERS="true"
DATABASE_URL=""
```

`DATABASE_URL` (`postgres://…`) kann alternativ verwendet werden und hat in
CakePHP Vorrang, wenn beide Wege gesetzt sind.

`DATABASE_QUOTE_IDENTIFIERS=true` bleibt sinnvoll: `groups` ist auch in
PostgreSQL kein unproblematischer Name.

⚠ **Im Cluster ist `DB_SSL=1` und `DB_SSLMODE=require` Pflicht** — Spilo
(der Zalando-Operator) lehnt unverschlüsselte TCP-Verbindungen in `pg_hba.conf`
ausdrücklich ab. Bewusst `require` und nicht `verify-ca`/`verify-full`: das
Serverzertifikat ist selbstsigniert und wird bei jedem Start neu erzeugt.

Die Zeichenkodierung ist fest `utf8` und **nicht** konfigurierbar — `utf8mb4`
würde PostgreSQL beim `SET NAMES` mit *invalid value for parameter
"client_encoding"* abweisen.

## Auth lokal deaktivieren

Fuer lokale Smoke-Tests kann Auth deaktiviert werden:

```bash
MYTIME_AUTH_DISABLED=true
```

Dann werden Controller ohne OIDC-Login erreichbar. Das ist nur fuer Entwicklung
und lokale Tests gedacht und darf in Produktion nicht aktiv sein.

## OIDC Provider

Der aktive Provider wird ueber `OIDC_PROVIDER` gewaehlt:

```bash
OIDC_PROVIDER="entra"
```

Unterstuetzte Werte:

- `entra`: Microsoft Entra ID, Standard
- `keycloak`: Keycloak Realm
- `google`: Google OpenID Connect
- `custom`: generischer OIDC Provider mit expliziten Endpoints

Alle Provider verwenden standardmaessig die Redirect-URI:

```bash
http://localhost:8765/auth/callback
```

In Produktion muss diese URL auf die echte HTTPS-Domain zeigen, zum Beispiel:

```bash
https://mytime.example.com/auth/callback
```

Die URL muss exakt im jeweiligen Provider hinterlegt sein.

## Microsoft Entra ID

Entra ist die erste und empfohlene Standardkonfiguration.

```bash
OIDC_PROVIDER="entra"
ENTRA_TENANT_ID="<tenant-id>"
ENTRA_CLIENT_ID="<application-client-id>"
ENTRA_CLIENT_SECRET="<client-secret-value>"
ENTRA_REDIRECT_URI="http://localhost:8765/auth/callback"
ENTRA_SCOPE="openid profile email"
OIDC_OWNER_EMAIL="<owner@example.com>"
```

Wichtig: Bei `ENTRA_CLIENT_SECRET` muss der Secret-Wert verwendet werden, nicht
die Secret-ID.

Entra Admin Center:

1. Identity -> Applications -> App registrations
2. App auswaehlen
3. Authentication -> Platform Web
4. Redirect URI eintragen, zum Beispiel `http://localhost:8765/auth/callback`
5. Certificates & secrets -> Client secret erzeugen
6. Den Secret-Wert sofort kopieren und in der Umgebung setzen

## Keycloak

```bash
OIDC_PROVIDER="keycloak"
KEYCLOAK_ISSUER="https://keycloak.example.com/realms/mytime"
KEYCLOAK_CLIENT_ID="<client-id>"
KEYCLOAK_CLIENT_SECRET="<client-secret>"
KEYCLOAK_REDIRECT_URI="http://localhost:8765/auth/callback"
KEYCLOAK_SCOPE="openid profile email"
```

Aus `KEYCLOAK_ISSUER` werden automatisch die Auth-, Token-, Certs- und
Logout-Endpoints abgeleitet.

## Google

```bash
OIDC_PROVIDER="google"
GOOGLE_CLIENT_ID="<client-id>"
GOOGLE_CLIENT_SECRET="<client-secret>"
GOOGLE_REDIRECT_URI="http://localhost:8765/auth/callback"
GOOGLE_SCOPE="openid profile email"
```

Die Google-Endpoints sind fest in der Anwendung hinterlegt.

## Custom OIDC

```bash
OIDC_PROVIDER="custom"
OIDC_LABEL="OpenID Connect"
OIDC_ISSUER="https://issuer.example.com"
OIDC_CLIENT_ID="<client-id>"
OIDC_CLIENT_SECRET="<client-secret>"
OIDC_REDIRECT_URI="http://localhost:8765/auth/callback"
OIDC_SCOPE="openid profile email"
OIDC_AUTHORIZE_URL="https://issuer.example.com/oauth2/authorize"
OIDC_TOKEN_URL="https://issuer.example.com/oauth2/token"
OIDC_KEYS_URL="https://issuer.example.com/.well-known/jwks.json"
OIDC_LOGOUT_URL="https://issuer.example.com/logout"
```

Fuer `custom` muessen `issuer`, `clientId`, `clientSecret`, `redirectUri`,
`authorizeUrl`, `tokenUrl` und `keysUrl` gesetzt sein.
