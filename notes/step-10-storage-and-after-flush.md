# Step 10 (M1.2): a module-owned table, and the hook after the commit

2026-11-04. Decision 3 (`NOTES.md`) chose a module-owned table. Before
ADR-0003 and a spec rest on it: how a module registers its own table and
entity in Omeka S 4.2.1, whether it installs and uninstalls cleanly,
whether its rows follow deleted media, and where the verdict can be
written once the media is committed. Step 09 left that last point open:
`entity.persist.post` fires inside Doctrine's flush.

## Read

- `Omeka\Service\EntityManagerFactory` (4.2.1): the entity manager uses
  annotation mapping over `entity_manager.mapping_classes_paths` from the
  merged module configuration, and never generates proxies at run time
  (`setAutoGenerateProxyClasses(-1)`); proxies come from
  `entity_manager.proxy_paths`. Doctrine ORM 2.20.8, DBAL 2.13.9,
  annotations 1.14.4 (`vendor/composer/installed.json`).
- `Doctrine\ORM\UnitOfWork::commit()` (2.20.8): the flush opens a
  transaction, commits it, and only then dispatches `postFlush`.
- `omeka-s-modules/Mapping` on GitHub (`Module.php`,
  `config/module.config.php`): `mapping_classes_paths` to `src/Entity`,
  `proxy_paths` to shipped proxies, `CREATE TABLE` and foreign keys
  `ON DELETE CASCADE` through `Omeka\Connection` in `install()`,
  `DROP TABLE IF EXISTS` in `uninstall()`, versioned changes in
  `upgrade()`.

## How it was measured

A second measurement-only module, `tools/probes/StorageProbe/` (never
shipped; formatted with Pint after the run, logic unchanged):

- an entity `ProbeVerdict` in `src/Entity`, keyed by its media (`@Id`
  on a `@OneToOne` to `Omeka\Entity\Media`, `onDelete="CASCADE"`),
  registered through `mapping_classes_paths` only, **without proxies**;
- `install()` creates `storage_probe_verdict` with a foreign key to
  `media(id)` `ON DELETE CASCADE`; `uninstall()` drops it;
- `entity.persist.post` on media notes the media and, the first time,
  adds a Doctrine `postFlush` listener to the entity manager; that
  listener logs whether a transaction is active and whether the media
  row is visible on a second, separate database connection, then writes
  one row: through DBAL (`insert`), through the entity manager
  (`persist` and a second `flush`) when the media's source name contains
  "ormflush", or throws when it contains "throw";
- an `api.search.query` listener on the media adapter joins the entity in
  DQL, the shape a filter in the media browse (M2) needs.

Copied into the test environment's PHP 8.3 container, installed through
the modules page, and driven with fixtures of `provemark/c2pa-verifier`.
Afterwards `tools/env/reset.sh` removed it and all data.

## Measured

**Install and uninstall.** Installing added exactly one table to the
schema dump (`mariadb-dump --no-data`, auto-increment values removed);
`/admin`, `/admin/media`, `/admin/item` and `/api/media` answered 200.
After uninstalling, with rows still in the table, the dump was identical
to the one before installing.

**After the commit, on every route:**

| route | media | variant | transaction active | nesting level | media visible on a second connection | row written |
|---|---|---|---|---|---|---|
| admin item form, upload | 2 | DBAL | no | 0 | yes | yes |
| API, item with media, upload | 4 | entity manager, second flush | no | 0 | yes | yes |
| API, `POST /api/media` onto an item | 5 | throw | no | 0 | yes | — |
| `ApiManager::batchCreate()` from PHP CLI (as a job would), two items with `html` media | 7, 9 | DBAL | no | 0 | yes | yes |

- `postFlush` fired after the commit on all four routes, including batch
  create, which flushes once for the batch; the listener added during the
  flush (in `entity.persist.post`) was called for that same flush.
- Writing there worked through DBAL and through the entity manager.
- **An exception thrown in `postFlush` turned the response into HTTP 500**
  (`{"errors":{"error":"probe: thrown in postFlush"}}`) **while the media
  was already committed** (media 5 existed afterwards). An uncaught error
  in the module would report a failed upload that in fact succeeded.

**Reading without proxies.** `EntityManager::find()` and `findAll()` on
the entity worked; the DQL join in `api.search.query` worked:
`/api/media?probe_how=dbal` gave media 2, 7, 9; `=ormflush` gave 4; a
value with no rows gave none; `/admin/media?probe_how=dbal` answered 200.

**Rows follow deletes.** `DELETE /api/media/4` removed row 4;
`DELETE /api/items/1` (media 2 and 5) removed row 2. Only rows 7 and 9
remained, matching the media left. The foreign key did it; the probe had
no remove listener.

## Reasoned, not measured

- Proxies are not needed as long as nothing loads this entity lazily
  (no other entity points at it, no `getReference()`); with
  `setAutoGenerateProxyClasses(-1)` such a load would fail, so a later
  association towards the entity would need shipped proxies.
- `postFlush` fires for every flush in the request, not only those with
  new media; the listener is added only after a new media item, and
  empties its list on each call.
- A flush inside an outer transaction (none was seen on the measured
  routes: nesting level 0) would reach `postFlush` before the outer
  commit; another module could open one.
- Whether verifying in the request is fast enough, or must move to a job,
  is not measured; it belongs to the M1 spec.
