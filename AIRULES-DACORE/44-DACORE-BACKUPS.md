# 44 — DACore backups

DACore provides durable, operator-created recovery archives independently of the
short-lived rollback folder used while a DACore update is being applied.

## Storage

- Backups live under `app/runtime/dacore-backups/`.
- The service creates a deny-all `.htaccess` and an inert `index.php`.
- The browser never receives a filesystem path. Download and restore resolve a
  database row, verify canonical-path containment, then verify the stored SHA-256
  and the archive manifest.
- Names use `{safe-target}-{random-hex}-{m-d-H-i-s}.zip`. The random name is
  defence in depth; root authorization remains mandatory.
- Temporary files use a non-ZIP suffix and are atomically renamed only after the
  archive and manifest are complete.

## Backup types

### Framework

Contains only:

- `app/DotApp.php`
- `app/parts/**`
- root `changelog.txt`

It never contains `dotapper.php`, project configuration, listeners, modules,
runtime data, AI instructions, the front controller, or editor files.

### Database

Contains a streamed SQL export of the configured main database. Export work is
bounded and chunked; application code must not call `exec`, `mysqldump`, or load
an unbounded table into memory.

Full-database restore is intentionally offline through the native database
client. An HTTP request must not replace the schema that owns its own session,
authorization, and update transaction.

### Module

Contains `app/modules/{Module}/`, exact `{lowercase_module}_*` tables, the
module's package/install metadata, and creator-owned DACore registry rows.
DACore itself is the explicit `dacore_*` system-module case.

Prefix discovery is fail-closed. It must not include another module's tables,
framework auth tables, or unrelated shared DACore rows. Uploaded PHP is never
executed to discover extra table names.

## Restore

- Only server-generated framework and module archives may be restored from the
  admin UI.
- Restore requires a root operator, the normal DACore POST CRC gate, graphical
  confirmation, and fresh step-up 2FA.
- Before changing live data, create a separate recovery archive.
- Verify the database row, canonical path, SHA-256, manifest type, target, and
  every allowlisted entry.
- Stage first. Replace only the declared framework/module files and owned
  tables. Roll files back if the operation fails.
- A database backup remains downloadable for offline recovery and has no
  one-click web restore.

## Administration

The Backups list is a bounded AJAX page:

- SQL `COUNT(*)` plus `LIMIT`/`OFFSET`
- encrypted row and page identifiers
- card overlay while a request runs
- rows and pager patched together
- visible DACore toast for every outcome
- no `location.reload()`, query-string paging, `alert()`, or `window.confirm()`

Create, restore, download, and delete handlers must never trust a posted
filename or path. Runtime backup ZIPs are never included in a DACore release
package.
