# Backup and Recovery Runbook

This runbook defines the recovery-critical state for the production Laravel CMS at `archvadze.com`.

## Recovery-Critical State Inventory

### Must Back Up

- MariaDB database: schema, data, indexes, constraints, content, users, permissions, orders, projects, purchases, subscriptions, audit data, and file path references.
- Laravel private storage: `/opt/mycms/app/storage/app/private`
  - Current project files: `project-files/...`
  - Current digital product files: `digital-products/...`
  - Supported older private product paths if present: `products/files/...`
- Laravel public upload source: `/opt/mycms/app/storage/app/public`
  - CMS images and media referenced through `public/storage`
  - Portfolio, guide, publication, service, page, digital product images
  - Legacy public fallback files described below
- Environment and secrets file: `compose.yml` configures both `app` and `mycms-mariadb` to use `.env.production`, resolved relative to `/opt/mycms/app` as `/opt/mycms/app/.env.production`. Store it encrypted and off-server.
- Application code reference: Git remote plus the exact deployed commit or tag.

### Should Back Up

- Host Nginx site configuration and Certbot renewal configuration, stored outside Git.
- Operational logs: `/opt/mycms/app/storage/logs`, for incident review and audits.
- Deployment notes that map releases to commits and backup timestamps.

### Regenerable / Do Not Need Core Backup

- `vendor/` after `composer install --no-dev --optimize-autoloader`
- `node_modules/`
- Laravel generated caches: `bootstrap/cache`, route/config/event caches, compiled Blade views
- File cache and file sessions under `storage/framework/cache` and `storage/framework/sessions`
- `storage/framework/views`
- `storage/app/livewire-tmp`
- `public/storage` symlink itself, if `storage/app/public` is preserved and `php artisan storage:link` can recreate it

## Database Backup Strategy

Use the running MariaDB container. Do not hardcode or print database passwords.

Safe operator pattern from the production host:

```bash
cd /opt/mycms/app
mkdir -p /opt/mycms/backups/manual
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
set -a
. /opt/mycms/app/.env.production
set +a
docker exec -e MYSQL_PWD="${DB_PASSWORD:?}" mycms-mariadb \
  mariadb-dump \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --default-character-set=utf8mb4 \
  -u "${DB_USERNAME:?}" \
  "${DB_DATABASE:?}" \
  | gzip -9 > "/opt/mycms/backups/manual/mycms-db-${timestamp}.sql.gz"
test -s "/opt/mycms/backups/manual/mycms-db-${timestamp}.sql.gz"
```

This includes schema, data, indexes, constraints, triggers, routines, and events as supported by the database.

Validation:

```bash
ls -lh /opt/mycms/backups/manual/mycms-db-*.sql.gz
gzip -t /opt/mycms/backups/manual/mycms-db-YYYYMMDDTHHMMSSZ.sql.gz
```

Replace the filename with the actual timestamped file. Do not print `.env.production`.

## File Backup Strategy

Back up authoritative source directories, not both source and symlink.

From the production host:

```bash
cd /opt/mycms/app
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p /opt/mycms/backups/manual
tar -C /opt/mycms/app -czf "/opt/mycms/backups/manual/mycms-private-storage-${timestamp}.tar.gz" storage/app/private
tar -C /opt/mycms/app -czf "/opt/mycms/backups/manual/mycms-public-storage-${timestamp}.tar.gz" storage/app/public
test -s "/opt/mycms/backups/manual/mycms-private-storage-${timestamp}.tar.gz"
test -s "/opt/mycms/backups/manual/mycms-public-storage-${timestamp}.tar.gz"
```

Preserve directory structure, filenames, timestamps, and permissions where possible.

Do not separately archive `public/storage`; it is a symlink to `storage/app/public`.

## Legacy Project File Debt

Current Client Portal uploads store project files on the private local disk under:

```text
storage/app/private/project-files/{project_id}/...
```

Download code still supports a legacy public fallback by checking the public disk when a private file is not found. Until that migration debt is resolved, public storage backups must include any legacy `project-files/...` paths under:

```text
storage/app/public/project-files/...
```

Do not migrate or delete legacy public project files as part of backup.

## Digital Product Storage

Current protected digital product files use private local storage paths:

```text
storage/app/private/digital-products/...
```

The migration command `digital-products:migrate-private` exists to copy legacy public product files to private storage. Do not run it as part of backup or restore unless a separate migration operation is planned and tested.

Download entitlement depends on both:

- Database purchase/version records
- The referenced file existing in private storage, or in the documented legacy public fallback path

Public storage backups must include any legacy digital product fallback files under:

```text
storage/app/public/digital-products/...
storage/app/public/products/files/...
```

## Environment and Secrets Strategy

The configured production env file, `/opt/mycms/app/.env.production`, is required for full disaster recovery and must not be committed to Git.

Back it up separately using encrypted, access-controlled, off-server storage. Include these secret categories without printing values in tickets, logs, or runbooks:

- `APP_KEY`
- DB credentials
- Resend credentials
- PayPal credentials
- Webhook secrets
- OAuth/social login secrets
- Any token, API key, or signing secret

Loss of `APP_KEY` can invalidate encrypted/session data and may break any encrypted application values.

## Backup Destination

Backups must not exist only on the Oracle VM.

Recommended model:

1. Local temporary staging on the production host, for example `/opt/mycms/backups/manual`.
2. Encrypted off-server copy to an operator-managed backup destination.
3. Optional second independent encrypted copy.

No cloud provider is assumed by this repository.

## Retention Policy

For the current small production system:

- Daily database backups
- Daily storage backups when uploads/content change regularly
- Keep 7 daily backups
- Keep 4 weekly backups
- Keep 3 monthly backups

Review storage usage monthly. Do not delete the only known-good backup before a new one is validated off-server.

## Backup Consistency

Take the database dump and file archives close together. For higher confidence:

- Avoid admin uploads, project file uploads, digital product updates, and content publishing during the backup window where practical.
- Record the database dump timestamp and storage archive timestamp together.
- For a major release, take a fresh backup immediately before deployment.

## Database Restore Strategy

Restoring the database is destructive. Do not run restore commands until the target, dump, and downtime window are confirmed.

Prerequisites:

1. Confirm the restore target is production or a disposable drill environment.
2. Confirm the dump file exists and passes `gzip -t`.
3. Confirm a fresh pre-restore backup has been taken.
4. Put the site into maintenance mode when restoring production:

```bash
cd /opt/mycms/app
docker exec mycms-app php artisan down
```

5. Load environment variables without printing them:

```bash
set -a
. /opt/mycms/app/.env.production
set +a
```

Destructive import step:

```bash
gunzip -c /path/to/mycms-db-YYYYMMDDTHHMMSSZ.sql.gz | docker exec -i -e MYSQL_PWD="${DB_PASSWORD:?}" mycms-mariadb \
  mariadb \
  -u "${DB_USERNAME:?}" \
  "${DB_DATABASE:?}"
```

After import:

```bash
docker exec mycms-app php artisan optimize:clear
docker exec mycms-app php artisan migrate:status
docker exec mycms-app php artisan storage:link
docker exec mycms-app php artisan config:cache
docker exec mycms-app php artisan route:cache
docker exec mycms-app php artisan view:cache
docker exec mycms-app php artisan event:cache
docker exec mycms-app php artisan up
docker exec mycms-app php artisan about
```

Run HTTP and authenticated browser smoke checks.

Do not automatically run `php artisan migrate --force` after importing a point-in-time database backup. The safest recovery restores the intended application commit/release, matching database backup, and matching file backup. If the restored database backup and deployed release belong to the same release, leave schema unchanged after confirming `migrate:status`.

Run this deliberate schema-changing step only when the recovery plan intentionally advances the restored database to migrations required by the deployed target release:

```bash
docker exec mycms-app php artisan migrate --force
```

## File Restore Strategy

Restoring files can overwrite current uploads. Take a fresh file backup first.

Example production restore sequence:

```bash
cd /opt/mycms/app
docker exec mycms-app php artisan down
tar -C /opt/mycms/app -xzf /path/to/mycms-private-storage-YYYYMMDDTHHMMSSZ.tar.gz
tar -C /opt/mycms/app -xzf /path/to/mycms-public-storage-YYYYMMDDTHHMMSSZ.tar.gz
docker exec mycms-app php artisan storage:link
docker exec mycms-app php artisan optimize:clear
docker exec mycms-app php artisan config:cache
docker exec mycms-app php artisan route:cache
docker exec mycms-app php artisan view:cache
docker exec mycms-app php artisan event:cache
docker exec mycms-app php artisan up
```

Verify public media, protected project downloads, and protected digital product downloads through application routes.

## Restore Drill

A backup is not trustworthy just because the files exist. Perform restore drills in a disposable environment, not production.

Verify:

- SQL dump imports cleanly.
- `php artisan migrate:status` reports coherent schema state for the restored release.
- `php artisan migrate --force` is tested only when the drill intentionally advances the restored database to a newer target release.
- `php artisan about` works.
- `public/storage` symlink exists and public media loads.
- Protected project files are downloadable only by the owning client.
- Purchased digital product files are downloadable only by entitled users.
- Key public pages load: home, services, portfolio, blog, guides, shop.
- Login works.
- Admin panel access respects roles.
- Client ownership isolation still holds.

## Disaster Recovery Order

1. Provision host and Docker runtime.
2. Restore or recreate host Nginx/Certbot/firewall configuration from secure host-level backup.
3. Checkout application code at the intended commit into `/opt/mycms/app`.
4. Restore `.env.production` from encrypted secrets backup.
5. Restore `/opt/mycms/mariadb` from infrastructure backup or import SQL dump into the MariaDB container.
6. Restore `storage/app/private` and `storage/app/public`.
7. Recreate containers.
8. Run `composer install --no-dev --optimize-autoloader` if needed.
9. Recreate `public/storage` with `php artisan storage:link`.
10. Clear/rebuild Laravel caches.
11. Smoke test.
