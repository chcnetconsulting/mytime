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

## Vor Deployment anpassen

In allen Dateien `mytime.chcnet.at` ersetzen.

In diesen Dateien das Image ersetzen:

```text
chcnetconsulting/mytime:2.0.0
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
