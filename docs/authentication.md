# Authentifizierung und Berechtigungen

MyTime nutzt OpenID Connect mit Authorization-Code-Flow. Nach erfolgreichem
Login wird der Benutzer anhand der Claims aus dem ID Token in der Tabelle
`users` gefunden oder angelegt.

## Login-Ablauf

1. `/auth/login` erzeugt `state` und `nonce`.
2. Die Anwendung leitet zum konfigurierten Provider weiter.
3. Der Provider ruft `/auth/callback` mit `code` und `state` auf.
4. MyTime tauscht den Code gegen Tokens.
5. Die ID-Token-Signatur wird ueber JWKS validiert.
6. Claims wie E-Mail und Name werden gelesen.
7. Der User wird in `users` gefunden oder angelegt.
8. Die Userdaten werden in der Session unter `Auth.User` gespeichert.

## Provider

Der aktive Provider wird ueber `OIDC_PROVIDER` gesetzt. Entra ist Standard.
Weitere Details stehen in [Konfiguration](configuration.md).

## Admin-Rechte

Ein Benutzer gilt als Admin, wenn eine der Bedingungen zutrifft:

- Die E-Mail entspricht `OIDC_OWNER_EMAIL`.
- Das Feld `users.is_admin` ist gesetzt.

Admins koennen User und Gruppen verwalten. Der erste Besitzer-User aus Entra
sollte daher als `OIDC_OWNER_EMAIL` konfiguriert werden. Danach kann er weitere
User in Gruppen einordnen und Admin-Rechte per Checkbox vergeben.

## Gruppen und Sichtbarkeit

Buchungen haben `user_id`, `group_id` und `mandant_id`.

Die Sichtbarkeit wird in dieser Reihenfolge bestimmt:

1. Wenn der eingeloggte User eine `group_id` hat, sieht er Buchungen dieser Gruppe.
2. Ohne Gruppe sieht er nur eigene Buchungen ueber `user_id`.
3. Admins haben Zugriff auf alle Buchungen.
4. Ohne gueltigen User werden keine Buchungen gezeigt.

Bei `MYTIME_AUTH_DISABLED=true` werden ohne Session-User alle Buchungen angezeigt,
damit lokale Smoke-Tests gegen echte Daten moeglich sind.

## User- und Gruppenverwaltung

- `/users`: User anzeigen
- `/users/add`: User anlegen
- `/users/edit/<id>`: User bearbeiten
- `/groups`: Gruppen anzeigen
- `/groups/add`: Gruppe anlegen
- `/groups/edit/<id>`: Gruppe bearbeiten

`is_admin` ist eine optionale Checkbox. Nicht angehakt bedeutet `false`; das
Feld darf leer bleiben.

## Entra Hinweise

Die Redirect URI muss in Entra exakt mit der von MyTime gesendeten URL
uebereinstimmen. Lokal ist das ueblicherweise:

```text
http://localhost:8765/auth/callback
```

Typische Fehler:

- `AADSTS50011`: Redirect URI stimmt nicht exakt.
- `AADSTS7000215`: Bei `ENTRA_CLIENT_SECRET` wurde wahrscheinlich die Secret-ID
  statt des Secret-Werts eingetragen.
- Internal Error nach Callback: `logs/error.log` pruefen, Cache leeren und
  kontrollieren, ob der User gespeichert werden kann.
