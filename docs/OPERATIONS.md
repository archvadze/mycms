# Operational Runbook

This runbook documents the production Laravel CMS operations baseline for `archvadze.com`.

## Production Architecture

- Application path on host: `/opt/mycms/app`
- Docker Compose file: `/opt/mycms/app/compose.yml`
- App container: `mycms-app`
- Database container: `mycms-mariadb`
- MariaDB persistent data path on host: `/opt/mycms/mariadb`
- Compose env files inspected in `compose.yml`: both `app` and `mycms-mariadb` use the same relative env file, `.env.production`, resolved from `/opt/mycms/app` as `/opt/mycms/app/.env.production`
- Host Nginx proxies PHP-FPM to `127.0.0.1:9002`
- App code is bind-mounted into the app container at `/var/www/html`
- Public storage symlink: `public/storage -> storage/app/public`
- Cache/session in production are file-based according to the current production context
- Queue is sync; no queue worker is currently required
- Mail is sent through Resend configuration
- Production tests are not run on the production host

Do not use `docker compose config` in routine operations because it can print environment-derived secrets.

## Routine Health Checks

Run from the production host:

```bash
cd /opt/mycms/app
docker compose ps
docker logs --tail=100 mycms-app
docker logs --tail=100 mycms-mariadb
df -h
du -sh /opt/mycms/app/storage /opt/mycms/mariadb
docker system df
```

Run inside the app container:

```bash
cd /opt/mycms/app
docker exec mycms-app php artisan about
docker exec mycms-app php artisan route:list --except-vendor
```

HTTP smoke checks:

```bash
curl -I https://archvadze.com/
curl -I https://archvadze.com/services
curl -I https://archvadze.com/blog
```

Use an authenticated browser smoke check after releases that affect admin, Client Portal, payments, downloads, email, or content publishing.

## Deploy Runbook

Run tests locally before deployment. Production deploys use Git pull, Docker app recreate when needed, and Artisan cache commands.

1. Local workstation:

```bash
ddev artisan test
git status --short
git push
```

2. Production host preflight:

```bash
cd /opt/mycms/app
git status --short
docker compose ps
```

Stop if production has unexpected local changes. Do not overwrite them blindly.

3. Pull the release:

```bash
git pull --ff-only
```

4. Install production PHP dependencies if `composer.lock` changed:

```bash
docker exec mycms-app composer install --no-dev --optimize-autoloader
```

5. Build frontend assets only when the release requires it.

Repository evidence: `package.json` defines `npm run build`, `vite.config.js` builds `resources/css/app.css` and `resources/js/app.js`, and `public/build/manifest.json` exists. If built assets are committed with the release, no production Node build is needed. If a release changes assets but does not include updated `public/build`, build in the release process before deployment or on production with the existing Node toolchain if available.

6. Run migrations only when the release contains migrations:

```bash
docker exec mycms-app php artisan migrate --force
```

7. Clear stale optimization caches:

```bash
docker exec mycms-app php artisan optimize:clear
```

8. Recreate the app container when PHP dependencies, PHP code, env, or runtime config changed:

```bash
docker compose up -d --force-recreate app
```

Use `--build` only when the Dockerfile, build context, or image-level dependencies changed and a rebuild is intentionally required. Do not use `--remove-orphans` as part of the normal deploy path.

9. Rebuild production caches:

```bash
docker exec mycms-app php artisan config:cache
docker exec mycms-app php artisan route:cache
docker exec mycms-app php artisan view:cache
docker exec mycms-app php artisan event:cache
```

10. Verify:

```bash
docker exec mycms-app php artisan about
docker compose ps
curl -I https://archvadze.com/
```

## Rollback Runbook

### Code-Only Release

1. Identify the previous known-good commit.
2. On the production host:

```bash
cd /opt/mycms/app
git status --short
git log --oneline -n 10
```

3. Stop if there are unexpected local changes.
4. Checkout the previous known-good commit using the safest command for the observed state. Do not run arbitrary destructive Git commands without confirming what will be discarded.
5. Reinstall dependencies if the rollback changes `composer.lock`.
6. Recreate the app container if needed.
7. Clear and rebuild caches:

```bash
docker exec mycms-app php artisan optimize:clear
docker exec mycms-app php artisan config:cache
docker exec mycms-app php artisan route:cache
docker exec mycms-app php artisan view:cache
docker exec mycms-app php artisan event:cache
```

8. Smoke test public and authenticated flows affected by the release.

### Release With DB Migrations

Prefer backward-compatible migrations. If a release migrated schema forward, code rollback may not be sufficient. Review the migration before rollback and decide whether the old code can run against the new schema.

If the database must be restored, follow `docs/BACKUP_RECOVERY.md`. Treat database restore as destructive and take a fresh backup first.

### Destructive/Data Migration

Rollback requires the pre-release database backup and file backup taken immediately before the release. Do not rely on code rollback alone.

## Emergency App Recovery

If the app container is down but MariaDB is healthy:

```bash
cd /opt/mycms/app
docker compose ps
docker logs --tail=200 mycms-app
df -h
docker compose up -d --force-recreate app
docker exec mycms-app php artisan about
curl -I https://archvadze.com/
```

Use `--build` in emergency app recovery only when the Docker image itself must be rebuilt.

Do not delete volumes or `/opt/mycms/mariadb` while recovering the app container.

## Database Container Recovery

If MariaDB fails to start:

```bash
cd /opt/mycms/app
docker compose ps
docker logs --tail=200 mycms-mariadb
df -h
du -sh /opt/mycms/mariadb
ls -ld /opt/mycms/mariadb
```

Do not initialize a fresh database over `/opt/mycms/mariadb`. Preserve the existing persistent directory for investigation and restore decisions.

## Scheduler

The Laravel scheduler has one configured task in `routes/console.php`:

```text
logs:clean monthly, without overlapping, background
```

Production should run Laravel Scheduler once per minute if this cleanup task is expected to execute:

```cron
* * * * * cd /opt/mycms/app && docker exec mycms-app php artisan schedule:run >> /dev/null 2>&1
```

Do not add cron automatically without confirming host operations policy.

## Queue

The current production queue is sync. No queue worker recovery is required.

Future queued workflows would require separate worker supervision, restart procedures, failed-job review, and capacity monitoring.

## Disk Space Monitoring

Run simple checks regularly:

```bash
df -h
du -sh /opt/mycms/app/storage
du -sh /opt/mycms/mariadb
du -sh /opt/mycms/backups 2>/dev/null || true
docker system df
```

Investigate growth in logs, backups, private uploads, public media, and MariaDB data before deleting anything.

## Host Nginx and TLS

Host Nginx and Certbot configuration are outside this Laravel repository. Maintain a separate secure backup of:

- Nginx site configuration for `archvadze.com`
- Certbot renewal configuration
- Firewall notes relevant to HTTP/HTTPS and the local PHP-FPM proxy

Do not store private TLS keys in Git.
