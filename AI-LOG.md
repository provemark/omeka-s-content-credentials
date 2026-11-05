# AI log

This project is built with Claude Code (Anthropic), driven and reviewed by
Maurice van Loon. Every contribution the assistant produces is logged here,
newest at the bottom, in the same commit as the work it describes. What is
*measured* (a command was run) is kept apart from what is *reasoned* (a
conclusion from reading). Decisions are Maurice's; the assistant proposes.

The assistant is not listed as an author in commit metadata; this log and the
README are where the disclosure lives.

## 2026-11-03: Repository created, start-up decisions (M0.1)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: read the local project brief and the sister-project files it names,
  walk the open decisions one at a time, then set up the repository.
- Produced: `.gitignore` (the local brief on the first line, `vendor/`,
  Docker overrides, tool caches, build output, editor files, and `*.key`
  unconditionally); this `AI-LOG.md`; `NOTES.md` with the six decisions
  below and the facts still to be measured. One commit, no remote.
- Read: `provemark/c2pa-verifier` README ("Use", "Public API"),
  `docs/trust-settings.md`, `docs/evidence-format.md`;
  `provemark/tracefern-image-check` `specs/TEMPLATE.md`, `NOTES.md` (the
  decisions and the first open items), `tools/build.sh`.
- Measured: the module list on `omeka.org/s/modules/` (189 modules, `curl`)
  has no module matching "C2PA", "Content Credentials", "provenance" or
  "Tracefern"; `gh search repos` finds no Omeka module for C2PA or Content
  Credentials; `gh api repos/<repo>/license` gives GPL-3.0 for
  `omeka/omeka-s`, `omeka-s-modules/FileSideload`, `CSVImport` and
  `Mapping`, CeCILL-2.1 for `Daniel-KM/Omeka-S-module-Common`; the omeka.org
  page "Register an add-on" states no licence requirement. Local tools:
  PHP 8.5.12, Composer 2.10.2, Docker 29.6.2 with its daemon running.
- Reasoned, not measured: that an Omeka module's namespace must equal its
  directory; the storage options of decision 3 (Omeka values editable, the
  media `data` column owned by the ingester); that the file store's API
  needs a local source path; that `evidenceToJson()` output is ASCII (read
  in `docs/evidence-format.md`).
- Decided by Maurice: (1) name "Content Credentials (C2PA)", module
  directory and namespace `ContentCredentials`; (2) licence
  GPL-3.0-or-later; (3) the verdict in a module-owned table (direction; ADR
  after measurement); (4) trust lists as Tracefern: the two C2PA lists
  bundled with their date, replaceable by the administrator, DigiCert
  Trusted Root G4 behind its own option, on by default, a warning when the
  copy is old, never fetched; (5) evidence in its own database table, one
  row per check, never updated, built in M5; (6) local until M0 is done,
  then a private GitHub repository; public and omeka.org only with
  Maurice's go.

## 2026-11-03: The plan, the licence, a README (M0.2)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M0.2 as proposed: `docs/milestones.md`, `LICENSE`, `README.md`,
  `CHANGELOG.md`.
- Produced: `docs/milestones.md` (M0 to M7 from the brief, still a draft
  for M1 to M7, and the M0 steps with their status); `LICENSE`, the GPL-3.0
  text from `gnu.org/licenses/gpl-3.0.txt` (SHA-256
  `3972dc97…36986`); `README.md` with the status and the disclosure of
  this log; `CHANGELOG.md`; in `NOTES.md` decision 2 the open point
  replaced by the measurement below.
- Measured: Omeka S's `LICENSE` (`gh api repos/omeka/omeka-s/contents/LICENSE`)
  differs from the gnu.org text only in four URLs; Omeka S's README says
  "version 3 (GPLv3)" without "or later"; `provemark/c2pa-verifier` is a
  public repository and on Packagist (HTTP 200), so the README's link to it
  holds.
- Reasoned, not measured: nothing new.
- Decided by Maurice: the M0 steps as proposed ("ok, begin met M0.2").

## 2026-11-03: The integration environment (M0.3)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M0.3 with an image of our own, built from the release zip.
- Produced: `docker/omeka/` (Dockerfile, entrypoint, a form-less install
  script), `compose.yaml`, `tools/env/up.sh`, `down.sh`, `reset.sh`;
  `notes/step-03-test-environment.md`; the M0.3 row in
  `docs/milestones.md`, an index of steps in `NOTES.md`, `CHANGELOG.md`.
- Read: in the Omeka S 4.2.1 release, `composer.json`,
  `Omeka\Stdlib\Environment`, `Omeka\Installation\Installer` and its tasks,
  `InstallController`, `Omeka\Mvc\Status`, `perform-job.php`,
  `config/local.config.php`; the verifier's `composer.json`.
- Measured: v4.2.1 is the latest release and its zip matches GitHub's
  SHA-256; the base image lacks only `pdo_mysql` and ImageMagick; a fresh
  environment installs in 7 s, the admin logs in (302 to `/admin`),
  `/admin/module` lists the mounted module as having an invalid
  `module.ini`, a second `up.sh` changes nothing; an admin address under
  `.test` makes the installer stop after the schema, which Omeka S then
  takes for installed. Details and commands in the step note.
- Reasoned, not measured: PHP 8.3 as the environment's version; that the
  pinned digests give the same images on amd64; that the unpinned Debian
  packages stay the same.
- Decided by Maurice: an image of our own from the release zip ("ok, eigen
  image").

## 2026-11-03: The PHP floor and the first dependencies (M0.4)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M0.4 as proposed: ADR-0001 the PHP floor, ADR-0002 the
  dependencies, then `composer.json`.
- Produced: `docs/adr/ADR-0001-php-floor.md` and
  `docs/adr/ADR-0002-dependencies.md`, both `proposed`; `composer.json`
  and `composer.lock`; `notes/step-04-dependencies.md`; the M0.4 row in
  `docs/milestones.md`, the index in `NOTES.md`, `CHANGELOG.md`.
- Read: the verifier's ADR-0001 (for the form) and `composer.json`;
  Tracefern's `composer.json`; Omeka S 4.2.1's `composer.lock`.
- Measured: the latest versions on Packagist; Pest 5 needs PHP 8.4, so
  Pest 4.7; `omeka/omeka-s` 4.2.1 cannot be required (it needs
  `omeka-s-themes/default dev-develop`); the five packages resolve on the
  8.3.0 platform and each starts on PHP 8.3, 8.4 and 8.5; Omeka S 4.2.1's
  locked packages exclude PHP 8.4 (4 of 81) and 8.5 (29 of 81), none
  excludes 8.3; the verifier runs in the integration container. Details in
  the step note.
- Reasoned, not measured: that Omeka S's autoloader and the module's
  `vendor/` coexist; that `module.ini` can state a PHP requirement; the
  composer package name.
- Decided by Maurice: to start M0.4 ("ok, begin met M0.4"). The two ADRs
  await his acceptance.

## 2026-11-03: ADR-0001 and ADR-0002 accepted

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: record the acceptance.
- Produced: both ADRs `accepted`, decided by Maurice van Loon; the M0.4
  row in `docs/milestones.md`.
- Decided by Maurice: ADR-0001 and ADR-0002 accepted as proposed; the
  composer package name `mauricevanloon/omeka-s-content-credentials` kept
  ("ok, ADR's aangenomen, naam is goed").

## 2026-11-03: The empty module skeleton (M0.5)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M0.5 as proposed: an empty skeleton; measure whether the
  namespace must equal the directory and that it installs and uninstalls
  cleanly.
- Produced: `config/module.ini`, `Module.php`, `config/module.config.php`,
  `src/.gitkeep`; `notes/step-05-module-skeleton.md`; the M0.5 row in
  `docs/milestones.md`, the index in `NOTES.md`, `CHANGELOG.md`.
- Read: in Omeka S 4.2.1, `ModuleManagerFactory`, `Module\Manager`,
  `AbstractModule`, `ModuleCannotInstallException`, the admin
  `ModuleController` and the module browse and uninstall-confirm views.
- Measured: the module is recognised, installs, deactivates, activates and
  uninstalls through the admin interface, leaving the database as before
  except for login sessions; admin and public pages answer 200 with it
  active; `Module.php` lints on PHP 8.1, 8.2 and 8.5; a probe module whose
  namespace differs from its directory fails to install with HTTP 500 and
  no row; 13 of the module's development packages are Omeka S runtime
  packages, several at another major version. Details in the step note.
- Reasoned, not measured: that the 500 is "class not found" (read from
  `getModuleObject()`); that loading the module's whole `vendor/` would
  break Omeka S; that the two autoload mappings agree; the behaviour on
  PHP 8.1 or 8.2.
- Decided by Maurice: to start M0.5 ("ok, ADR's aangenomen, naam is goed,
  begin met M0.5").

## 2026-11-03: The spec template and SPEC-000 as a draft (M0.6a)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M0.6: the spec template, SPEC-000 and `bin/spec-check.php`, tests
  first.
- Produced: `specs/TEMPLATE.md` (Tracefern's, with the Omeka S
  documentation and source, the Docker environment, the Laminas escaper and
  a status line under each open question); `specs/SPEC-000-spec-traceability.md`
  as `draft`: the verifier's SPEC-000 with its amendments 1 and 2 folded
  in as AC11 and AC12, and a new AC13 (one file per spec ID; filenames
  `SPEC-###-`). M0.6 split into 6a (this) and 6b (tests, script), because
  no code may be written while SPEC-000 is a draft.
- Read: the verifier's `specs/SPEC-000-spec-traceability.md` and
  `bin/spec-check.php` (v0.10.0); Tracefern's `specs/TEMPLATE.md`.
- Measured: with `php -r` on PHP 8.5.12, the verifier's filename pattern
  reads `SPEC-0071-x.md` as `SPEC-007`, and keying by ID keeps only the last
  of two files with the same ID.
- Reasoned, not measured: that the verifier's whole script, run on such a
  tree, would let the first file take part in nothing.
- Decided by Maurice: to start M0.6 ("ok, begin met M0.6"). SPEC-000
  awaits his approval.

## 2026-11-04: SPEC-000 implemented (M0.6b)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M0.6b after approving SPEC-000: tests first and seen red, then the
  script.
- Produced: SPEC-000 `approved` (Maurice van Loon, 2026-11-04) with a
  status line under its open question (its own AC12), then `implemented`
  with the Traceability filled; `tests/Unit/SpecCheckTest.php`; the
  verifier's fixture trees and two new ones; `bin/spec-check.php`;
  `tests/Pest.php`, `phpunit.xml`; scripts in `composer.json`;
  `notes/step-06-spec-check.md`; `docs/milestones.md`, `NOTES.md`,
  `CHANGELOG.md`.
- Read: the verifier's `tests/Unit/SpecCheckTest.php`, its fixture trees
  and `phpunit.xml`.
- Measured: red on a missing script, then 15 of 16 red on a stub, and AC10
  red with the check order reversed; green, 16 passed, on PHP 8.3, 8.4 and
  8.5; on a copy with SPEC-000 implemented and untraced, `composer check`
  stops at spec-check with exit 1 and Pest does not run. Details in the
  step note.
- Reasoned, not measured: that Pest never collects the fixture trees
  (inferred from the test count).
- Decided by Maurice: SPEC-000 approved ("ok, SPEC-000 goedgekeurd, begin
  met M0.6b").

## 2026-11-04: `composer check` (M0.7a)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M0.7: the rest of `composer check`.
- Produced: `pint.json`, `phpstan.neon`, `deptrac.yaml`,
  `tools/fetch-omeka.sh`, the `check` chain in `composer.json`; type and
  style fixes in `docker/omeka/install.php` (with `OMEKA_ROOT` in
  `compose.yaml`), `Module.php` and SPEC-000's AC10 test;
  `notes/step-07-composer-check.md`; `docs/milestones.md` (M0.7 split into
  7a and 7b, because the integration test needs SPEC-001 first),
  `NOTES.md`, `CHANGELOG.md`.
- Read: the verifier's and Tracefern's `pint.json`, `phpstan.neon` and
  `deptrac.yaml`, and the verifier's scripts; in the Omeka S release, the
  signatures of `Application::init()` and `Installer`.
- Measured: Pint's and PHPStan's findings and their fixes; that PHPStan
  knows Omeka S only through the scan (falsified without it); that the MVC
  application is Omeka's fork `omeka/sanimal-mvc`; that Deptrac with
  `--fail-on-uncovered` passes a class in no layer; `composer check` green;
  the environment and the module's lifecycle again after the changes.
  Details in the step note.
- Reasoned, not measured: nothing new.
- Decided by Maurice: to start M0.7 ("ok, begin met M0.7").

## 2026-11-04: SPEC-001 as a draft (M0.7b)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M0.7, the integration test of installing and uninstalling, which
  needs a spec first.
- Produced: `specs/SPEC-001-install-and-uninstall.md` as `draft`: AC1–AC4
  make step 05's by-hand measurement into integration tests; AC5 refuses
  PHP below 8.3 with a message (a unit-tested check); AC6 measures the
  refusal on an Omeka S running PHP 8.2. Two open questions: how AC6 gets
  that Omeka S (proposed: a second service built from `php:8.2-apache`),
  and whether `composer check` starts the environment itself.
- Read: `Omeka\View\Helper\Messages` in Omeka S 4.2.1: a plain-string
  message is escaped with `escapeHtml`. That answered a third open
  question before the draft was handed over.
- Measured: `composer check` passes with SPEC-001 listed as draft.
- Decided by Maurice: nothing yet; SPEC-001 awaits his approval.

## 2026-11-04: SPEC-001 implemented; M0 done (M0.7b)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: SPEC-001 approved with both proposals; tests first, then the
  implementation.
- Produced: SPEC-001 `approved` (status lines under its two open
  questions), then `implemented` with the Traceability filled;
  `src/Requirements.php`, `Module::install()`; the unit and integration
  tests and their support classes; the PHP 8.2 installation in
  `compose.yaml` with `BASE_IMAGE` in the Dockerfile; `tools/env/up.sh`
  for both; the integration suite in `phpunit.xml` and `composer check`;
  SPEC-000's AC8 test made to count test files itself; the second part of
  `notes/step-07-composer-check.md`; `docs/milestones.md` (M0 done),
  `NOTES.md`, `CHANGELOG.md`.
- Read: `Omeka\Mvc\Application::init()` (only active modules are loaded).
- Measured: red for AC5 and AC6 on the missing behaviour, and for AC1–AC4
  with a temporary sabotage `Module.php` (never committed); a module's
  `src/` is not autoloadable during its own install, which made the first
  implementation fail with HTTP 500; `composer check` green in 30 s; the
  unit suite on PHP 8.3 and 8.4; the refusal message on the PHP 8.2 page.
  Details in the step note.
- Reasoned, not measured: that the message is escaped (it holds nothing
  escaping would change); the integration support code on PHP 8.3.
- Decided by Maurice: SPEC-001 approved with both proposals ("ok, SPEC-001
  goedgekeurd met beide voorstellen, begin").

## 2026-11-04: The private GitHub repository

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: the repository first, before M1.
- Produced: the private repository `provemark/omeka-s-content-credentials`
  with `main` pushed; the commit address rewritten before the push; this
  repository's `user.email` and `user.name` set; decision 6 in `NOTES.md`
  marked done; `docs/milestones.md`.
- Measured: `gh` logged in as Maurice1969DB, a member of `provemark`; the
  name free; the eleven commits carried the global configuration's address,
  while the verifier's history uses the GitHub noreply address; no tracked
  file names a commit hash, so rewriting changed no record; after
  `git filter-repo --mailmap`, all 22 author and committer fields carry the
  noreply address; no tracked file holds the old address, a key or a
  token, and no commit message carries attribution; the GitHub API lists
  only the noreply address on the pushed commits. A bundle of the history
  before the rewrite was kept outside the repository, in the session's
  scratch directory.
- Reasoned, not measured: nothing new.
- Decided by Maurice: owner and name `provemark/omeka-s-content-credentials`;
  rewrite to the noreply address before pushing.

## 2026-11-04: CI

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: CI as proposed: static checks and unit tests on PHP 8.3–8.5, the
  integration tests in a job of their own.
- Produced: `.github/workflows/ci.yml` (three jobs, and a parse check of
  the files Omeka S loads on PHP 8.1 and 8.2, which was added to the
  proposal); `notes/step-08-ci.md`; `docs/milestones.md`, `NOTES.md`,
  `CHANGELOG.md`.
- Read: the verifier's `.github/workflows/ci.yml`.
- Measured: the first run (41792666292) green on all six jobs, with the
  PHP versions, test counts and installs read from its log; that settles
  two points step 07 had reasoned (the integration support code on PHP
  8.3; the pinned images on the runner). Details in the step note.
- Reasoned, not measured: that the runner is x64 (GitHub's documentation);
  that a failing step turns the run red (no deliberately red commit was
  pushed).
- Decided by Maurice: to build CI ("ok, begin met CI").

## 2026-11-04: M1.1, the events around a new media item

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: begin M1.
- Produced: the M1 steps in `docs/milestones.md` (proposed); the
  measurement-only module `tools/probes/EventProbe/`;
  `notes/step-09-events-and-original.md`; `NOTES.md`.
- Read: `Omeka\Api\Manager`, `Omeka\Api\Adapter\ItemAdapter` and
  `MediaAdapter`, `Omeka\File\TempFile`, `Omeka\Media\Ingester\Upload`,
  `Omeka\Db\Event\Subscriber\Entity` in the pinned 4.2.1 source.
- Measured: on the admin form, the API (with an item and onto an item) and
  the URL ingester, `entity.persist.post` on media fires once per new
  media with the id set and the original present and byte-identical to
  the upload; `api.create.post` on the media adapter fires only for
  `POST /api/media`, which falsifies the brief's candidate; media without
  a file and a refused upload; update; delete of a media and of an item
  (no media `api.delete.post` then). The test environment was reset
  afterwards.
- Reasoned, not measured: that storing must wait until after the flush;
  which hook that is, CSV Import, File Sideload and batch create.
- Decided by Maurice: to start M1 ("ok, begin met M1").

## 2026-11-04: M1.2, a module-owned table and the hook after the commit

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M1.2.
- Produced: the measurement-only module `tools/probes/StorageProbe/`;
  `notes/step-10-storage-and-after-flush.md`; `docs/adr/ADR-0003-storage.md`
  (proposed); `NOTES.md`, `docs/milestones.md`.
- Read: `Omeka\Service\EntityManagerFactory`, Doctrine's
  `UnitOfWork::commit()`, the Mapping module's `Module.php` and
  `config/module.config.php` (GitHub).
- Measured: the table installs and uninstalls with the schema back as it
  was; `postFlush` after the commit on four routes, batch create from the
  CLI included; writing there through DBAL and the entity manager; an
  exception there gives HTTP 500 for a committed upload; reading and a
  DQL join without proxies; rows removed with their media and with the
  item. The test environment was reset afterwards.
- Reasoned, not measured: when proxies would be needed; outer
  transactions opened by other modules; the cost of verifying in the
  request.
- Decided by Maurice: to do M1.2 ("ok, begin met M1.2"); ADR-0003 awaits
  his decision.

## 2026-11-04: ADR-0003 accepted

- Decided by Maurice: ADR-0003 (storing the verdict) accepted ("ok,
  ADR-0003 aangenomen").

## 2026-11-04: M1.3, loading the verifier inside Omeka S

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M1.3.
- Produced: the measurement-only module `tools/probes/AutoloadProbe/`;
  `notes/step-11-loading-the-verifier.md`; `NOTES.md`,
  `docs/milestones.md`. Measurement scripts and build copies stayed outside
  the repository or were removed.
- Read: the verifier's `composer.json`, README (Use, Public API) and
  `Cli/Command.php`; Tracefern's SPEC-009 and `tools/build.sh`.
- Measured: thirteen packages in both vendors at other versions; the whole
  development `vendor/autoload.php` gives HTTP 500 on PHP 8.2 and a latent
  fatal in Omeka's PSR logger on 8.3; a one-namespace autoloader gives the
  CLI's verdict (state and JSON hash) on 16 fixtures inside Omeka, the
  logger intact; a Strauss 0.30.0 build copy does the same, its
  `autoload.php` fatal on 8.2; Strauss rewrites namespace strings but not
  paths. The test environment was reset.
- Reasoned, not measured: the conclusions for the spec in the note.
- Decided by Maurice: to do M1.3 ("ok, ADR-0003 aangenomen, begin met
  M1.3").

## 2026-11-04: M1.4, SPEC-002 as a draft

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M1.4.
- Produced: `specs/SPEC-002-verify-on-upload.md` (`draft`, 14 criteria, two
  open questions, SPEC-001 amendment 1 proposed in its scope);
  `docs/milestones.md`.
- Read: `specs/TEMPLATE.md`; Tracefern's SPEC-001 (behaviour section);
  `Omeka\File\Store\StoreInterface` and `Local`; the allowed media types in
  Omeka S 4.2.1's `config/module.config.php`.
- Measured: the CLI's report shape for `fixture-signed.jpg` (the active
  manifest's `signature_info` has `issuer` and `common_name`, no `time`);
  the unsigned fixtures give `Invalid`, `has_manifest` false, no status;
  the altered JPEG of AC4 gives `Invalid` with `signingCredential.untrusted`
  and `assertion.dataHash.mismatch`; the six fixtures are 249,809 bytes.
- Reasoned, not measured: the request-time cost of verifying large files
  (an open question in the spec).
- Decided by Maurice: to write the spec ("ok, begin met M1.4"); approval
  pending.

## 2026-11-04: SPEC-002 approved, SPEC-001 amendment 1

- Decided by Maurice: SPEC-002 approved with both recommendations (verify
  in the request; copy the fixtures), and with it SPEC-001 amendment 1
  ("ok, SPEC-002 goedgekeurd met beide aanbevelingen, begin").
- Produced: the status and the answered questions in SPEC-002; AC1 and an
  Amendments section in SPEC-001.

## 2026-11-04: M1.5, SPEC-002 implemented

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: implement SPEC-002 ("begin").
- Produced: `src/Check/` (Verdict, MediaCheck, Recorder, VerdictStore),
  `src/Omeka/` (AfterFlushListener, DbalVerdictStore, Schema,
  VerifierLoader), `src/Entity/StoredVerdict.php`; `Module.php` (install,
  uninstall, the listener); `config/module.config.php`; three Deptrac
  layers; PHPStan scan of Omeka's Doctrine packages; the tests
  (`tests/Unit/VerifyOnUploadTest.php`,
  `tests/Integration/VerifyOnUploadTest.php`, `tests/Support/VerifierCli.php`,
  `tests/Integration/Support/included-files.php`, additions to
  `OmekaInstallation`); SPEC-001's AC1 test for amendment 1; the fixtures
  in `tests/Fixtures/c2pa/`; CI's parse check over all of `src/`;
  SPEC-002's Traceability; `notes/step-12-verify-on-upload.md`; README,
  CHANGELOG, NOTES, milestones.
- Measured: the red runs before the implementation; the green
  `composer check`; what each integration test measures (in the note);
  three defects in the tests found and fixed, among them that Omeka's
  `application.config.php` sets `$url` in its caller's scope.
- Reasoned, not measured: AC8–AC10 and AC12 through stand-ins; `verifier`
  `NULL` for `requirements`; the time verifying adds to large uploads.
- Decided by Maurice: SPEC-002 and its two recommendations, SPEC-001
  amendment 1.

## 2026-11-04: M2.1, the admin view hooks

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: begin M2 (the module version stays 0.1.0 until a release,
  decided by Maurice).
- Produced: the M2 steps in `docs/milestones.md`; the measurement-only
  module `tools/probes/ViewProbe/`; `notes/step-13-admin-views.md`;
  `NOTES.md`.
- Read: `view/omeka/admin/media/show.phtml`, `show-details.phtml`,
  `browse.phtml`, `search.phtml`, `view/common/advanced-search.phtml`;
  `Omeka\View\Helper\Trigger`, `Browse`, `SearchFilters`;
  `Omeka\ColumnType\ColumnTypeInterface`, `Omeka\Stdlib\Browse`;
  `AbstractEntityAdapter::sortQuery()`.
- Measured: each hook fires on its page with what the note lists; a
  module column shows through `column_defaults` without user settings; a
  search partial, a filter label and an `api.search.query` condition
  work together; a hostile string through `escapeHtml` arrives escaped on
  every page; an unknown `sort_by` is ignored. The test environment was
  reset afterwards.
- Reasoned, not measured: the conclusions for the spec in the note.
- Decided by Maurice: version left at 0.1.0 until release; begin M2.

## 2026-11-04: M2.2, SPEC-003 as a draft

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M2.2.
- Produced: `specs/SPEC-003-show-the-result.md` (`draft`, 11 criteria, two
  open questions); `docs/milestones.md`.
- Read: Tracefern's SPEC-002 (wording, escaping of control and direction
  characters), ported; `Omeka\Mvc\Status`.
- Measured: the test environment has no site.
- Reasoned, not measured: that `Status::isAdminRequest()` separates the
  admin side (AC11 will measure it).
- Decided by Maurice: to write the spec ("ok, begin met M2.2"); approval
  pending.

## 2026-11-04: SPEC-003 approved

- Decided by Maurice: SPEC-003 approved with both recommendations (the
  filter on admin requests only; the column for administrators with own
  columns accepted, said in the README) ("ok, SPEC-003 goedgekeurd met
  beide aanbevelingen, begin").

## 2026-11-04: M2.3, SPEC-003 implemented

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, SPEC-003 goedgekeurd met beide aanbevelingen, begin".
- Produced: `tests/Unit/ShowTheResultTest.php` and
  `tests/Integration/ShowTheResultTest.php` (first, seen red: 41 unit
  failures on missing classes, 10 of 11 integration failures);
  `src/Display/` (Presentation, Text, FilterValue) and the Deptrac layer
  `Display`; `src/Omeka/` AdminDisplay, StateColumn(Factory), VerdictHtml,
  VerdictReader, ViewText; `view/content-credentials/advanced-search.phtml`;
  `config/module.config.php`; `Module.php`; SPEC-003 Traceability and
  status `implemented`; `notes/step-14-show-the-result.md`; README,
  milestones, NOTES, CHANGELOG.
- Measured: the red and green runs; AC11 made to fail by removing the
  admin-only guard, then green with it; `composer check` green (88 unit,
  31 integration tests); the new files lint on PHP 8.2.35.
- Mistakes of mine, caught by the tests: `toContain()` given a message as
  a second argument (Pest reads it as a needle); AC10 first checked the
  whole page for the echoed value, where Omeka's own escaped link holds it.
- Interpretation for Maurice to confirm: AC10's "never echoed" read as
  "by the module".
- Not measured: the browse's cost (one row query per media item), the
  column picker, the look in a browser.

## 2026-11-04: M3 steps proposed

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, lezing klopt, stel M3-stappen voor".
- Decided by Maurice: AC10's "never echoed" in SPEC-003 means "by the
  module" (the reading in `notes/step-14-show-the-result.md` stands).
- Produced: the M3 steps table in `docs/milestones.md`.
- Read: `NOTES.md` §4; the verifier's `docs/trust-settings.md`;
  Tracefern's `specs/SPEC-004-trust-settings.md` (criteria) and
  `trust/README.md`; the list of Tracefern's fixtures. Nothing measured.

## 2026-11-04: M3.1, settings in Omeka S measured

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M3.1".
- Produced: `tools/probes/SettingsProbe/` (measurement only);
  `notes/step-15-settings.md`; `docs/milestones.md`; `NOTES.md`.
- Read: `AbstractModule`, `ModuleController::configureAction()`,
  `AclFactory`, `AbstractSettings`, `Module\Manager`, `layout-admin.phtml`
  (Omeka S 4.2.1).
- Measured: everything in the note's table, in the PHP 8.3 container.
- Mistake of mine: the first probe used `messenger()` as a view helper,
  and every admin page gave 500 until it was corrected; the note says so.
- Not measured: a checkbox field, Laminas form elements, PHP 8.2.

## 2026-11-04: M3.2, the verifier with the bundled trust lists measured

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M3.2".
- Produced: `tools/probes/TrustSettings/` (three measurement scripts and
  the main result); `notes/step-16-trust-lists.md`; `docs/milestones.md`;
  `NOTES.md`.
- Measured: the list copy against upstream (two `gh api` reads of
  public files, the only network use, from the development machine); 52
  library-against-CLI pairs; the same inside Omeka with the module's
  loader; the cost of `fromJson()`; verdicts at five dates and the
  OpenAI image's expiry by binary search.
- Reasoned: which fixtures to propose for M3's tests; nothing copied yet.

## 2026-11-04: M3.3, ADR-0004 proposed

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M3.3".
- Produced: `docs/adr/ADR-0004-bundled-trust-lists.md` (`proposed`);
  `docs/milestones.md`; `NOTES.md`.
- Rests on: `notes/step-16-trust-lists.md`; decision 4 in `NOTES.md`.
- Proposed for Maurice to decide: the age warning counts from the date
  of the last comparison with upstream, not from the upstream commit
  date; both are shown.

## 2026-11-04: ADR-0004 accepted

- Decided by Maurice: ADR-0004 accepted, the age warning counted from the
  comparison date ("ok, ADR-0004 aangenomen, begin met M3.4").

## 2026-11-04: M3.4, SPEC-004 as a draft

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, ADR-0004 aangenomen, begin met M3.4".
- Produced: `specs/SPEC-004-trust-settings.md` (`draft`, 10 criteria,
  SPEC-002 amendment 1 and SPEC-003 amendment 1 proposed, four open
  questions); `docs/milestones.md`.
- Read: Tracefern's SPEC-004 criteria (ported); steps 15 and 16; ADR-0004.
- Measured: SPEC-002's altered JPEG gives the same verdict with and
  without the bundled lists (for the amendment's claim).
- Decided by Maurice: approval pending.

## 2026-11-04: SPEC-004 approved

- Decided by Maurice: SPEC-004 approved with all recommendations (change
  the `CREATE TABLE`, no `upgrade()`; the age warning on the configure page
  only; custom settings in `Omeka\Settings`; copy the Pixel 10 photo), and
  with it SPEC-002 amendment 1 and SPEC-003 amendment 1 ("ok, SPEC-004
  goedgekeurd met alle aanbevelingen, begin").

## 2026-11-04: M3.5, SPEC-004 implemented

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, SPEC-004 goedgekeurd met alle aanbevelingen, begin".
- Produced: the tests first (`tests/Unit/TrustSettingsTest.php`,
  `tests/Integration/TrustSettingsTest.php`, seen red: 37 of 38 and 10 of
  11); `trust/` and its README; `src/Trust/`; changes in `src/Check/`,
  `src/Display/`, `src/Omeka/`, `src/Entity/`, `Module.php`, `deptrac.yaml`;
  `tools/trust/compare.sh`; three fixtures and two licence files; the
  test environment's upload limit; SPEC-004 Traceability and status
  `implemented`; `notes/step-17-trust-settings.md`; README, CHANGELOG,
  NOTES, milestones.
- Measured: the red and green runs; AC9 made to fail with an old
  comparison date; PHP's upload limits in the container; `compare.sh`
  against upstream (`gh api`, public files).
- Mistakes of mine, caught by the tests: a regex on a 1 MiB textarea,
  and `verdictRow()` without the new column.
- Not measured: the page in a browser; a POST without the hidden field.

## 2026-11-04: M4 steps proposed

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, stel M4-stappen voor".
- Produced: the M4 steps table in `docs/milestones.md`.
- Read: `Omeka\Job\AbstractJob`, `Dispatcher`, the `PhpCli` and
  `Synchronous` strategies (file list and signatures only). Nothing
  measured.

## 2026-11-04: M4.1, jobs in Omeka S measured

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M4.1".
- Produced: `tools/probes/JobProbe/` (module and scripts, measurement
  only); `notes/step-18-jobs.md`; `docs/milestones.md`; `NOTES.md`.
- Read: `Dispatcher`, `PhpCli`, `Synchronous`, `AbstractJob`, `Cli`,
  `perform-job.php`, `JobController` (Omeka S 4.2.1).
- Measured: the table in the note, in the PHP 8.3 container; the
  container's `local.config.php` was changed for one case and restored.
- Not measured: scale, concurrency, restart after a stop (M4.2); a host
  with `exec` disabled.

## 2026-11-04: SPEC-004 amendment 1, the Configure link

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "Hoe kan ik vanuit het home naar die configuratie?"; then
  "ok, amendement goedgekeurd, voer het uit".
- Found: no Configure link on the modules page; Omeka requires
  `configurable = true` in `module.ini`. My oversight in M3: the page was
  only ever opened by URL.
- Decided by Maurice: SPEC-004 amendment 1 (AC11).
- Produced: the AC11 test (seen red after correcting a too-strict first
  version, see the note); `config/module.ini`; SPEC-004 AC11, Amendments
  and Traceability; `notes/step-19-configure-link.md`; NOTES, CHANGELOG.

## 2026-11-04: SPEC-001 amendment 2, the install redirect

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, SPEC-001 amendement 2 goedgekeurd, voer het uit".
- Mistake of mine: commit `9e72de5` was pushed with a red `composer check`
  (check and commit chained with `;`). Its failure, SPEC-001 AC1, showed
  that `configurable = true` also changes the redirect after install.
- Decided by Maurice: SPEC-001 amendment 2.
- Produced: SPEC-001 AC1 and its Amendments; the AC1 test (seen red
  without `configurable`, green with it); `notes/step-19-configure-link.md`;
  CHANGELOG, NOTES.

## 2026-11-04: M4.2, a re-check at scale measured

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M4.2".
- Produced: `tools/probes/RecheckProbe/` (module, seed and measure
  scripts, measurement only); `notes/step-20-recheck-at-scale.md`;
  `docs/milestones.md`; `NOTES.md`.
- Measured: the table in the note, in the PHP 8.3 container, with 2 530
  seeded media that were deleted afterwards (one extra test item went
  with them, see the note).
- Not measured: large originals one by one, slower hosts, a progress
  display.

## 2026-11-04: M4.3, SPEC-005 drafted

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M4.3".
- Produced: `specs/SPEC-005-recheck-existing-media.md` (`draft`, 13 ACs,
  five open questions with recommendations); `docs/milestones.md`.
- Rests on steps 18 and 20; nothing new measured.

## 2026-11-04: M4.4, SPEC-005 implemented

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, SPEC-005 goedgekeurd met alle aanbevelingen, begin".
- Produced: `src/Check/Recheck.php`, `src/Check/RecheckCounts.php`,
  `src/Display/RecheckSection.php`, `src/Omeka/RecheckJob.php`,
  `src/Omeka/RecheckRuns.php`, `Module.php`; `tests/Unit/RecheckTest.php`,
  `tests/Integration/RecheckTest.php`, `OmekaInstallation::rootShell()`;
  SPEC-005 (status, open-question statuses, Traceability);
  `notes/step-21-recheck.md`; README, CHANGELOG, NOTES, milestones.
- Tests seen red before the code (unit 2 failed; integration 11 failed,
  3 passed); AC10 falsified with a `global_admin` case.
- Found while testing: opcache delays a change to `local.config.php`
  (measured, see the note).
- Mistake of mine, caught before the commit: a message with an
  apostrophe inside a single-quoted PHP string, made by a `sed` edit;
  seen when reading the edited line back, fixed, then checked with
  `php -l`.

## 2026-11-04: SPEC-004 amendment 2, the settings texts

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, stel het amendement voor punt 1 voor"; then "ok, amendement
  2 goedgekeurd met de AC6-zin, voer het uit".
- Decided by Maurice: amendment 2, with the AC6 sentence as well.
- Produced: the four tests (seen red: 4 failed), `SettingsPage`,
  SPEC-004 (Scope, AC6, AC8, Amendments), `notes/step-22-settings-texts.md`,
  NOTES, CHANGELOG.

## 2026-11-04: M5 steps proposed

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, stel M5-stappen voor".
- Produced: `docs/milestones.md` ("M4 is done", "M5 steps").
- Read: the verifier's `docs/evidence-format.md`, the `TrustSettings`
  signatures, decision 5 in `NOTES.md`. Nothing measured.

## 2026-11-05: M5.1, the evidence document measured

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M5.1".
- Produced: `tools/probes/EvidenceProbe/measure.php` (never shipped),
  `notes/step-23-evidence-document.md`, NOTES, milestones.
- Measured: 13 inputs × 5 trust settings with verifier 0.10.0, on PHP
  8.5.12 (host) and 8.3.36 (container); results and the commands in the
  note. The reasoned parts (the size per run, the choices for SPEC-006)
  are marked as such there.

## 2026-11-05: M5.2, evidence in Omeka measured

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M5.2".
- Produced: `tools/probes/EvidenceRouteProbe/` (never shipped: module,
  job, controller, view, `scripts/measure.php`),
  `notes/step-24-evidence-in-omeka.md`, NOTES, milestones.
- Measured in the PHP 8.3 test environment; commands and results in the
  note. Over the 1 103 media present, not the 3 000 the plan named (the
  seed of step 20 was deleted); the 3 000 figure is reasoned.
- Mistakes of mine, caught while measuring: the first route inherited
  the admin `__NAMESPACE__` (every request 404; recorded as a finding);
  a query used `media.is_public`, which lives on `resource`.

## 2026-11-05: M5.3, ADR-0005 proposed

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M5.3".
- Produced: `docs/adr/ADR-0005-evidence-table.md` (`proposed`);
  `docs/milestones.md`; `NOTES.md`.
- Rests on: steps 23 and 24; decision 5 and ADR-0003, ADR-0004. Nothing
  new measured.
- Proposed for Maurice to decide: keep `fromJson()` and record
  `trust_sha256`; evidence deleted with its media item and on uninstall;
  left to SPEC-006: who may see it, every check or only a changed
  document, and files without a manifest.

## 2026-11-05: ADR-0005 accepted

- Decided by Maurice: ADR-0005 accepted as proposed (`fromJson()` kept
  with `trust_sha256`; evidence deleted with its media item and on
  uninstall) ("ok, ADR-0005 aangenomen, begin met M5.4").

## 2026-11-05: M5.4, SPEC-006 as a draft

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, ADR-0005 aangenomen, begin met M5.4".
- Produced: `specs/SPEC-006-keep-the-evidence.md` (`draft`, 12 criteria,
  SPEC-003 amendment 2 proposed, three open questions); ADR-0005's
  status; `docs/milestones.md`; `NOTES.md`.
- Rests on: steps 23 and 24, ADR-0005, decision 5; the Omeka S 4.2.1
  files it cites (checked to exist). Nothing new measured.
- Decided by Maurice: approval pending.

## 2026-11-05: SPEC-006 approved

- Decided by Maurice: SPEC-006 approved with all three recommendations
  (access follows the media item; every check adds a row; no row for
  `none`), and with it SPEC-003 amendment 2 ("ok, SPEC-006 goedgekeurd met
  alle aanbevelingen, begin met M5.5").

## 2026-11-05: M5.5, SPEC-006 implemented

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, SPEC-006 goedgekeurd met alle aanbevelingen, begin met M5.5";
  then "ok, amendement 3 en de U+202E-correctie goedgekeurd, rond af".
- Decided by Maurice: SPEC-001 amendment 3; the correction of U+202E in
  SPEC-006 AC7.
- Produced: `src/Check/Evidence.php`, `Checked.php`, `MediaCheck.php`,
  `Recorder.php`, `RecheckCounts.php`, `VerdictStore.php`;
  `src/Trust/BuiltTrust.php`, `TrustChoice.php`;
  `src/Display/Presentation.php`; `src/Omeka/EvidenceController.php`,
  `EvidenceControllerFactory.php`, `EvidenceHtml.php`, `EvidenceReader.php`,
  `DbalVerdictStore.php`, `Schema.php`, `VerdictHtml.php`,
  `AdminDisplay.php`; `Module.php`, `config/module.config.php`,
  `view/content-credentials/evidence/`; `tests/Unit/EvidenceTest.php`,
  `tests/Integration/EvidenceTest.php`, `HttpResponse`; SPEC-001, SPEC-003,
  SPEC-006; `notes/step-25-keep-the-evidence.md`; README, CHANGELOG, NOTES,
  milestones.
- Tests seen red before the code; AC3, AC11 and the second AC9 test
  falsified. AC11's check of the response body could not be falsified (see
  the note).
- Mistakes of mine: no SPEC-001 amendment proposed with ADR-0005 and the
  draft; the character U+202E written into SPEC-006 and a test instead of
  six ASCII characters, and the spec corrected before Maurice approved it;
  a test that turned a correct null into 'missing'.

## 2026-11-05: M6 steps proposed

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, stel M6-stappen voor".
- Produced: `docs/milestones.md` ("M6 steps").
- Read: Tracefern's SPEC-003, SPEC-027 and `notes/m3-ai-label.md`; the
  verifier's `docs/evidence-format.md`; steps 13 and 16. Nothing measured.

## 2026-11-05: M6.1, the AI statement measured

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: "ok, begin met M6.1".
- Produced: `tools/probes/AiProbe/` (never shipped),
  `notes/step-26-ai-statement.md`, NOTES, milestones.
- Measured: 17 files with three settings on PHP 8.5.12 and 8.3.36, and a
  census of 570 files, with verifier 0.10.0; no fixture added to the
  repository. Found: `fixture-signed` turns `Invalid` on 2030-08-27, which
  dates this module's own tests.
- Reasoned, marked in the note: the consequences for M6.2; the meaning of
  `algorithmicMedia` is from memory and still to be cited.
