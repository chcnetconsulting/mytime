# Kubernetes Deployment

Diese Manifeste deployen MyTime in den Namespace `mytime`.

## Komponenten

- `00-namespace.yaml`: Namespace `mytime`
- `01-configmap.yaml`: nicht geheime App-Konfiguration
- `02-secret.example.yaml`: Secret-Template, vor Nutzung kopieren/anpassen
- `03-nginx-config.yaml`: Nginx HTTP-Frontend fuer PHP-FPM
- `04-deployment.yaml`: PHP-FPM App plus Nginx Sidecar
- `05-service.yaml`: ClusterIP Service fuer Traefik
- `06-migration-job.yaml`: einmaliger CakePHP Migration Job
- `07-certificate.yaml`: cert-manager Certificate mit ClusterIssuer `le`
- `08-ingressroute.yaml`: Traefik IngressRoute auf `websecure`
- `09-mysql.yaml`: **Auslaufmodell** — das alte lokale MySQL. Bleibt bis zum
  Abbau als Rückweg stehen, die Anwendung spricht es nicht mehr an.
- `10-import-job.yaml`: **einmalig** — Übernahme der Nutzdaten MySQL → pgsql92
- `11-db-secret.example.yaml`: Vorlage für das Secret `mytime-db` (Passwort
  für pgsql92, aus dem Operator-Secret kopiert)

## Datenbank

Die Anwendung nutzt seit Image 2.2.0 den gemeinsamen PostgreSQL-Cluster
**pgsql92** im Namespace `default` (Zalando-Operator, PostgreSQL 17) statt
eines eigenen MySQL. Datenbank und Rolle heißen `mytime` und sind im CRD
`~/dev/infra/k8s/pgsql92/postgresql-cr.yaml` hinterlegt.

Verbindungswerte stehen in `01-configmap.yaml`, das Passwort im eigenen Secret
`mytime-db`. Dieses wird **imperativ** aus dem Secret gesetzt, das der Operator
erzeugt — Secrets gelten nur im eigenen Namespace, und
`enable_cross_namespace_secret` ist aus:

```bash
PW=$(kubectl -n default get secret \
       mytime.pgsql92.credentials.postgresql.acid.zalan.do \
       -o jsonpath='{.data.password}' | base64 -d)
kubectl -n mytime create secret generic mytime-db --from-literal=DB_PASSWORD="$PW"
```

⚠ **TLS ist Pflicht, nicht Kür.** Spilo setzt in `pg_hba.conf`
`hostnossl … reject` und `hostssl … md5`; ohne Verschlüsselung kommt gar keine
Verbindung zustande. Deshalb `DB_SSL=1` und `DB_SSLMODE=require` in der
ConfigMap — aber bewusst nicht `verify-*`: das Serverzertifikat ist
selbstsigniert und wird bei jedem Start neu erzeugt.

⚠ Nach jeder Änderung an ConfigMap oder Secret ist ein Neustart nötig,
`envFrom` wird im laufenden Betrieb nicht nachgezogen:

```bash
kubectl -n mytime rollout restart deploy/mytime
```

Gesichert wird die Datenbank vom nächtlichen CronJob `db-backup` (ns `backup`)
als `pgsql92-mytime.dump` — ohne Zutun, der Job zieht alle Datenbanken des
Clusters. Der frühere Eintrag `dump_mysql mytime …` in
`~/dev/infra/k8s/db-backup/backup.sh` muss beim Abbau von mysql-0 entfernt
werden, sonst meldet der Job jede Nacht einen Fehler.

## Vor Deployment anpassen

In allen Dateien `mytime.chcnet.at` ersetzen.

Das Image kommt aus der privaten Cluster-Registry
`registry.chcnetconsulting.com` (Push) bzw. cluster-intern `localhost:32000`
(Pull ohne Auth). In `04-deployment.yaml` und `06-migration-job.yaml` ist das
Image entsprechend gesetzt:

```text
localhost:32000/mytime:<tag>
```

Neues Image bauen und pushen (Prod = amd64):

```bash
docker buildx build --platform linux/amd64 \
  -t registry.chcnetconsulting.com/mytime:<tag> --push .
```

In `01-configmap.yaml` muss `ENTRA_REDIRECT_URI` zur echten Domain passen:

```text
https://<dein-host>/auth/callback
```

Secret erstellen:

```bash
cp k8s/02-secret.example.yaml k8s/02-secret.yaml
```

Dann `k8s/02-secret.yaml` mit echten Werten fuellen. Diese Datei nicht committen.

## Anwenden

```bash
kubectl apply -f k8s/00-namespace.yaml
kubectl apply -f k8s/01-configmap.yaml
kubectl apply -f k8s/02-secret.yaml
kubectl apply -f k8s/03-nginx-config.yaml
kubectl apply -f k8s/07-certificate.yaml
kubectl apply -f k8s/04-deployment.yaml
kubectl apply -f k8s/05-service.yaml
kubectl apply -f k8s/08-ingressroute.yaml
```

Migrationen ausfuehren:

```bash
kubectl apply -f k8s/06-migration-job.yaml
kubectl -n mytime logs job/mytime-migrations -f
```

Bei erneutem Migration-Lauf den alten Job vorher loeschen:

```bash
kubectl -n mytime delete job mytime-migrations
kubectl apply -f k8s/06-migration-job.yaml
```

## Pruefen

```bash
kubectl -n mytime get pods
kubectl -n mytime get svc
kubectl -n mytime get certificate
kubectl -n mytime get ingressroute
kubectl -n mytime logs deploy/mytime -c php-fpm
kubectl -n mytime logs deploy/mytime -c nginx
```

## Warum Nginx Sidecar?

Traefik spricht HTTP. PHP-FPM spricht FastCGI auf Port 9000. Deshalb routet
Traefik zum Nginx-Sidecar, und Nginx leitet PHP-Anfragen intern an PHP-FPM
weiter.
