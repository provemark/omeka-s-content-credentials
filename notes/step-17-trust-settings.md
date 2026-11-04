# Step 17 (M3.5): SPEC-004, trust settings

2026-11-04. SPEC-004 (approved 2026-11-04, with SPEC-002 amendment 1 and
SPEC-003 amendment 1) is implemented. Every new upload is now verified
against the bundled C2PA lists, with the DigiCert root unless an
administrator turns it off, or against custom settings that replace them.
Each row records the trust source, and the block names it. Measured on
Omeka S 4.2.1, PHP 8.3.36, in the Docker environment of `tools/env/`.

## What was built

- `trust/`: the three files of ADR-0004, unchanged from Tracefern's copy,
  and `trust/README.md` (source, commit, SHA-256, the CC BY 4.0 notice,
  the DigiCert fingerprint, how to refresh).
- `src/Trust/` (a new Deptrac layer `Trust`, no Omeka S):
  - `BundledLists`: the record of ADR-0004, with `isStale()` and
    `source()`.
  - `TrustChoice`: `build()`, `bundledJson()` (the verifier's settings
    recipe) and `refusal()`.
  - `BuiltTrust`: the settings, the `trust` value and, when the
    settings could not be built, the reason.
- `src/Check/`: `MediaCheck` takes a third closure that gives the
  `BuiltTrust` for a check. A failure there means verifying without
  settings with `trust` `none`, never `error`. `Verdict` has `trust`;
  it is null for an error before verification.
- `src/Display/`: `Presentation` has the trust line and the unreadable
  rule of SPEC-003 amendment 1. `SettingsPage` holds the configure page's
  markup.
- `src/Omeka/`:
  - `TrustConfig`: the two Omeka settings, `built()`, `form()` and
    `save()`.
  - The column `trust VARCHAR(64)` in `Schema`, `DbalVerdictStore`,
    `StoredVerdict` and `VerdictReader`.
  - `AfterFlushListener` builds the settings for each check.
- `Module.php`:
  - `getConfigForm()` and `handleConfigForm()`. A refused text is kept
    for the re-rendered form, and the error goes to Omeka's messenger as
    plain text, which Omeka escapes.
  - `uninstall()` deletes both settings. The keys are written out there,
    since `src/` is not loaded during uninstall.
- `tools/trust/compare.sh`: run on 2026-11-04, it found upstream `d6dec63`
  (2026-10-26) with both lists equal to the bundled ones.
- Fixtures: the Pixel 10 photo, `c2pa-rs-ocsp.jpg` and
  `c2pa-rs-test-trust-anchors.pem`, with the c2pa-rs licence files;
  SHA-256 equal to Tracefern's copies.

## Tests, red first

- Unit (`tests/Unit/TrustSettingsTest.php`, 38 tests with their
  datasets), before `src/Trust/` and `trust/` existed: `Tests: 37
  failed, 1 warning, 1 passed`.
  - Missing classes: `Class "…\Trust\TrustChoice" not found` (14),
    `…\BundledLists` (6), `…\Display\SettingsPage` (4).
  - The empty `trust/`: the DigiCert fingerprint came out as SHA-256 of
    nothing, `E3:B0:C4:42:…`.
  - The trust line was missing: `Failed asserting that null is identical
    to 'Trust: …'` (5). Unknown trust values were not unreadable (5), and
    the Pixel 10 photo was `Invalid` instead of `Trusted` without
    settings.
  - The warning was `Undefined property …Verdict::$trust`.
  - The one test that passed was the trust line's "NULL" case: no line
    when there is no source, which held before.
- Integration (`tests/Integration/TrustSettingsTest.php`, 11): `Tests:
  10 failed, 1 passed`. AC9 passed: it asserts that no age warning
  shows, which holds for a module without a configure page. To see it
  fail, `BundledLists::COMPARED` was set to `2026-01-01`: `Tests: 1
  failed`. With the real date back: 1 passed.
- After: 127 unit tests and 42 integration tests green; `composer check`
  green; the new and changed files lint on PHP 8.2.35.

## Measured on the way

- **PHP's default upload limit refuses the Pixel 10 photo.** The PHP 8.3
  image has `upload_max_filesize=2M` and `post_max_size=8M` (measured
  with `ini_get` in the container). Omeka answered the 5.8 MB upload with
  its form again (HTTP 200, no item). The test environment's Dockerfile
  now sets both to 64M. A real installation with the default limit
  refuses such a file before this module sees it. That is Omeka's and the
  server's limit, not the module's.
- **A 1 MiB textarea exceeds PCRE's backtrack limit.** My test read the
  textarea back with a lazy regex, which gives up on 1 MiB
  (`pcre.backtrack_limit=1000000`, measured on the host and in the
  container) and returned nothing. It now uses `strpos`. The page itself
  was right.
- **My test helper did not read the new column.** `verdictRow()` lists
  its columns, so AC6 saw `trust` as null while the row held `none`. The
  column was added.
- The verifier's reasons, measured: "the trust settings are not valid
  JSON: Syntax error"; for a top-level `allowed_list`, "trust.allowed_list
  is not read at the top level: …". Neither echoes the submitted text.
  They are shown escaped all the same.
- With the module upgraded in place, the test environment's table lacked
  the column. The integration tests' helper uninstalls and installs again
  when `trust` is missing, as SPEC-004 decided (no `upgrade()` before the
  first release). Any other local installation must do the same.

## Not measured

- A checkbox posted without the hidden field. The page always sends the
  hidden `0`, and only `'1'` turns DigiCert on, so a missing field means
  off.
- The configure page in a browser; the tests read the HTML.
- The time a check takes with settings in a real upload (step 16 measured
  about 15 ms to build them, in a script).
