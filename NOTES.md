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

**Decided 2026-11-07 (Maurice):** the repository stays
`provemark/omeka-s-content-credentials` when it goes public; it does not
wait for a new name.

**Public since 2026-11-07,** at Maurice's go ("doe punt 3 en 2"), after the
check of step 46 and his consent that his name and quoted instructions become
public. With it: private vulnerability reporting on, Projects off, topics
`omeka-s`, `omeka-s-module`, `c2pa`, `content-credentials`. Measured: `gh api`
shows `public` and reporting `true`; every external link in `README.md`,
`SECURITY.md` and `trust/README.md` answers 200 without logging in.
Registration on omeka.org is still his go.

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
| — | [`notes/step-22-settings-texts.md`](notes/step-22-settings-texts.md): SPEC-004 amendment 2; the configure page's note and warning now name SPEC-005's runs |
| M5.1 | [`notes/step-23-evidence-document.md`](notes/step-23-evidence-document.md): a report, so a document, for every input (empty, noise, truncated included); 194 B without a manifest to 35 KB for two manifests; ASCII; byte-identical for a fixed time and across PHP 8.3 and 8.5; by the clock only `judged_at` changes; `trust_lists` empty with a settings document; `fromTrustLists()` gives the same verdicts; a file without a manifest says `Invalid` where the module shows `none`; under 0.2 ms to write |
| M5.2 | [`notes/step-24-evidence-in-omeka.md`](notes/step-24-evidence-in-omeka.md): an admin route needs its own `__NAMESPACE__` (else a silent 404); Omeka registers the controller as an ACL resource; reading the media item through the API gives evidence access identical to the media page, an ACL rule alone does not; `LONGTEXT` and the download give the bytes back (3 303 rows, 5 MiB); about 6 MB on disk per run over 1 100 media; the foreign key removes evidence with its media item |
| M5.3 | [`docs/adr/ADR-0005-evidence-table.md`](docs/adr/ADR-0005-evidence-table.md) (accepted 2026-11-05): a second, append-only table through DBAL, with the file's and the settings' SHA-256 next to the document; written in one transaction with the verdict; `fromJson()` kept; deleted with its media item and on uninstall; served through a route that reads the media item through the API first |
| M5.4 | `specs/SPEC-006-keep-the-evidence.md`: the M5 spec; approved 2026-11-05 with all recommendations (access follows the media item; every check adds a row; no row for `none`), with SPEC-003 amendment 2 (the evidence line in the block) |
| M5.5 | [`notes/step-25-keep-the-evidence.md`](notes/step-25-keep-the-evidence.md): SPEC-006 implemented; SPEC-001 amendment 3 (the second table); the verdict and its evidence in one transaction, tested from both sides; Omeka hides an exception's message on its 404 page; an installation without the table stores `error`/`interrupted` until it is installed again |
| M6.1 | [`notes/step-26-ai-statement.md`](notes/step-26-ai-statement.md): the statement in `toArray()` with verifier 0.10.0; a tampered image keeps it, so the verdict must gate the label; the active manifest alone misses parent-chain and ocsp; Titan is `Valid` with the statement to at least 2045, OpenAI turns `Invalid` in April 2027; `fixture-signed` turns `Invalid` on 2030-08-26, which dates this module's own tests |
| M6.2 | `specs/SPEC-007-ai-label.md`: the M6 spec; approved 2026-11-05 with all recommendations, SPEC-002 amendment 2 and SPEC-003 amendment 3 |
| M6.3 | [`notes/step-27-the-ai-label.md`](notes/step-27-the-ai-label.md): SPEC-007 implemented, with its amendments 1 and 2; `process-timeout` 0 in `composer.json`; not looked at in a browser by the assistant; no real file is `Trusted` with a label in the tests |
| M7.1 | [`notes/step-28-the-release-build.md`](notes/step-28-the-release-build.md): a Strauss-prefixed zip installs on a clean Omeka S and refuses PHP 8.2; 10 of 10 fixtures agree with the unprefixed verifier, documents byte-identical; the integration suite gives 105 of 106 against it (AC13 names the folder); without the prefix the module silently uses another module's copy; a forgotten path fails closed on every upload; Strauss 0.30.0 and 0.31.1 give the same tree |
| M7.2 | [`notes/step-29-uninstall.md`](notes/step-29-uninstall.md): after install, use and uninstall only the module's job rows are left (nothing refers to them); a run under an uninstall ends `completed` with thousands of "not stored", and after a reinstall it fills the new table, unless its row is deleted, which ends it in about a second; `view.details` on the confirmation works only for an active module; the `module.ini` description shows in every state |
| M7.3 | [`docs/adr/ADR-0006-prefixing-the-verifier.md`](docs/adr/ADR-0006-prefixing-the-verifier.md) (accepted 2026-11-06): Strauss 0.31.1 as a pinned phar, in the build only; prefix `ContentCredentials\Vendor\`; the zip holds `vendor-prefixed/` with the verifier's `src/` (no `Cli/`), `LICENSE` and `composer.json`, and no `vendor/`, autoloader or `composer.json` of the module |
| M7.4 | [`specs/SPEC-008-release-build.md`](specs/SPEC-008-release-build.md) (approved 2026-11-06): six choices, all as recommended: job rows deleted in `uninstall()`, the warning in the description, the loader looks for the folder, what ships is a list in the build, the zip's name holds the version, the zip tested in `composer check` and at every push |
| M7.5 | [`notes/step-30-the-release-build-built.md`](notes/step-30-the-release-build-built.md): the build, the clean environment, the loader and the uninstall; SPEC-008 implemented with three amendments; 14 falsifications |
| M7.6 | [`notes/step-31-the-release-check.md`](notes/step-31-the-release-check.md): upstream added one root to both lists (Samsung, `64b3f9c`, 2026-11-04), no fixture's verdict changes, and refreshing needs an amendment; what omeka.org asks is met but for a public repository, a release and a README that is ready; the name is still free; no secret by pattern in the history, four probes hold a local path; no `upgrade()` yet |
| M7.7 | Maurice's decisions on step 31 (2026-11-06): refresh the lists, version 0.1.0, the zip on PHP 8.4 and 8.5, README and CHANGELOG ready, the probes' paths relative without rewriting history. A public repository, the registration and his own look at the zip are open. [`notes/step-32-refreshing-the-lists.md`](notes/step-32-refreshing-the-lists.md): the lists at `64b3f9c`, 2026-11-04 |
| M7.8 | [`notes/step-33-the-zip-on-newer-php.md`](notes/step-33-the-zip-on-newer-php.md): SPEC-008 amendment 4, AC16; the zip passes on PHP 8.4 and 8.5; with deprecations on, 8.5 logs none from the module and a few from Omeka S itself, which are off by default |
| M7.9 | [`notes/step-34-readme-and-paths.md`](notes/step-34-readme-and-paths.md): the README written for someone installing the module; every relative link holds from the zip; the four absolute links lead nowhere while the repository is private |
| M7.10 | [`notes/step-34-readme-and-paths.md`](notes/step-34-readme-and-paths.md): the four probes find their folders from `__DIR__`; the history is not rewritten |
| review | [`notes/step-35-the-code-review.md`](notes/step-35-the-code-review.md): ten findings checked; a killed run blocks every new run, a run that stored nothing is `completed`, the AI label misses `__n` labels; M7.11 to M7.14 before a release, finding 9 stays a known limit |
| M7.11 | [`notes/step-36-ending-a-run-that-has-gone.md`](notes/step-36-ending-a-run-that-has-gone.md): SPEC-005 amendment 2, AC14: Stop first, then "Mark this run as ended"; the status `error`; 10 falsifications |
| M7.12 | [`notes/step-37-a-run-that-fails.md`](notes/step-37-a-run-that-fails.md): SPEC-005 amendment 3, AC15: a run that fails, or with a check not stored, is `error` with only the module's own lines; a stop stays `stopped`; 10 falsifications; a killed connection measured: the run goes on and says what it lost |
| M7.13 | [`notes/step-38-instance-labels.md`](notes/step-38-instance-labels.md): SPEC-007 amendment 3, AC15: a final `__n` is removed before the exact compare, as the verifier does; 13 falsifications, of which two change no answer; no real file of 802 has such a label |
| M7.14 | [`notes/step-39-a-refused-form.md`](notes/step-39-a-refused-form.md): finding 4, SPEC-004 amendment 4, AC12: a refused form gives back the DigiCert choice as well as the text; 11 falsifications |
| M7.14 | [`notes/step-40-a-refused-start.md`](notes/step-40-a-refused-start.md): finding 5, SPEC-005 amendment 4, AC16: a refused start or end answers with the configure page and no success message; the same defect in AC14's path; 10 falsifications |
| M7.14 | [`notes/step-41-a-damaged-list.md`](notes/step-41-a-damaged-list.md): finding 6, SPEC-004 amendment 5, AC13: each bundled file in use is compared with its hash; an empty list had been accepted under its date; the 500 measured; the bind mount answers a quick second write late, which also touched the falsifications of steps 39 and 40 |
| M7.14 | [`notes/step-42-invisible-characters.md`](notes/step-42-invisible-characters.md): finding 7, SPEC-003 amendment 4: every Cc and Cf character becomes U+FFFD; ZWJ and ZWNJ in names are a known limit; 10 falsifications, two tests added for the two that survived at first |
| M7.14 | [`notes/step-43-the-signer-line.md`](notes/step-43-the-signer-line.md): finding 8, SPEC-003 amendment 5: "Signed by" names what is present; empty after trimming is absent; 10 falsifications; M7.14 done |
| M7.15 | [`notes/step-44-two-unused-constants.md`](notes/step-44-two-unused-constants.md): `TrustConfig::SETTINGS` and `Recheck::STATES` removed, read nowhere |
| M7.15 | [`notes/step-45-when-strauss-fails.md`](notes/step-45-when-strauss-fails.md): SPEC-008 amendment 5, AC17: the build stops with Strauss's output when it prefixes nothing; Strauss exiting non-zero is read, not measured; `build/tests/clone` sometimes cannot be removed between quick runs |
| M7.16 | [`notes/step-46-before-going-public.md`](notes/step-46-before-going-public.md): the check before going public (history, secrets, paths, fixtures; nothing blocking); CI token read-only and actions pinned to commits |
| M7.17 | [`notes/step-47-security-policy.md`](notes/step-47-security-policy.md): `SECURITY.md`; private vulnerability reporting is off until Maurice turns it on |
| M7.18 | [`notes/step-48-the-trial.md`](notes/step-48-the-trial.md): Maurice's trial in a clean Omeka S: `none`, `Trusted` and the AI label as expected; "het werkt" |
| M7.19 | [`notes/step-49-release-0-1-0.md`](notes/step-49-release-0-1-0.md): 0.1.0 released, zip SHA-256 `9ebec7dc…`; the build is not byte-reproducible; the anonymous download was 504 for about two minutes |
| M7.20 | [`notes/step-50-retrying-downloads.md`](notes/step-50-retrying-downloads.md): `--http1.1 --retry 10 --retry-delay 30` on the three release downloads; over HTTP/2 curl reported GitHub's 504 as error 56 and did not retry |
| M7.21 | [`notes/step-51-caching-downloads.md`](notes/step-51-caching-downloads.md): `actions/cache` for the Omeka S zip and the Strauss phar; the image copies the zip `tools/fetch-omeka.sh` checked; compose's `additional_contexts` tried and dropped (classic builder) |
| M7.22 | [`notes/step-52-stalled-downloads.md`](notes/step-52-stalled-downloads.md): `--connect-timeout 30 --speed-limit 1000 --speed-time 60` on the two release downloads; a stalled transfer and an incomplete connection end as error 28, which `--retry` retries; `-m` not chosen |
| M7.23 | [`notes/step-53-dependabot-for-actions.md`](notes/step-53-dependabot-for-actions.md): `.github/dependabot.yml` for `github-actions` only, monthly; Composer left out (the bundled verifier is a release decision); security alerts are a repository setting, Maurice's |
| M7.24 | [`notes/step-54-the-label-in-the-column.md`](notes/step-54-the-label-in-the-column.md): Omeka's tablesaw wraps a cell in a flex span, so the label stood beside the verdict; the cell is now one element (SPEC-007 amendment 4) |
| M7.25 | [`notes/step-55-the-readme-outside-github.md`](notes/step-55-the-readme-outside-github.md): omeka.org publishes the README as the module's page; links made absolute, an introduction for collection managers and two screenshots added; `module_link` and `author_link` shown by Omeka, `support_link` unused by Omeka 4.2.1 |
| M7.26 | [`notes/step-56-release-0-1-1.md`](notes/step-56-release-0-1-1.md): tag `v0.1.1` on `a57383e`, zip `51515d3e…`; the upgrade from the published 0.1.0 keeps verdicts and evidence byte-identical |
| M7.27 | [`notes/step-57-omeka-requirements.md`](notes/step-57-omeka-requirements.md): every documented requirement met except internationalization (no `language/`, no `translator`, one `@translate`) |
| M7.28 | [`notes/step-58-security-review.md`](notes/step-58-security-review.md): no high or medium findings; L1 the evidence controller allowed for every role (confirmed by reading), L2 a fatal in the upload request, L3 evidence that cannot be stored; `composer audit --no-dev` clean |

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
