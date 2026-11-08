# Changelog

This project follows the milestones in `docs/milestones.md`. Dates are the
day the work was committed.

## Unreleased

- Added: an introduction for collection managers and two screenshots in the README; every link in it
  works outside GitHub, and Omeka's modules page links to the repository and the author
  (SPEC-008 amendment 6). (2026-11-08)
- Fixed: in the media browse the AI label stands under the verdict, instead of beside it and broken
  inside the word (SPEC-007 amendment 4). (2026-11-08)
- Changed: GitHub's release downloads are retried over HTTP/1.1 (M7.20) and kept in a CI cache, and
  the test image is built from the zip `tools/fetch-omeka.sh` checked (M7.21). (2026-11-08)
- Changed: a download that stalls, or a connection that does not complete, ends after a minute and
  is retried, instead of hanging (M7.22). (2026-11-08)
- Added: Dependabot proposes updates of the pinned GitHub Actions, monthly (M7.23). (2026-11-08)

## 0.1.0 (2026-11-08)

The first version: every new JPEG, PNG and WebP media item is verified and its verdict stored and
shown in the admin interface, with bundled C2PA trust lists of 2026-11-04 or custom trust settings, a
re-check of existing media, a kept evidence document per check, and a label for a signed statement of
generative AI. Requires Omeka S 4.2.1 or a later 4.x and PHP 8.3 or later.

What follows is the record of how it was built.

### Added

- M0: the repository, the start-up decisions (`NOTES.md`), the plan
  (`docs/milestones.md`), the licence (GPL-3.0-or-later) and this
  changelog. (2026-11-03)
- M0: the integration environment: Omeka S 4.2.1 (release zip, SHA-256
  checked) on PHP 8.3 and MariaDB 11.4 in Docker Compose, with
  `tools/env/up.sh`, `down.sh` and `reset.sh`. (2026-11-03)
- M0: `composer.json` with the verifier (`^0.10`) and the development
  tools; ADR-0001 (PHP 8.3 floor) and ADR-0002 (dependencies), proposed.
  (2026-11-03)
- M0: the empty module (`config/module.ini`, `Module.php`,
  `config/module.config.php`); installs and uninstalls cleanly in
  Omeka S 4.2.1. (2026-11-03)
- M0: the spec template and SPEC-000, the spec traceability check
  (`bin/spec-check.php`), as the first step of `composer check`; it also
  refuses two spec files with one ID. (2026-11-04)
- M0: `composer check` runs spec-check, Pint, PHPStan at level max (Omeka S
  read from the pinned release), Deptrac and the unit tests. (2026-11-04)
- M0: installing refuses PHP below 8.3 with a message on the modules page
  (SPEC-001); integration tests of installing and uninstalling, on PHP 8.3
  and 8.2, in `composer check`. (2026-11-04)
- CI on GitHub Actions: static checks and unit tests on PHP 8.3, 8.4 and
  8.5, the integration tests, and a parse check on PHP 8.1 and 8.2.
  (2026-11-04)
- M1: every new JPEG, PNG and WebP media item with an original is verified
  with `provemark/c2pa-verifier` after it is saved, and its verdict stored
  in the table `content_credentials_verdict` (created on install, dropped
  on uninstall, removed with its media); a failure is stored as `error`
  and never stops the upload (SPEC-002). (2026-11-04)
- M2: the verdict in the admin interface: a block on the media page and
  in the Details panel, a column "Content Credentials" in the media
  browse, and a filter by verdict (admin only); text from the file is
  escaped and its control and direction characters made visible
  (SPEC-003). (2026-11-04)
- M3: trust settings. Uploads are verified against the bundled C2PA trust
  lists (`trust/`, CC BY 4.0) and the DigiCert Trusted Root G4 for
  timestamps (an option, on by default), or against custom trust settings
  that replace them, set on the module's configure page. Each verdict
  records its trust source and the admin block names it. The page warns
  six months after the lists were last compared with the published ones;
  uninstalling removes the settings (SPEC-004). (2026-11-04)
- Fixed: the modules page now links to the configure page
  (`configurable = true` in `config/module.ini`; SPEC-004 amendment 1),
  and installing goes straight to it (SPEC-001 amendment 2). (2026-11-04)
- M4: checking existing media again. The configure page starts a run
  over all checkable media or those without a result, as an Omeka job,
  one at a time, with the trust settings saved when it starts; it shows
  the latest run and links to its job page, where Stop ends it. The job
  log holds only the module's own lines (SPEC-005). (2026-11-04)
- Changed: the configure page's note and its warning about unusable
  custom settings now say that runs over existing media use the settings
  too (SPEC-004 amendment 2). (2026-11-04)
- M5: keeping the evidence. Every check whose verdict is `Trusted`,
  `Valid` or `Invalid` adds the verifier's evidence document to a table of
  its own, in one transaction with the verdict, and rows are never changed.
  The block on the media page counts the checks kept and links to pages
  that list them, show a document and download it. Who can see the media
  item can see its evidence. Files without Content Credentials and checks
  that end in `error` keep none (SPEC-006, ADR-0005). (2026-11-05)
- M6: the AI label. A file that verifies and says, in its signed actions,
  that it was made or edited with generative AI shows "AI-generated (signed)"
  or "AI-edited (signed)" in the block and the browse column, and the browse
  can filter on either, in admin only. A file that does not verify shows no
  label. The verdict table gains a column `ai_statement`; a development
  installation is installed again (SPEC-007). (2026-11-05)
- Changed: `composer check` has no time limit of Composer's own any more
  (`process-timeout` 0): the integration tests can outlast its 300 seconds.
  (2026-11-05)
- M7: the release build: `tools/build.sh` makes a zip with the verifier prefixed by Strauss 0.31.1,
  tested in a clean Omeka S by `composer check` and at every push, where the integration tests run on
  it too (ADR-0006, SPEC-008). (2026-11-06)
- Changed: uninstalling also deletes the module's job rows, which ends a run that is going on, and
  the module's description says that uninstalling deletes its verdicts and evidence (SPEC-008).
  (2026-11-06)
- Changed: the bundled C2PA trust lists are those of 2026-11-04 (`64b3f9c`), which add the Samsung
  C2PA Root CA G1 to both. Verdicts already stored keep their own list date until they are checked
  again (SPEC-004 amendment 3). (2026-11-06)
- M7: the release zip is tested on PHP 8.3, 8.4 and 8.5 (SPEC-008 amendment 4). (2026-11-06)
- M7: the README and this changelog are ready to publish: the README says what the module does for
  someone installing it, and its links work from the zip. (2026-11-06)
- Added: a run whose process has gone can be marked as ended on the configure page, after Stop on its
  job page, so that it no longer blocks every new run (SPEC-005 amendment 2). (2026-11-06)
- Fixed: a run that fails, or that could not store every check, now ends as failed instead of
  completed, and its log says how many checks were lost (SPEC-005 amendment 3). (2026-11-06)
- Fixed: the AI label also reads an actions assertion labelled with an instance number, such as
  `c2pa.actions.v2__1`, as the verifier does. A stored verdict changes when it is checked again
  (SPEC-007 amendment 3). (2026-11-07)
- Fixed: a refused settings form keeps the DigiCert choice that was submitted with it, so correcting
  the text and saving no longer loses that choice (SPEC-004 amendment 4). (2026-11-07)
- Fixed: a refused start or end of a run stays on the configure page and no longer also says that the
  module was successfully configured (SPEC-005 amendment 4). (2026-11-07)
- Fixed: a bundled trust list that is not the file this release ships, such as an empty or damaged
  one, is no longer used: checks run without a trust list, and the configure page says so instead of
  answering with an error (SPEC-004 amendment 5). (2026-11-07)
- Fixed: zero-width characters, the byte order mark, tag characters and every other Unicode control or
  format character in text from a file now show as U+FFFD, so they cannot hide in a signer's name
  (SPEC-003 amendment 4). (2026-11-07)
- Fixed: the signer line names only what the certificate holds: "Signed by Name (Organisation)",
  "Signed by Name" or "Signed by Organisation", and no empty brackets (SPEC-003 amendment 5).
  (2026-11-07)
- Changed: the release build stops with Strauss's own output when Strauss does not prefix the
  verifier, instead of failing later without a reason (SPEC-008 amendment 5). (2026-11-07)
- Added: `SECURITY.md`, how to report a vulnerability and what counts as one. (2026-11-07)
