# Step 09 (M1.1): the events around a new media item, and the original

2026-11-04. Before any M1 spec rests on it: which event fires once per new
media item with the original available, whether that original is
byte-identical to the upload, and which events fire on update and delete.
The brief's candidate was `api.create.post` on the media adapter.

## How it was measured

Omeka S 4.2.1 in the test environment (PHP 8.3 container), with a
measurement-only module, `tools/probes/EventProbe/` (kept in the
repository so the run can be repeated; never shipped, never loaded by the
tests). It listens on the shared event manager and writes one JSON line
per event to `/tmp/event-probe.log` in the container, with the media's id,
ingester, storage id and recorded SHA-256, whether the original exists
under `files/original/` and its SHA-256, and, for `media.ingest_file.*`,
the temporary file and its SHA-256.

Listened to:

- on `Omeka\Entity\Media`: `entity.persist.pre`, `entity.persist.post`,
  `entity.update.post`, `entity.remove.pre`, `entity.remove.post`;
- on `Omeka\Api\Adapter\MediaAdapter` and `ItemAdapter`: `api.create.pre`,
  `api.create.post`, `api.update.post`, `api.delete.post`,
  `api.hydrate.post`, `api.batch_create.post`;
- on `Omeka\File\TempFile`: `media.ingest_file.pre`, `media.ingest_file.post`.

The probe was copied into the container (`docker compose cp`), installed
through the modules page, and driven over HTTP. The routes, each with a
verifier fixture from `provemark/c2pa-verifier`'s `tests/Fixtures/`:

| route | how | file (SHA-256, first 12) |
|---|---|---|
| admin item form, upload | multipart POST to `/admin/item/add` as the browser sends it | `fixture-signed.jpg` (`b004c197b6bf`) |
| REST API, item with media, upload | multipart POST `/api/items` with a test API key | `fixture-signed.png` (`649fa44a68e1`) |
| URL ingester | JSON POST `/api/items`, `ingest_url` served by `php -S` inside the same container (no network outside it) | `fixture-signed.webp` (`f0f546a2a110`) |
| REST API, media onto an existing item | multipart POST `/api/media` with `o:item` | `fixture-unsigned.jpg` (`7753f456cc72`) |
| media without a file | JSON POST `/api/items`, `html` ingester | none |
| upload Omeka refuses | multipart POST `/api/items` with a `.php` file | — |

Then `PATCH /api/media/2`, `DELETE /api/media/4` and `DELETE /api/items/1`
(an item with two media). Afterwards `tools/env/reset.sh` removed the
probe, the test API key and all the data.

## Measured

Every route that creates media with a file gave the same order, in one
request:

1. `media.ingest_file.pre`: temporary file present, its SHA-256 equal to
   the upload; no id; no original yet.
2. `media.ingest_file.post`: temporary file already deleted; original
   present; no id yet.
3. `api.hydrate.post` (media adapter), `entity.persist.pre`: no id yet.
4. **`entity.persist.post` on `Omeka\Entity\Media`: id set, original
   present.**
5. `api.create.post`: on the item adapter when the media came with an
   item; on the media adapter only for `POST /api/media`.

| route | media id at `entity.persist.post` | SHA-256 of the original = upload = `media.sha256` |
|---|---|---|
| admin form | 2 | yes |
| API, item with media | 4 | yes |
| URL ingester | 6 | yes |
| API, media onto item | 7 | yes |

- **The brief's candidate does not hold.** `api.create.post` on the media
  adapter does not fire for media created inside an item (the admin form,
  the API with `o:media`, the URL ingester); it fired only for
  `POST /api/media`. `entity.persist.post` on `Omeka\Entity\Media` fired
  once per new media item on all four routes.
- **The original is byte-identical to the upload** on these four routes:
  the stored file's SHA-256 equals the uploaded file's and the media's
  recorded `sha256`. Its path is
  `files/original/<storage_id>.<extension>`.
- **Media without a file** (`html`): `entity.persist.post` fires with an
  id, no storage id, no SHA-256, no original.
- **A refused upload** (HTTP 422, "Cannot store files with the media type
  text/x-php"): `api.hydrate.post` fires, no `entity.persist.*`, no media
  row, no original.
- **Update** (`PATCH`, `o:is_public`): `entity.update.post` and
  `api.update.post` on the media adapter; the original unchanged.
- **Delete of one media** (`DELETE /api/media/4`): `entity.remove.pre`
  with the id and the original still present; `entity.remove.post` with
  the id `null` and the original gone; `api.delete.post` on the media
  adapter.
- **Delete of an item with media**: `entity.remove.pre` and
  `entity.remove.post` per media, but **no `api.delete.post` on the media
  adapter**, only on the item adapter. So the id of a removed media is
  available in `entity.remove.pre`, not later.

Read in the source (`application/src/`), not measured:
`entity.*` events come from `Omeka\Db\Event\Subscriber\Entity`, a Doctrine
subscriber, with the entity class (and `Omeka\Entity\Resource`) as
identifiers; they fire inside Doctrine's flush. `media.ingest_file.*` come
from `TempFile::mediaIngestFile()`, before and after `storeOriginal()`.

## Reasoned, not measured

- `entity.persist.post` runs inside the flush. Writing our own row from
  that listener with another flush is something Doctrine does not allow
  during a flush; and if the flush failed after it, a verdict would exist
  for a media item that does not. The likely shape is: note the new media
  in `entity.persist.post`, verify and store after the flush has
  committed. Which hook fires after the commit, in every route, is not
  measured yet; it belongs with the storage measurement (M1.2).
- Not measured, so no spec may rely on them yet: CSV Import and File
  Sideload (separate modules, not installed), batch create through the
  API, replacing a file (core Omeka S has no replace for an upload), and
  ingesters other than `upload`, `url` and `html`.
