# Production Baseline Freeze

Baseline date: 2026-08-11

Baseline Git reference inspected locally: `a421a1b Add production operations and recovery runbooks`

Production URL: `https://archvadze.com`

This document freezes the current operationally stable CMS baseline. Future work should treat this as the architecture and behavior boundary unless a later approved architecture, security, migration, or operations batch supersedes it.

## Baseline Purpose

The current CMS is considered production-ready for its existing scope:

- Public website and content management
- Filament admin operations
- Client Portal
- Email Inbox workflow
- Orders, projects, purchases, subscriptions, and digital products
- Backup, recovery, deploy, and rollback runbooks

This freeze defines what is complete, what must not be changed casually, and what belongs to future Phase 2 work.

## Frozen Architecture

- Laravel: 12.x, locked at `laravel/framework v12.54.1`
- PHP runtime: PHP 8.4 FPM container; last local `artisan about` showed PHP 8.4.22
- Admin: Filament v5.3.4
- Database: MariaDB, `mariadb:11.8` in `compose.yml`
- App container: `mycms-app`
- DB container: `mycms-mariadb`
- Production app path: `/opt/mycms/app`
- Production DB data path: `/opt/mycms/mariadb`
- Host web server: Nginx proxying PHP-FPM to `127.0.0.1:9002`
- Cache/session baseline: file-based in production context
- Queue baseline: sync
- Mail: Resend
- Payments: PayPal
- Authorization package: Spatie permissions
- File storage: local private storage plus public storage symlink
- Deployment: Git pull, Docker app recreate, Artisan optimization caches
- Operations docs: `docs/OPERATIONS.md` and `docs/BACKUP_RECOVERY.md`

Production optimization caches are part of the baseline:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

The normal deploy cache sequence is:

1. `php artisan optimize:clear`
2. `docker compose up -d --force-recreate app`
3. `php artisan config:cache`
4. `php artisan route:cache`
5. `php artisan view:cache`
6. `php artisan event:cache`

Use `--build` only when Dockerfile, build context, or image dependencies changed.

## Frozen Authorization Model

Role names are authoritative and must not be aliased or renamed casually.

Super Admin:

- Full admin, security, settings, and audit access
- Activity Log access
- Team/user security administration

Admin:

- Operations access
- Orders
- Projects
- Clients
- Inbox
- Purchases
- Subscriptions
- Digital Products where currently allowed

Editor:

- Content management
- Public content publishing workflows where currently authorized

Support:

- Inbox/contact/comment support
- No content, settings, financial, or security mutation outside the current matrix

Client:

- No Filament panel
- Client Portal only

Panel access requires an authorized role. Account status must be active. Verified email remains required where currently enforced. Client Portal ownership checks are server-side and must not be replaced with UI-only guards.

## Frozen Client Portal Security

Client Portal access requires:

- Client role
- Active account
- Verified email where the current middleware enforces it
- Associated `Client` record

Invariants:

- Project ownership is resolved server-side.
- Order, purchase, and subscription ownership are server-side.
- Project messages are ownership-scoped.
- Project uploads use private local storage.
- Downloads go through protected controller routes.
- Raw file paths are not exposed to clients.
- Financial IDs and provider payloads are not exposed in client UI.
- Purchase downloads require entitlement.
- Historical paid purchase download semantics remain valid as currently implemented.
- File upload size/type limits and download limits remain enforced.

## Frozen Email Architecture

The email architecture is:

```text
External email
-> Resend Inbound
-> verified/throttled Laravel webhook
-> database
-> Filament Inbox
-> outbound Resend reply
```

The configured operational model is a single mailbox handled through the Email Inbox.

Threading principles:

- `References` header matching
- `In-Reply-To` matching
- Normalized message/thread linkage through `EmailMessage` and `EmailThread`
- Inbox shows one latest inbound row per conversation
- Thread statuses are `open` and `closed`
- Opening a conversation marks inbound messages read
- Unread badge reflects unread open conversations
- Inbound reply to a closed thread reopens it
- Attachments are retrieved through protected routes and are not stored locally
- Email metadata storage is minimized and sensitive payloads must not be expanded casually

Do not redesign threading, webhook verification, attachment retrieval, or outbound reply architecture without a dedicated email architecture batch.

## Frozen Order and Project Workflow

Order statuses:

- `pending`
- `contacted`
- `accepted`
- `rejected`

Order invariants:

- Accepted order creates a Project.
- Duplicate Project creation is prevented by `order_id`.
- `accepted` is terminal for normal operational status actions.
- Payment fields are server-controlled.
- Order pricing and identity-sensitive fields are not trusted from client-submitted overrides.

Project statuses:

- `pending`
- `in_progress`
- `review`
- `completed`

Project invariants:

- Project status may move to another valid status according to current workflow.
- Linked source order is immutable.
- Client ownership remains scoped through the associated `Client`.

Do not introduce a second status system or a new state machine without an explicit future workflow batch.

## Frozen Payment, Purchase, and Entitlement Model

PayPal security invariants:

- Server-authoritative price
- Order ownership enforced
- Purchase ownership enforced
- Capture validation
- Provider status validation
- Currency, amount, and reference validation
- Idempotency for already-paid orders and purchases
- No raw provider payloads in UI/logs
- No client-trusted pricing

Digital purchase entitlement:

- Purchase must belong to the current user.
- Version/product relationship is validated.
- Download limit and expiry are enforced.
- Download uses protected POST route.
- Download logging is retained.
- Download count does not decrement below zero.
- Unpublished product may remain downloadable for a historical paid purchase according to current semantics.

Do not alter financial semantics outside a dedicated payment/security review.

## Frozen Private Storage Model

Project files:

- Current uploads use local private disk.
- Current path family: `storage/app/private/project-files/...`
- Downloads go through protected controller logic.
- Legacy public fallback remains supported until a future migration resolves it.

Digital products:

- Current version files use private local storage.
- Current path family: `storage/app/private/digital-products/...`
- Stored extension is server-derived.
- Dangerous extensions are rejected.
- Downloads require entitlement.
- Legacy public fallback remains supported where applicable.

Do not move files, run storage migrations, or remove legacy fallbacks without a dedicated migration and rollback plan.

## Frozen Public Content Visibility

Public visibility rules:

- Portfolio listing exposes only published projects.
- Publications expose only published records.
- Guides expose only published records.
- Shop exposes only published products.
- Homepage proof sections expose only published records where the content type supports publishing.
- CMS publish/unpublish actions require server-side authorization.

Do not weaken publication filters or authorize mutations from list/view-only capabilities.

## Frozen Admin Model

Major operational resources:

- Orders
- Projects
- Clients
- Inbox
- Purchases
- Subscriptions
- Digital Products
- Content
- Settings
- Activity Log

Admin invariants:

- Activity Log is Super Admin-only and read-only.
- Sensitive audit fields are redacted.
- Workflow mutation actions use server-side authorization.
- Admin UX is considered part of the production baseline.

## Frozen Cache and Performance Baseline

- Production config, routes, views, and events are cache-compatible.
- Homepage cache uses generation-based section keys via `App\Support\HomepageCache`.
- `site.settings` cache is invalidated on settings changes.
- Admin dashboard aggregates are DB-side.
- Client dashboard lists are bounded and aggregates are DB-side.
- No Redis dependency is assumed.
- No queue worker dependency is assumed.

## Operations Baseline

Use these runbooks as operational references:

- `docs/OPERATIONS.md`
- `docs/BACKUP_RECOVERY.md`

Future production changes must preserve:

- Backupability
- Restore procedure
- Safe deployment
- Rollback path
- No secrets in Git
- Off-server backups
- No destructive production changes without fresh backup

## Change Control Rules

Future Agent and developer batches must follow these rules:

- One explicit scope per batch.
- Inspect before modifying.
- No hidden migrations.
- No package additions without approval.
- No role matrix changes without explicit request.
- No payment semantics changes without dedicated review.
- No storage architecture changes without migration and rollback plan.
- No auth weakening.
- No production direct edits.
- Local tests before commit.
- No commit, push, or deploy by Agent unless explicitly requested.
- Production migrations only when the release includes them.
- Backup before destructive or data migrations.
- Preserve production cache sequence.
- No secrets in output.

## Definition of Done

The current production-ready baseline means:

- Full local test suite is green.
- Deployment has completed successfully.
- HTTP smoke checks are green.
- Authenticated browser smoke checks are green for affected workflows.
- Config, route, view, and event caches are built in production.
- Backup/recovery docs exist and are current.
- Git working tree is clean before release handoff.
- Known debt is documented.

This is an operational baseline, not a formal compliance certification.

## Baseline Version Record

- Date: 2026-08-11
- Git reference inspected: `a421a1b Add production operations and recovery runbooks`
- Laravel: `v12.54.1`
- Filament: `v5.3.4`
- PHP: `8.4` runtime family; local DDEV about previously reported `8.4.22`
- Production URL: `https://archvadze.com`
- Database engine: MariaDB, `mariadb:11.8`
- Containers: `mycms-app`, `mycms-mariadb`
- Deployment model: Git pull, app container recreate, Artisan caches
- Recovery docs: `docs/OPERATIONS.md`, `docs/BACKUP_RECOVERY.md`

