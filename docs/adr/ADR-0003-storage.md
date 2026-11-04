# ADR-0003: Storing the verdict

| Field    | Value            |
|----------|------------------|
| Status   | proposed         |
| Date     | 2026-11-04       |
| Decided  | —                |

## Context

Decision 3 (`NOTES.md`, 2026-11-03) chose a module-owned table over Omeka
values, annotations or something else. This ADR records how, on the
measurements of `notes/step-09-events-and-original.md` and
`notes/step-10-storage-and-after-flush.md` (Omeka S 4.2.1):

- `entity.persist.post` on `Omeka\Entity\Media` fires once per new media
  item on every measured route, inside Doctrine's flush.
- Doctrine's `postFlush` fires after the commit on every measured route,
  batch create included; writing there works.
- An exception thrown in `postFlush` gives HTTP 500 for an upload that was
  committed.
- A module's entity, mapped through `mapping_classes_paths` without
  proxies, can be read and joined in DQL; a foreign key
  `ON DELETE CASCADE` removes its row with the media, also when the item is
  deleted; a table created in `install()` and dropped in `uninstall()`
  leaves the schema as it was.

The alternatives, from decision 3: Omeka values on the media (editable by
any editor, shown publicly by default, mixed with descriptive metadata);
Omeka annotations (the same editability, a heavier model); files next to
the original (no query for the browse filter of M2).

## Decision

1. One table owned by the module, one row per media item, its primary key
   the media id, with a foreign key to `media(id)` `ON DELETE CASCADE`.
   The columns are the M1 spec's business.
2. Mapped as a Doctrine entity in `src/Entity` (annotations, as Omeka S
   4.2.1 requires) through `entity_manager.mapping_classes_paths`, without
   proxies while nothing loads it lazily.
3. Created with plain SQL in `install()`, dropped in `uninstall()`, changed
   between versions in `upgrade()`, as the Mapping module does.
4. Written after the commit: new media noted in `entity.persist.post`, the
   row written from a Doctrine `postFlush` listener registered on the
   first such note.
5. That listener catches every `Throwable`; a failure becomes state
   `error` with its reason, never an exception out of the listener.

## Consequences

- No delete listener: the database removes the row.
- The table is invisible to Omeka's own API and editing; showing it (M2)
  needs the module's own view code.
- A later entity pointing at this one would need shipped proxies (a new
  decision then).
- When verification itself runs (in the request or in a job) is not
  decided here; the M1 spec decides it.
- Deptrac gets a layer for `src/Entity` before its first file.
