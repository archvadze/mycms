# Technical Debt Register

This register captures known debt at the production baseline freeze. Do not treat these as permission to change architecture casually; each item needs its own scoped future batch.

## Debt Summary

| ID | Area | Priority | Classification | Debt | Recommended Future Batch |
| --- | --- | --- | --- | --- | --- |
| TD-001 | Project files | P1 | Data/storage, Security | Legacy public project file fallback remains supported | Dedicated private migration with integrity verification |
| TD-002 | Digital product FKs | P1 | Database/schema, Security | Schema-level cascade behavior may allow destructive outcomes outside normal model guards | FK hardening migration review |
| TD-003 | DB indexes | P2 | Database/schema, Performance | Hot composite query indexes should be evaluated | Targeted index migration |
| TD-004 | Newsletter sending | P2 | Infrastructure, Performance | Bulk newsletter sending is synchronous | Queue/chunked newsletter infrastructure batch |
| TD-005 | Email metadata backup table | P2 | Data/storage, Maintainability | `email_message_metadata_backup_20260808` retention decision remains | Retention/deletion decision batch |
| TD-006 | PayPal dependency warnings | P2 | Maintainability, Infrastructure | Vendor package emits PHP deprecation warnings in tests | Dependency upgrade compatibility batch |
| TD-007 | Public product gallery JS | P3 | UX, Maintainability | Inline gallery JavaScript is acceptable but not ideal | Public frontend refinement batch |
| TD-008 | Blog detail comments UI | P3 | UX | Comments presentation is dense and mixed-language | Blog UX refinement batch |
| TD-009 | Order form componentization | P3 | UX, Maintainability | Order form remains large and would benefit from component boundaries | Public form maintainability batch |
| TD-010 | Off-server backup automation | P2 | Infrastructure | Backup script stages local artifacts but does not upload externally | Backup destination/integration batch |

## P1 Debt

### TD-001 Legacy Project Files Public to Private Migration

Current state:

- Current project uploads are private under `storage/app/private/project-files/...`.
- Protected controller download is the current access model.
- Legacy public fallback is still supported for old files.

Risk:

- Legacy public files may remain accessible through public storage if present.

Future batch:

- Inventory legacy public `project-files/...`.
- Copy to private storage.
- Verify size/hash integrity.
- Update records only if needed.
- Delete public copies only after verified private copies and rollback plan.

### TD-002 Digital Product FK Cascade Behavior

Current state:

- Application-level delete guards protect purchased digital products and versions.
- Migrations include cascade behavior such as `purchases.digital_product_version_id -> cascade`.

Risk:

- Direct database operations outside normal model flow could still create destructive outcomes.

Future batch:

- Review current FK constraints.
- Plan restrictive FK changes if compatible with production data.
- Add migration only after explicit approval and backup plan.

## P2 Debt

### TD-003 Missing or High-Value Composite Indexes

Evidence-backed candidates:

- `projects(client_id, status)` for client dashboard project status aggregates.
- `orders(client_id, status)` for client dashboard order status aggregates.
- Email latest/unread/thread patterns on `email_messages`, such as combinations involving `direction`, `email_thread_id`, `is_read`, `received_at`, and `id`.

Current state:

- Existing migrations include some indexes, including `orders(status, created_at)`, `purchases(user_id, created_at)`, `project_messages(project_id, created_at)`, and comment indexes.

Future batch:

- Confirm production query plans and table sizes.
- Add only targeted indexes.
- Test migration time and rollback.

### TD-004 Newsletter Bulk Sending

Current state:

- Newsletter bulk sending is synchronous.
- Production queue baseline is sync.

Risk:

- Large subscriber lists can make admin actions slow or fragile.

Future batch:

- Introduce queue infrastructure only with explicit operations approval.
- Chunk subscribers.
- Add retry/failure visibility.

### TD-005 Email Metadata Backup Table

Current state:

- `email_message_metadata_backup_20260808` remains as a historical backup table.

Risk:

- Long-term retention may be unnecessary and can add confusion/storage overhead.

Future batch:

- Confirm operational confidence.
- Decide retention or deletion.
- Take backup before removal if deletion is approved.

### TD-006 PayPal PHP Deprecation Warnings

Current state:

- Test runs emit vendor deprecation warnings from `srmklive/paypal` under PHP 8.4.
- Warnings are currently non-blocking.

Future batch:

- Evaluate compatible dependency upgrade.
- Confirm payment tests and sandbox behavior before production release.

### TD-010 Off-Server Backup Automation

Current state:

- `scripts/backup-production.sh.example` stages local backup artifacts.
- Off-server encrypted destination is an operator responsibility.

Future batch:

- Select backup destination.
- Add encrypted upload/verification automation.
- Add retention enforcement outside the application tree.

## P3 Debt

### TD-007 Public Product Gallery Inline JS

Classification: UX, Maintainability

Current state:

- Product gallery behavior is implemented inline in Blade.

Future batch:

- Extract to a small frontend module when broader public frontend work is scheduled.

### TD-008 Blog Detail Comments Presentation

Classification: UX

Current state:

- Blog comments are functional but visually dense and include mixed-language text.

Future batch:

- Refine presentation and copy without changing authorization/comment semantics.

### TD-009 Order Form Componentization

Classification: UX, Maintainability

Current state:

- Order form is production-functional but large.

Future batch:

- Split into maintainable components while preserving server-side validation and browser validation.

## Phase 2 Roadmap

Phase 2 excludes already-frozen baseline work. It should focus on explicitly scoped improvements:

- Legacy project-file migration
- Digital product FK hardening
- Targeted DB indexes
- Queue infrastructure
- Newsletter batching
- Richer reporting
- Analytics
- Public UX refinements
- Email/notification enhancements
- Additional payment/provider features
- Operational off-server backup automation

Do not bundle these into unrelated maintenance batches.

## Priority Counts

- P1: 2
- P2: 5
- P3: 3

