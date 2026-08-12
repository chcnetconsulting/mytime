# REST-API

Zusätzlich zur Weboberfläche (OIDC-Login) stellt MyTime eine kleine REST-API bereit.
Sie dient dazu, die **Monats-Timesheet-PDF** programmatisch abzurufen und je Monat ein
**Approval** (z. B. die „Approved"-Mail des Kunden als PDF) abzulegen und wieder
herunterzuladen — damit im Zweifel Timesheet **und** Freigabe vorweisbar sind.

## Authentifizierung

Die API nutzt **nicht** die OIDC-Session, sondern einen statischen Bearer-Token:

```
Authorization: Bearer <MYTIME_API_TOKEN>
```

Der Token wird über die Umgebungsvariable `MYTIME_API_TOKEN` gesetzt (Secret, siehe
`config/.env.example` bzw. `k8s/02-secret.example.yaml`). Ist er leer/ungesetzt, ist die
**gesamte API deaktiviert** und liefert `404`.

Die API handelt fest als **Owner-Account** — der Benutzer aus `OIDC_OWNER_EMAIL`. Es gibt
keine Nutzerumschaltung; es geht ausschließlich um die eigenen Timesheets.

## Endpunkte

Optionaler Query-Parameter `mandant_id` grenzt überall auf einen Mandanten ein.

| Methode | Pfad | Zweck |
|---|---|---|
| `GET` | `/api/timesheet/{year}/{month}` | Monats-Timesheet als PDF (identisch zum Web-Export) |
| `GET` | `/api/approval/{year}/{month}` | gespeichertes Approval-PDF herunterladen |
| `POST` | `/api/approval/{year}/{month}` | Approval-PDF ablegen (Upsert je Monat) |
| `GET` | `/api/approvals` | JSON-Liste aller hinterlegten Approvals (ohne Dateiinhalt) |

Approvals werden als **BLOB in der Datenbank** gespeichert (die App hat im Cluster kein
persistentes Dateivolume). Es gibt genau **ein** Approval je `(User, Mandant, Monat)`;
ein erneuter Upload ersetzt das vorhandene.

## Beispiele

```bash
TOKEN=…                      # = MYTIME_API_TOKEN
BASE=https://mytime.chcnet.at

# Timesheet Juli 2026 als PDF
curl -sf -H "Authorization: Bearer $TOKEN" \
     "$BASE/api/timesheet/2026/7" -o timesheet_2026_07.pdf

# Approval (Kunden-Freigabe als PDF) für Juli 2026 ablegen
curl -sf -X POST -H "Authorization: Bearer $TOKEN" \
     -H "X-Filename: approval_2026_07.pdf" \
     --data-binary @amin-approved.pdf \
     "$BASE/api/approval/2026/7"

# … oder als klassischer Datei-Upload (Feld: file)
curl -sf -X POST -H "Authorization: Bearer $TOKEN" \
     -F "file=@amin-approved.pdf" \
     "$BASE/api/approval/2026/7"

# Approval wieder herunterladen
curl -sf -H "Authorization: Bearer $TOKEN" \
     "$BASE/api/approval/2026/7" -o approval_2026_07.pdf

# Überblick
curl -sf -H "Authorization: Bearer $TOKEN" "$BASE/api/approvals"
```

Nur echte PDFs werden angenommen (Prüfung auf `%PDF-`), maximal 16 MB.

## Weboberfläche

Dieselben Approvals lassen sich auch auf der Startseite hochladen und herunterladen:
je Monatszeile gibt es ein Upload-Feld und — sobald vorhanden — einen
`✅ Approval`-Download-Link (Session-Auth, CSRF-geschützt).
