# Notes

Decisions and open questions for Content Credentials (C2PA), a module for
Omeka S. Each entry says what is measured (a command was run) and what is
reasoned (read or concluded). The record of each step is in `notes/`; this
file holds the decisions and an index.

What the module does: it verifies the Content Credentials (C2PA) of every
uploaded media file with `provemark/c2pa-verifier`, stores the verdict with
the media and shows it in the admin interface. It only checks: it never
signs, never blocks an upload and opens no network connection. Later
(milestone M5) it keeps the verifier's evidence document behind each
verdict, so that what held when a file came in stays on record after a
certificate has expired.

## Decisions (2026-11-03)

### 1. Name, module directory, namespace

| what | value |
|---|---|
| display name | Content Credentials (C2PA) |
| module directory | `ContentCredentials` |
| PHP namespace | `ContentCredentials` |

Descriptive, as Omeka S modules usually are (`FileSideload`,
`ImageServer`), and the same name as the Drupal module
`content_credentials`. It does not depend on the ProveMark name, which may
change.

Measured 2026-11-03: the module list on `omeka.org/s/modules/` (189
modules, one page, fetched with `curl`) has no module whose name or text
contains "C2PA", "Content Credentials", "provenance" or "Tracefern".
`gh search repos` finds nothing for "ContentCredentials omeka", "c2pa
omeka" or "omeka content credentials".

Reasoned, not measured: that the namespace must equal the module
directory. To measure in M0 against the pinned Omeka S.

### 2. Licence: GPL-3.0-or-later

The licence of Omeka S and of the modules in `omeka-s-modules`. The bundled
verifier is MIT, which combines with it.

Measured 2026-11-03 (`gh api repos/<repo>/license`): `omeka/omeka-s`,
`omeka-s-modules/FileSideload`, `CSVImport` and `Mapping` are GPL-3.0;
`Daniel-KM/Omeka-S-module-Common` is CeCILL-2.1. The page "Register an
add-on" on omeka.org states no licence requirement (it asks for a release
zip and a directory name that matches).

Measured 2026-11-03 (M0.2): Omeka S's README says "GNU General Public
License, version 3 (GPLv3)", without "or later". Its `LICENSE` and the text
at `gnu.org/licenses/gpl-3.0.txt` differ only in four URLs (`http` against
`https`, one older path); this repository's `LICENSE` is the gnu.org text.
The module says "or later" either way, which includes version 3.

### 3. Storing the verdict: a module-owned table (direction; ADR to follow)

A Doctrine entity and table owned by the module, with a foreign key to the
media (on delete cascade), as the official Mapping module keeps its own
entities. Users cannot edit it, the browse filter (M2) and the re-check
(M4) can query it, and uninstall removes it.

Rejected: Omeka values on the media (editable, so a shown verdict need not
come from the verifier; and it mixes untrusted text from the file into the
catalogue's metadata); the media's `data` column (owned by the ingester,
possibly overwritten on update, and hard to filter on).

Reasoned, not measured: all of the above. The ADR is written after the
mechanism (entity registration, install and uninstall SQL, migrations) has
been measured against the pinned Omeka S.

### 4. Trust lists: as Tracefern

- The module bundles `C2PA-TRUST-LIST.pem` (trust kind `manifest`) and
  `C2PA-TSA-TRUST-LIST.pem` (trust kind `tsa`) from
  [c2pa-org/conformance-public](https://github.com/c2pa-org/conformance-public/tree/main/trust-list)
  (CC BY 4.0), and shows the date of the copy in the settings.
- An administrator can paste a trust-settings JSON (the verifier's format).
  It **replaces** the bundled lists; nothing is merged.
- DigiCert Trusted Root G4 is a separate `tsa` anchor behind its own
  option, **on by default**. Turning it on means accepting the time
  DigiCert's timestamp authorities put on a signature.
- The settings warn when the bundled copy is older than about six months.
  The module never fetches a list itself.

Read, not measured here: the verifier's `docs/trust-settings.md` (measured
there on 2026-09-25): without the DigiCert anchor, every Adobe Firefly or
Microsoft Bing file whose signer has expired is `Invalid`. Built in M3.

### 5. Evidence: in the database, its own milestone (M5)

The evidence document (`evidenceToJson()`, format version 1) is stored in a
module-owned table of its own, one row per check, never updated, with the
check's date and the verifier's version. A re-check (M4) therefore never
overwrites what held when the file came in. It travels with the database
backup an institution already makes. Built in M5; the table design of M1
leaves room for it.

Read in the verifier's `docs/evidence-format.md`, not measured:
`evidenceToJson()` writes every character outside ASCII as `\uXXXX`, so
the document is ASCII and a text column can keep it byte-identical
whatever the character set.

Rejected: writing it in M1 already (M1 grows, and without M5's viewing and
downloading it is not usable); a file next to the original through Omeka's
file store (its store API takes a local source path, so a temporary file,
to measure; files can be orphaned from their row; no filtering or counting
without the database).

### 6. Distribution

Local until M0 is done; then a private GitHub repository, so CI (PHP 8.3,
8.4, 8.5) runs from M1 on. Its owner and name are asked for at that moment,
with a view to the renaming of ProveMark. Public, and registration on
omeka.org, only with Maurice's go.

**Done 2026-11-04:** private repository
`provemark/omeka-s-content-credentials` (chosen by Maurice), next to the
sister projects; the release zip's directory stays `ContentCredentials`
whatever the repository is called. Before the first push, the eleven local
commits were rewritten (`git filter-repo --mailmap`) from the author and
committer address of the global git configuration to
`16209371+Maurice1969DB@users.noreply.github.com`, the address the
verifier's history uses, and this repository's `user.email` set to it.
Measured: the GitHub API lists that address, and only that one, as author
and committer of every pushed commit.

## Index of steps

| step | note |
|---|---|
| M0.3 | [`notes/step-03-test-environment.md`](notes/step-03-test-environment.md): Omeka S 4.2.1 pinned, the Docker environment, a failed install that looks installed |
| M0.4 | [`notes/step-04-dependencies.md`](notes/step-04-dependencies.md): ADR-0001 PHP 8.3 floor, ADR-0002 dependencies, `composer.json`; `omeka/omeka-s` cannot be required |
| M0.5 | [`notes/step-05-module-skeleton.md`](notes/step-05-module-skeleton.md): the empty module installs and uninstalls cleanly; namespace must equal the directory; never load the module's whole `vendor/` |
| M0.6 | [`notes/step-06-spec-check.md`](notes/step-06-spec-check.md): the spec template; SPEC-000 implemented, `bin/spec-check.php` the first step of `composer check` |
| M0.7a | [`notes/step-07-composer-check.md`](notes/step-07-composer-check.md): Pint, PHPStan at max with Omeka S scanned from the pinned release, Deptrac; Deptrac does not enforce a layer per file |
| M0.7b | same note, second part: SPEC-001 implemented; a module's `src/` is not autoloaded during its own install |
| CI | [`notes/step-08-ci.md`](notes/step-08-ci.md): GitHub Actions, static checks and unit tests on PHP 8.3–8.5, the integration tests, a parse check on 8.1 and 8.2; first run green |
| M1.1 | [`notes/step-09-events-and-original.md`](notes/step-09-events-and-original.md): `entity.persist.post` on `Omeka\Entity\Media` fires once per new media on every measured route, original present and byte-identical; `api.create.post` on the media adapter does not (the brief's candidate); deleting an item gives no media `api.delete.post` |
| M1.2 | [`notes/step-10-storage-and-after-flush.md`](notes/step-10-storage-and-after-flush.md): a module table with `ON DELETE CASCADE` installs and uninstalls cleanly and follows deletes; Doctrine `postFlush` fires after the commit on every route; an exception there gives HTTP 500 for a committed upload; ADR-0003 accepted |
| M1.3 | [`notes/step-11-loading-the-verifier.md`](notes/step-11-loading-the-verifier.md): the whole development `vendor/` breaks Omeka's logger (fatal), and any Composer `autoload.php` kills PHP 8.2; an autoloader for the verifier's namespace only gives the CLI's verdict on 16 fixtures in Omeka; Strauss rewrites namespace strings, not paths |
| M1.4 | `specs/SPEC-002-verify-on-upload.md`: the M1 spec; approved 2026-11-04 with SPEC-001 amendment 1 |
| M1.5 | [`notes/step-12-verify-on-upload.md`](notes/step-12-verify-on-upload.md): SPEC-002 implemented; every new JPEG, PNG and WebP gets the CLI's verdict, stored after the commit; Omeka's `application.config.php` sets `$url` in its caller's scope |
| M2.1 | [`notes/step-13-admin-views.md`](notes/step-13-admin-views.md): `view.show.sidebar`, `view.details`, a column type added through `column_defaults`, `view.advanced_search` / `view.search.filters` / `api.search.query` for a filter; output through `escapeHtml` arrives escaped; an unknown `sort_by` is ignored |
| M2.2 | `specs/SPEC-003-show-the-result.md`: the M2 spec; approved 2026-11-04 with both recommendations (filter on admin requests only; the column for administrators with own columns accepted) |
| M2.3 | [`notes/step-14-show-the-result.md`](notes/step-14-show-the-result.md): SPEC-003 implemented; Pest's `toContain()` takes needles, not a message; Omeka echoes the query, escaped, in its own Advanced search link; `Status::isAdminRequest()` is false for the anonymous REST API (measured) |
| M3.1 | [`notes/step-15-settings.md`](notes/step-15-settings.md): the configure page is global-admin only and CSRF-checked; a refused form loses the posted text unless the module restores it; a 74 KB setting reads back exactly, also in `postFlush`; every request decodes all settings; a `view.layout` warning shows on every admin page; a module in `needs_upgrade` is not loaded; uninstall leaves a module's settings |
| M3.2 | [`notes/step-16-trust-lists.md`](notes/step-16-trust-lists.md): Tracefern's list copy is byte-identical to upstream (`47009ee`, unchanged at `d6dec63`); library equals CLI on 52 file × settings pairs; Pixel 10 and OpenAI `Trusted` by default; DigiCert decides Amazon Titan, Lightroom and `c2pa-rs-ocsp`; `fromJson()` costs about 15 ms per upload; the OpenAI image turns `Invalid` on 2027-04-23 |
| M3.3 | [`docs/adr/ADR-0004-bundled-trust-lists.md`](docs/adr/ADR-0004-bundled-trust-lists.md) (accepted 2026-11-04): the three files in `trust/`, unchanged, hashes recorded in code and checked by a test; two dates shown, the age warning (183 days) counted from the last comparison with upstream; a development script compares before a release |
| M3.4 | `specs/SPEC-004-trust-settings.md`: the M3 spec; approved 2026-11-04 with all recommendations, with SPEC-002 amendment 1 (the `trust` column) and SPEC-003 amendment 1 (the trust line) |
| M3.5 | [`notes/step-17-trust-settings.md`](notes/step-17-trust-settings.md): SPEC-004 implemented; PHP's default 2M upload limit refuses the Pixel 10 fixture (test environment raised to 64M); a 1 MiB textarea exceeds PCRE's backtrack limit in a test regex |
| M4.1 | [`notes/step-18-jobs.md`](notes/step-18-jobs.md): `PhpCli` jobs run as their own process in the container; a throw or a fatal error ends `error` with the message and paths in the job log, which site admins can read; Stop works only through `shouldStop()`; a job that cannot start is `error` while the page reports success; `Synchronous` blocks the request |
| — | [`notes/step-19-configure-link.md`](notes/step-19-configure-link.md): SPEC-004 amendment 1; Omeka shows the Configure link only with `configurable = true` in `module.ini`, which was missing; installing then redirects to the configure page (SPEC-001 amendment 2) |
| M4.2 | [`notes/step-20-recheck-at-scale.md`](notes/step-20-recheck-at-scale.md): the module's check as a job: 3 097 media in 14.9 s, memory flat at 18 MB; trust built once per run (per item triples the time); stop and carry on by id works; two runs at once and an upload during a run do no harm |
| M4.4 | [`notes/step-21-recheck.md`](notes/step-21-recheck.md): SPEC-005 implemented; runs started from the configure page, one at a time, with the trust settings of the start; the job log holds only the module's own lines; opcache delays a change to `local.config.php` by up to 2 s (measured) |

## To measure before a spec rests on it

From the brief, each to become a measured note against the pinned
Omeka S 4.2.x:

- the module skeleton (`config/module.ini`, `Module.php`,
  `config/module.config.php`), and whether the namespace must equal the
  directory;
- where the original lives, and whether it is byte-identical to the upload
  on every route: measured for the admin form, the API and the URL
  ingester (step 09); CSV Import and File Sideload not yet;
- which event fires once per new media item with the file available, and
  which on update and delete: measured (step 09); the hook after the
  commit: Doctrine `postFlush` (step 10);
- background jobs, and whether a failing job can break an upload;
- storage: entity registration, install and uninstall SQL: measured
  (step 10), ADR-0003; migrations between versions not yet;
- display: view events or helpers for the media page and the browse:
  measured (step 13);
- the PHP floor: Omeka S 4.2 from PHP 8.1, the verifier `^8.3` (an ADR);
- bundling: Strauss prefixing of the verifier, as Tracefern's SPEC-009:
  measured on a build copy (step 11); the build itself is M7.
