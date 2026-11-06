# SPEC-002: Verify on upload and store the result

| Field      | Value                                             |
|------------|---------------------------------------------------|
| Status     | implemented                                       |
| Author     | Maurice van Loon                                  |
| Approved   | Maurice van Loon, 2026-11-04                      |
| Supersedes | —                                                 |

> Lifecycle: `draft` → maintainer approves → `approved` → tests-first →
> `implemented` (Traceability filled). No implementation code while `draft`.
> Only the Traceability section of an `approved` spec may change without a new
> approval; everything else needs a proposed amendment.

## Problem

An institution that receives a JPEG, PNG or WebP with Content Credentials
has no way to see, in Omeka S, whether those credentials verify. This spec
is M1 of `docs/milestones.md`: every new media item with an original in one
of these three formats is verified once, with `provemark/c2pa-verifier`,
and the verdict is stored with the media. Showing it is M2 (its own spec).

It rests on three measured notes against Omeka S 4.2.1:

- `notes/step-09-events-and-original.md`: `entity.persist.post` on
  `Omeka\Entity\Media` fires once per new media item on the admin form, the
  REST API (with an item and onto an item) and the URL ingester, with the
  original stored at `files/original/<storage_id>.<extension>` and
  byte-identical to the upload; it fires inside Doctrine's flush.
- `notes/step-10-storage-and-after-flush.md` and ADR-0003: Doctrine's
  `postFlush` fires after the commit on all those routes and on
  `ApiManager::batchCreate()`; writing there works; an exception thrown
  there turns a committed upload into HTTP 500; a module table with a
  foreign key `ON DELETE CASCADE` follows deletes and uninstalls cleanly.
- `notes/step-11-loading-the-verifier.md`: no Composer `autoload.php` of the
  module may be loaded in Omeka S; an autoloader for the verifier's one
  namespace gives the CLI's verdict, byte for byte, on 16 fixtures inside
  Omeka.

The vocabulary is the verifier's: `validation_state` (`Trusted`, `Valid`,
`Invalid`) and the C2PA 2.4 §15 status codes, verbatim. The module adds two
states of its own and no more: `none` (the file has no manifest) and
`error` (the module could not check).

## Scope

**In scope**

- New media items whose Omeka-detected media type (`media.media_type`) is
  `image/jpeg`, `image/png` or `image/webp` and that have an original
  (`has_original`), on every route that persists media through Omeka S's
  entity manager.
- The original read from Omeka S's `Local` file store
  (`Omeka\File\Store\Local::getLocalPath('original/'.$media->getFilename())`).
- Verifying without trust settings (so `Trusted` cannot occur yet; trust
  settings are M3).
- The table `content_credentials_verdict` (ADR-0003): created in
  `install()`, dropped in `uninstall()`, mapped as an entity in
  `src/Entity`.
- The autoloader for `Provemark\C2paVerifier\` only, registered only when
  `Requirements::phpProblem(PHP_VERSION)` is `null`.
- **SPEC-001 amendment 1 (proposed here):** AC1 expects, besides the module
  row, the new table, empty; AC4 stays as it is (uninstall leaves the
  schema as it was). Confirmed together with this spec.

**Out of scope** (each needs its own spec before it may be built)

- Showing the verdict (M2), trust settings (M3), re-checking existing media
  (M4), the evidence document (M5), the AI label (M6), the release build
  and Strauss (M7).
- Checking in a background job (see Open questions).
- File stores other than `Local` (an S3 store module, for instance): such
  media get `error` / `unreadable` (AC8).
- Media whose original is replaced or changed after creation (core Omeka S
  offers no replace; step 09).
- Upgrading an installation of 0.1.0: no released version exists.
- Other formats the verifier reads (GIF, MP4, audio…).

## Behavior

Acceptance criteria as Given/When/Then. Each is individually testable and will be
covered by a Pest test tagged `->group('SPEC-002')`, in `tests/Unit` when it
needs no Omeka S and in `tests/Integration` (the Docker environment of
`tools/env/`) when it does. At least one criterion MUST be an error /
malformed-input path. This project fails closed: it never shows a verdict
the verifier did not give, a failure is stored as state `error` with a
reason, and the upload always proceeds. Everything read from a file is
untrusted text; a criterion that displays it says how it is escaped (the
Laminas `escapeHtml` view helper, or which other).

One row per checked media item in `content_credentials_verdict`:

| column | value |
|---|---|
| `media_id` | the media's id; primary key; foreign key to `media(id)` `ON DELETE CASCADE` |
| `state` | `Trusted`, `Valid`, `Invalid` (the verifier's `ValidationState` value), `none` (no manifest), or `error` (not checked) |
| `format` | the report's `format`, or `NULL` for `error` |
| `signer_issuer`, `signer_common_name` | from the active manifest's `signature_info` in `toArray()`, or `NULL` when there is none |
| `signed_at` | that `signature_info`'s `time` as the verifier gives it, or `NULL` |
| `codes` | JSON list: for `Invalid`, the `code` of every entry of `toArray()['validation_status']`, in order, as the CLI gives them; otherwise `[]` |
| `remote_manifest_url` | the report's `remoteManifestUrl` (never fetched), or `NULL` |
| `reason` | for `error`: `interrupted`, `unreadable`, `exception` or `requirements`; otherwise `NULL` |
| `verifier` | `Verifier::VERSION` of the verifier that checked |
| `checked_sha256` | SHA-256 of the bytes that were verified, or `NULL` when none were read |
| `checked_at` | UTC time of the check |

Text columns from the file (`format`, `signer_*`, `signed_at`, `codes`,
`remote_manifest_url`) are stored exactly as the verifier gives them,
through bound parameters; nothing in this spec displays them, M2 escapes
them on output.

- **AC1 — signed file: the CLI's verdict**
  - Given `fixture-signed.jpg`, `fixture-signed.png` and
    `fixture-signed.webp` from the verifier's fixtures
  - When each is uploaded through the admin item form
  - Then its row has `state`, `format`, `signer_issuer`,
    `signer_common_name` and `signed_at` equal to what
    `vendor/bin/c2pa-verify <file>` prints for the same file
    (`validation_state`, `format`, and the `signature_info` of the manifest
    named by `active_manifest`), `codes` `[]`, `checked_sha256` equal to
    the file's SHA-256, and `verifier` `0.10.0` (or the installed
    version's `Verifier::VERSION`)

- **AC2 — every route**
  - Given `fixture-signed.png`
  - When it is added through the REST API as an item with media, through
    `POST /api/media` onto an existing item, and through the URL ingester
    (served from inside the container, as in step 09)
  - Then each new media item has exactly one row, equal in `state` and
    `checked_sha256` to AC1's for that file

- **AC3 — unsigned file: `none`, not `Invalid`**
  - Given `fixture-unsigned.jpg`, `.png` and `.webp`
  - When each is uploaded
  - Then its row has `state` `none`, the signer columns `NULL` and `codes`
    `[]`, although the verifier's own state for these files is `Invalid`
    with `has_manifest` false and no status (measured 2026-11-04)

- **AC4 — altered file: `Invalid` with the CLI's codes** *(error path)*
  - Given a copy of `fixture-signed.jpg` with one byte of its image data
    changed (the test makes it: the byte 64 bytes before the end, XOR 0xFF;
    measured 2026-11-04: the CLI gives `Invalid` with
    `signingCredential.untrusted`, `assertion.dataHash.mismatch`)
  - When it is uploaded
  - Then its row has `state` `Invalid` and `codes` equal, in order, to the
    codes of the CLI's `validation_status` for the same altered file

- **AC5 — no verdict the verifier did not give**
  - Given any row with `state` `Trusted`, `Valid` or `Invalid`
  - Then that state is the verifier's `ValidationState` value for those
    bytes, unchanged; the module derives only `none` (from `hasManifest`
    false) and `error` (from its own failures)

- **AC6 — other media are left alone**
  - Given a GIF upload (a verifier GIF fixture), an `html` media item and a
    PDF upload (a minimal PDF the test writes)
  - When each is created
  - Then none has a row, and each is created as without the module

- **AC7 — deleting removes the row**
  - Given a checked media item, and an item with two checked media
  - When the media item is deleted, and the item is deleted
  - Then their rows are gone

- **AC8 — unreadable original: `error`, the upload proceeds** *(error path)*
  - Given a media item of a checked type whose original cannot be opened
    (missing, or a file store that is not `Local`)
  - When the check runs
  - Then its row has `state` `error`, `reason` `unreadable`,
    `checked_sha256` `NULL`, and the media item exists

- **AC9 — an exception: `error`, the upload proceeds** *(error path)*
  - Given verification that throws any `Throwable`
  - When the check runs
  - Then its row has `state` `error` and `reason` `exception`; the
    exception's message is not stored (it may hold paths); nothing is
    thrown out of the `postFlush` listener; the media item exists

- **AC10 — interrupted check leaves `error`, never nothing** *(error path)*
  - Given a check that stops after it started (a fatal error such as the
    memory or time limit cannot be caught)
  - Then the row that remains has `state` `error` and `reason`
    `interrupted`, because that row is written before verifying and
    replaced after

- **AC11 — the store fails: the upload proceeds** *(error path)*
  - Given `content_credentials_verdict` cannot be written (renamed away by
    the test, and back afterwards)
  - When a signed JPEG is uploaded through the admin item form
  - Then the response is the same redirect as without the module, the
    media item exists, and no exception reaches the response

- **AC12 — PHP below 8.3 at run time: no verifier loaded** *(error path)*
  - Given the module active while `Requirements::phpProblem(PHP_VERSION)`
    is not `null` (PHP downgraded after install)
  - When the check runs
  - Then no class of the verifier is loaded, and the row has `state`
    `error` and `reason` `requirements`

- **AC13 — the verifier's namespace only**
  - Given the module active on PHP 8.3
  - When a signed JPEG is uploaded
  - Then no file of the module's `vendor/` outside
    `vendor/provemark/c2pa-verifier/src/` has been included
    (`get_included_files()`), and Omeka's `Psr\Log\LoggerInterface` still
    comes from Omeka's own `vendor/`

- **AC14 — text from the file is stored exactly**
  - Given the signed fixtures of AC1
  - Then `signer_issuer`, `signer_common_name`, `signed_at` and
    `remote_manifest_url` read back from the database are byte-identical to
    the CLI's values, including any quote, backslash or non-ASCII character

## References

- Specification: C2PA Technical Specification 2.4, §15 (validation, status
  codes). Omeka S developer documentation: Modules, Server Events
  (`omeka.org/s/docs/developer/`). Omeka S 4.2.1 source:
  `Omeka\Db\Event\Subscriber\Entity`, `Omeka\File\TempFile`,
  `Omeka\File\Store\Local`, `Omeka\Service\EntityManagerFactory`; Doctrine
  ORM 2.20.8 `UnitOfWork::commit()`.
- Oracle: `provemark/c2pa-verifier` v0.10.0, `vendor/bin/c2pa-verify <file>`
  without settings, run by the test on the same bytes; the fixtures copied
  from the verifier's `tests/Fixtures/` into `tests/Fixtures/c2pa/` with
  their provenance and licence (MIT) in a README there. Measured on
  Omeka S 4.2.1, PHP 8.3, in the Docker environment.
- Reasoned: that a fatal error during the check leaves the media committed
  (the check runs after the commit); AC10 is tested by checking that the
  `interrupted` row exists when the verifier is called (a stand-in verifier
  in a unit test), not by exhausting memory. AC8, AC9 and AC12 are unit
  tests with stand-ins for the file store, the verifier and the PHP
  version; the integration tests cover the wiring.

## API sketch

Illustrative only.

```php
namespace ContentCredentials;

// Registers spl_autoload for 'Provemark\\C2paVerifier\\' only; the string
// is rewritten by Strauss in the release (step 11), the path by the build.
final class VerifierLoader { public static function register(string $moduleDir): void; }

namespace ContentCredentials\Check;

final readonly class Verdict { /* the row's values; fromReport(), error(reason) */ }

final class MediaCheck
{
    /** @param callable(resource): VerificationReport $verify */
    public function check(?string $originalPath, callable $verify, string $phpVersion): Verdict;
}

// Notes new media in entity.persist.post; on postFlush writes the
// `interrupted` row, checks, replaces the row; catches every Throwable.
final class AfterFlushRecorder { public function postFlush(PostFlushEventArgs $args): void; }

namespace ContentCredentials\Entity;

/** @Entity @Table(name="content_credentials_verdict") */
class Verdict { /* media (Id, OneToOne, onDelete CASCADE), columns above */ }
```

Deptrac gets a layer per new directory (`Check`, `Entity`, the listener)
before its first file.

## Open questions

- **In the request or in a job?** Verifying in the `postFlush` listener
  keeps the verdict there the moment the upload is done and needs no job
  machinery; it adds the verification time to the upload request (not
  measured for large files). A job (`Omeka\Job\AbstractJob`) keeps the
  upload fast but needs its own measurement (does a failing job ever touch
  the upload?) and leaves media unchecked until it runs. Recommendation:
  in the request now, as Tracefern did first (its SPEC-001; the background
  came later, its SPEC-013), and a job with M4. *Blocker for approval.*
  *Status 2026-11-04:* answered, in the request, the recommendation
  approved by Maurice van Loon with the spec.
- **The fixtures in the repository:** copying the six signed and unsigned
  fixtures (249,809 bytes together, measured) and one GIF into
  `tests/Fixtures/c2pa/`, or reading them from elsewhere? The installed
  package has no `tests/` (`ls vendor/provemark/c2pa-verifier`).
  Recommendation: copy, with a README naming the verifier's tag (v0.10.0)
  and licence. The altered JPEG and the PDF are made by the tests.
  *Non-blocker.*
  *Status 2026-11-04:* answered, copied, the recommendation approved by
  Maurice van Loon with the spec.

## Amendments

- **Amendment 1 (2026-11-04, approved by Maurice van Loon with SPEC-004):**
  the table gains a column `trust VARCHAR(64) NULL`, after `verifier`, and
  each verdict is produced with the trust settings of SPEC-004 (the
  `trust` value names them; NULL for an `error` before verification). The
  `CREATE TABLE` statement changes; there is no `upgrade()` before the
  first release. Was: verified without settings, no `trust` column. The
  criteria above are unchanged; their fixtures give the same verdicts with
  the bundled lists (SPEC-004, Amendments).
- **Amendment 2 (2026-11-05, approved by Maurice van Loon with SPEC-007):**
  the table gains a column `ai_statement VARCHAR(16) NULL`, after `trust`:
  `generated`, `edited` or NULL by SPEC-007's rule, set only when the state
  is `Trusted` or `Valid`. The `CREATE TABLE` statement changes; there is no
  `upgrade()` before the first release. Was: no such column. The criteria
  above are unchanged. Tested under SPEC-007.
- **Amendment 3 (2026-11-06, approved by Maurice van Loon with SPEC-008):**
  AC13 follows the folder the module runs on, as the release zip has
  `vendor-prefixed/` and no `vendor/`: of that folder, only
  `provemark/c2pa-verifier/src/` is included, and at least one file from there.
  Omeka's `Psr\Log\LoggerInterface` as before. Was: "no file of the module's
  `vendor/` outside `vendor/provemark/c2pa-verifier/src/`".

## Traceability

Filled when status becomes `implemented`. Every acceptance criterion maps to at
least one test; every source file maps back to this spec.

| Acceptance criterion | Test (file :: name / group) | Source (file/symbol) |
|----------------------|-----------------------------|----------------------|
| AC1 | tests/Integration/VerifyOnUploadTest.php :: AC1: a signed file gets the CLI's verdict / SPEC-002 | src/Check/Verdict.php :: Verdict::fromReport(); src/Check/MediaCheck.php :: check(); src/Omeka/AfterFlushListener.php; src/Omeka/DbalVerdictStore.php; src/Omeka/Schema.php; Module.php :: install(), attachListeners() |
| AC2 | tests/Integration/VerifyOnUploadTest.php :: AC2: every route gets exactly one row / SPEC-002 | src/Omeka/AfterFlushListener.php :: note(), postFlush() |
| AC3 | tests/Unit/VerifyOnUploadTest.php :: AC3: an unsigned file is none, not Invalid; tests/Integration/VerifyOnUploadTest.php :: AC3: an unsigned file is none, not Invalid / SPEC-002 | src/Check/Verdict.php :: Verdict::fromReport() |
| AC4 | tests/Unit/VerifyOnUploadTest.php :: AC4: an altered file is Invalid with the CLI's codes; tests/Integration/VerifyOnUploadTest.php :: AC4: an altered file is Invalid with the CLI's codes / SPEC-002 | src/Check/Verdict.php :: Verdict::fromReport() |
| AC5 | tests/Unit/VerifyOnUploadTest.php :: AC5: every state is the verifier's, or none for a file without a manifest / SPEC-002 | src/Check/Verdict.php :: Verdict::fromReport(), Verdict::error() |
| AC6 | tests/Integration/VerifyOnUploadTest.php :: AC6: other media are left alone / SPEC-002 | src/Omeka/AfterFlushListener.php :: MEDIA_TYPES, note() |
| AC7 | tests/Integration/VerifyOnUploadTest.php :: AC7: deleting the media or its item removes the row / SPEC-002 | src/Omeka/Schema.php :: create() (the foreign key) |
| AC8 | tests/Unit/VerifyOnUploadTest.php :: AC8: an original that cannot be opened is error / unreadable / SPEC-002 | src/Check/MediaCheck.php :: check(); src/Omeka/AfterFlushListener.php :: originalPath() |
| AC9 | tests/Unit/VerifyOnUploadTest.php :: AC9: an exception from the verifier is error / exception, its message not kept; AC9: an exception anywhere in recording never leaves the recorder / SPEC-002 | src/Check/MediaCheck.php :: check(); src/Check/Recorder.php :: record(); src/Omeka/AfterFlushListener.php :: note(), postFlush(); Module.php :: attachListeners() |
| AC10 | tests/Unit/VerifyOnUploadTest.php :: AC10: the interrupted row is written before the verifier runs / SPEC-002 | src/Check/Recorder.php :: record() |
| AC11 | tests/Unit/VerifyOnUploadTest.php :: AC11: a store that fails is reported, never thrown; tests/Integration/VerifyOnUploadTest.php :: AC11: a store that cannot be written leaves the upload alone / SPEC-002 | src/Check/Recorder.php :: record(); src/Omeka/AfterFlushListener.php :: create() (the failure is logged by class only) |
| AC12 | tests/Unit/VerifyOnUploadTest.php :: AC12: below PHP 8.3 the verifier is not loaded: error / requirements / SPEC-002; .github/workflows/ci.yml :: parses on PHP 8.1 / 8.2 | src/Check/MediaCheck.php :: check() |
| AC13 | tests/Integration/VerifyOnUploadTest.php :: AC13: only the verifier's own source is loaded from vendor/ / SPEC-002 (tests/Integration/Support/included-files.php) | src/Omeka/VerifierLoader.php :: register(); src/Entity/StoredVerdict.php; config/module.config.php |
| AC14 | tests/Integration/VerifyOnUploadTest.php :: AC14: text from the file is stored exactly / SPEC-002 | src/Omeka/DbalVerdictStore.php :: write() (bound parameters); src/Omeka/Schema.php (LONGTEXT) |
