# Step 12 (M1.5): SPEC-002 implemented, verify on upload and store the result

2026-11-04. SPEC-002 was approved with both recommendations (verify in the
request; copy the fixtures), and with it SPEC-001 amendment 1. This step
wrote the tests, saw them red, built the module's first behaviour, and saw
them green.

## What was built

| layer (Deptrac) | file | what |
|---|---|---|
| Check | `src/Check/Verdict.php` | one verdict: the verifier's state, or `none` (no manifest) or `error` with a reason |
| Check | `src/Check/MediaCheck.php` | from a path to a verdict; never throws; loads the verifier only when the PHP version allows |
| Check | `src/Check/Recorder.php` | writes `error` / `interrupted`, checks, writes the verdict; every failure to a callback |
| Check | `src/Check/VerdictStore.php` | the store's interface |
| Omeka | `src/Omeka/AfterFlushListener.php` | notes new JPEG, PNG and WebP media in `entity.persist.post`, records them in Doctrine's `postFlush` |
| Omeka | `src/Omeka/DbalVerdictStore.php` | `INSERT … ON DUPLICATE KEY UPDATE`, bound parameters |
| Omeka | `src/Omeka/Schema.php` | the table, its foreign key `ON DELETE CASCADE` |
| Omeka | `src/Omeka/VerifierLoader.php` | an autoloader for `Provemark\C2paVerifier\` only |
| Entity | `src/Entity/StoredVerdict.php` | the table as Doctrine maps it, for reading (M2) |

`Module.php` creates the table in `install()` (after the PHP check),
drops it in `uninstall()`, and hands every new media item to the listener.
`config/module.config.php` registers the entity mapping. Deptrac got the
three layers before their first file: `Check` may use `Requirements`,
`Omeka` may use `Check`, `Entity` and `Requirements`, `Entity` nothing.
PHPStan scans Omeka's Doctrine packages one by one (not
`doctrine/deprecations`, which this repository's `vendor/` has at another
version).

Everything in `src/` stays parseable on PHP 8.1, because a module installed
on 8.3 and left in place after a downgrade still runs it (AC12): no
readonly classes, no typed constants. Checked with `php -l` on the PHP 8.2
container; CI's parse job now covers all of `src/` on 8.1 and 8.2.

Tests: `tests/Unit/VerifyOnUploadTest.php` (AC3–AC5, AC8–AC12, stand-ins
for the verifier, the store and the PHP version where the criterion
needs them), `tests/Integration/VerifyOnUploadTest.php` (AC1–AC4, AC6,
AC7, AC11, AC13, AC14), `tests/Support/VerifierCli.php` (the oracle:
`vendor/bin/c2pa-verify` on the same bytes), and
`tests/Integration/Support/included-files.php` (AC13, run inside the
container). The fixtures are in `tests/Fixtures/c2pa/`, each compared with
the verifier's tag v0.10.0 (README there).

## Red, before any implementation

`vendor/bin/pest --testsuite=Unit --group=SPEC-002`:

```
   Error
  Interface "ContentCredentials\Check\VerdictStore" not found
  at tests/Unit/VerifyOnUploadTest.php:62
```

`vendor/bin/pest --testsuite=Integration` against the module of M0:
SPEC-001's AC1 and all 15 SPEC-002 tests failed, for example

```
  ⨯ it AC1: installing adds one row and one empty table
  ⨯ it AC1: a signed file gets the CLI's verdict with ('jpg')
  …
  ⨯ it AC14: text from the file is stored exactly with ('webp')
  Exit 1 from docker compose exec -T db mariadb … SELECT JSON_OBJECT('media_id', …
```

The uploads themselves succeeded in that run (the new test support worked
up to the missing table).

## Green, and what turned up on the way

After the implementation the unit tests passed at once. The integration
run first gave five failures, all in the tests, none in `src/`:

- AC3: `$row['signer_issuer'] ?? 'missing'` turns a real `NULL` into
  "missing". Fixed with `toHaveKeys()` and a plain comparison.
- AC4: the `mariadb` client escapes backslashes in batch output, which
  broke the JSON of `JSON_OBJECT()` as soon as a value held a quote.
  Fixed with `--raw`; AC14 depends on exactly that.
- AC13: the URL ingester received `false`. Measured: Omeka S's
  `application/config/application.config.php` sets `$url` in the scope it
  is read in (line 6, `$url = getenv('OMEKA_DB_CONNECTION_URL');`), which
  overwrote the script's own `$url`, first in the global scope and then,
  moved into a function, in that function. Fixed by naming the function's
  parameters `$omekaRoot` and `$ingestUrl`.

Then `composer check` (2026-11-04, host PHP 8.5.12, the containers on
PHP 8.3.36 and 8.2.35):

```
OK: 3 spec(s), 5 test file(s)
{"tool":"pint","result":"passed"}
 [OK] No errors
  Violations           0
  Tests:    47 passed (157 assertions)
  Tests:    20 passed (164 assertions)
```

## Measured by the tests

- AC1, AC14: in Omeka S 4.2.1, `fixture-signed.jpg`, `.png` and `.webp`
  uploaded through the admin item form get `Valid`, the CLI's `format`,
  issuer and common name, `codes` `[]`, the file's SHA-256 and verifier
  `0.10.0`; the text read back is byte-identical to the CLI's.
- AC2: the REST API (item with media, media onto an item) and the URL
  ingester each give exactly one row with the same state and SHA-256.
- AC3: the unsigned files are `none`, where the verifier says `Invalid`.
- AC4: the altered JPEG is `Invalid` with the CLI's codes in order.
- AC6: a GIF, a PDF and an `html` media get no row.
- AC7: the rows go with their media, and with an item's media.
- AC11: with the table renamed away, the admin form still redirects to the
  new item, which exists.
- AC13: after an upload in an Omeka S process, no file of `vendor/`
  outside `vendor/provemark/c2pa-verifier/src/` was included, Omeka's
  `Psr\Log\LoggerInterface` came from Omeka's `vendor/`, and the entity
  mapping read the new row back (`Valid`).

## Read or reasoned, not measured

- AC8, AC9, AC10 and AC12 are tested with stand-ins (unit tests), as the
  spec's References say; their wiring in Omeka is the same code path as
  the measured criteria.
- A row of `error` / `requirements` has `verifier` `NULL`: no verifier was
  loaded, so none checked. The spec's table says "the verifier that
  checked"; this is how that reads for a check that did not run.
- How long verifying adds to an upload of a large file: still not
  measured (SPEC-002's first open question, answered "in the request").
