# Milestones

The plan. The record of what was actually done is in `notes/` and
`AI-LOG.md`; when the plan and the record disagree, the record wins.

Fixed throughout: read and verify only; no network, no `exec`, no
temporary files in the verification path; the verifier's
`validation_state` and the C2PA 2.4 §15 status codes verbatim, no
vocabulary of our own. Every feature is a spec (`specs/`) approved before
it is built, with its tests seen red first.

| M | What | Done when |
|---|---|---|
| M0 | Skeleton: module, tooling, spec template, SPEC-000, Docker environment, notes, AI log, ADRs | `composer check` green on an empty `src/`; the module installs and uninstalls cleanly in the pinned Omeka S |
| M1 | Verify on upload and store the result (JPEG, PNG, WebP; the original only) | the verdict per fixture equals `vendor/bin/c2pa-verify` on the same file, measured in Omeka S |
| M2 | Show the result in the admin interface (media page, browse) | an integration test sees each state; untrusted text escaped |
| M3 | Trust settings: the bundled C2PA trust lists with their date, replaceable by the administrator | `Trusted` reached on a fixture with a trusted signer; a settings warning when the copy is old |
| M4 | Check existing media again (a job started from the admin interface) | all media checked, interruptible, idempotent |
| M5 | Evidence: store the verifier's evidence document (format version 1) per check, viewable and downloadable | the stored document is byte-identical to `evidenceToJson()` and survives a verifier update |
| M6 | AI label: "AI-generated (signed)" only on a manifest that verifies | as SPEC-003 of Tracefern |
| M7 | Release build: a zip with a prefixed `vendor/`; uninstall removes everything | the zip installs on a clean Omeka S; then the decision on registering on omeka.org |
| later | Credentials across derivatives (thumbnailer), signing by the institution, more formats | one spec each, not before M7 |

The milestones M1 to M7 are a draft until Maurice confirms them.

## M0 steps

One concept per step, one commit per step, each built after an "ok".

| step | what | status |
|---|---|---|
| M0.1 | Repository, `.gitignore`, `AI-LOG.md`, `NOTES.md` with the start-up decisions | done 2026-11-03 |
| M0.2 | This plan, `LICENSE` (GPL-3.0), `README.md`, `CHANGELOG.md` | done 2026-11-03 |
| M0.3 | Pin Omeka S (measure the latest 4.2.x tag); Docker Compose with Omeka S and MariaDB, the module mounted in `modules/`, scripts to start, stop and reset it, apart from any development installation | done 2026-11-03 (`notes/step-03-test-environment.md`) |
| M0.4 | ADR-0001 the PHP floor (8.3: installations on 8.1 and 8.2 excluded); ADR-0002 the dependencies (the verifier, the development tools); then `composer.json` | done 2026-11-03 (`notes/step-04-dependencies.md`); ADRs accepted 2026-11-03 |
| M0.5 | An empty module skeleton (`config/module.ini`, `Module.php`, `config/module.config.php`); measure whether the namespace must equal the directory, and that it installs and uninstalls cleanly in the pinned Omeka S | done 2026-11-03 (`notes/step-05-module-skeleton.md`) |
| M0.6a | `specs/TEMPLATE.md` and SPEC-000 (`draft`), a port of the verifier's SPEC-000 with one new criterion | done 2026-11-03; SPEC-000 approved 2026-11-04 |
| M0.6b | after approval: SPEC-000's tests, seen red; `bin/spec-check.php`; green; Traceability filled | done 2026-11-04 (`notes/step-06-spec-check.md`) |
| M0.7a | `composer check`: spec-check, Pint, PHPStan at level max, Deptrac, Pest (unit); green on an empty `src/` | done 2026-11-04 (`notes/step-07-composer-check.md`) |
| M0.7b | SPEC-001 (install and uninstall), then its integration tests against the Docker environment, in `composer check` | done 2026-11-04 (`notes/step-07-composer-check.md`) |

**M0 is done (2026-11-04):** `composer check` passes, including the
integration tests, and the module installs and uninstalls cleanly in
Omeka S 4.2.1 (SPEC-001). Then, by decision 6 in `NOTES.md`: the private
GitHub repository `provemark/omeka-s-content-credentials` (2026-11-04).
CI on PHP 8.3, 8.4 and 8.5, with the integration tests (2026-11-04,
`notes/step-08-ci.md`). Next: M1.

## M1 steps

Proposed 2026-11-04; one step at a time, each after an "ok".

| step | what | status |
|---|---|---|
| M1.1 | Measure the events around a new media item and where the original is, on every route the core offers | done 2026-11-04 (`notes/step-09-events-and-original.md`) |
| M1.2 | Measure a module-owned table (entity, install and uninstall SQL) and the hook after the flush; ADR-0003 storage | done 2026-11-04 (`notes/step-10-storage-and-after-flush.md`); ADR-0003 accepted 2026-11-04 |
| M1.3 | Measure loading the verifier without the module's whole `vendor/` | done 2026-11-04 (`notes/step-11-loading-the-verifier.md`) |
| M1.4 | The M1 spec (`draft`), resting on M1.1 to M1.3 | done 2026-11-04: SPEC-002 `draft`, with SPEC-001 amendment 1 proposed in it |
| M1.5 | after approval: SPEC-002's tests, seen red; the implementation; green; Traceability filled | done 2026-11-04 (`notes/step-12-verify-on-upload.md`) |

**M1 is done (2026-11-04):** the verdict of every new JPEG, PNG and WebP
equals `vendor/bin/c2pa-verify` on the same file, measured in Omeka S
4.2.1 (SPEC-002). Next: M2, showing it.

## M2 steps

Proposed 2026-11-04; one step at a time, each after an "ok".

| step | what | status |
|---|---|---|
| M2.1 | Measure the admin view hooks: the media page, the browse, a filter; escaping | done 2026-11-04 (`notes/step-13-admin-views.md`) |
| M2.2 | The M2 spec (`draft`) | done 2026-11-04: SPEC-003 `draft`; approved 2026-11-04 |
| M2.3 | after approval: tests seen red, the implementation, green, Traceability | done 2026-11-04 (`notes/step-14-show-the-result.md`) |

**M2 is done (2026-11-04):** an integration test sees each state on the
media page, in the Details panel and in the browse column; untrusted text
is escaped and control and direction characters made visible (SPEC-003).
Next: M3, trust settings.

## M3 steps

Proposed 2026-11-04; one step at a time, each after an "ok". The
decisions are already in `NOTES.md` §4 (bundle the two C2PA lists and
the DigiCert root, DigiCert on by default, custom settings replace
everything, an age warning, never fetch). The blueprint is Tracefern's
SPEC-004; each Omeka equivalent is measured first.

| step | what | status |
|---|---|---|
| M3.1 | Measure Omeka S 4.2.1 for settings: the module configuration form (`getConfigForm()`, `handleConfigForm()`), `Omeka\Settings` (storage, size of a 70 KB value, reading it from the after-flush listener), CSRF on the form, where a warning can show (the configure page, the messenger, the dashboard), and `Module::upgrade()` for a schema change | done 2026-11-04 (`notes/step-15-settings.md`) |
| M3.2 | Measure the verifier with the bundled lists: our six fixtures and Tracefern's real-world fixtures (Pixel 10, OpenAI, Truepic, Amazon Titan; their licences decide which may be copied), DigiCert on and off, equal to `vendor/bin/c2pa-verify --settings`; and what building the settings adds per upload | done 2026-11-04 (`notes/step-16-trust-lists.md`) |
| M3.3 | ADR-0004: bundling the trust lists as data (source, commit, CC BY 4.0 attribution, the DigiCert fingerprint, how a copy is refreshed, the age threshold) | done 2026-11-04: ADR-0004 `proposed`; accepted 2026-11-04 |
| M3.4 | The M3 spec (`draft`), including the column that records the trust source in each row (an amendment to SPEC-002's table) | done 2026-11-04: SPEC-004 `draft`, with SPEC-002 amendment 1 and SPEC-003 amendment 1 proposed in it; approved 2026-11-04 with all recommendations |
| M3.5 | after approval: tests seen red, the implementation, green, Traceability | done 2026-11-04 (`notes/step-17-trust-settings.md`) |

**M3 is done (2026-11-04):** `Trusted` is reached by default on the Pixel 10
photo, and with custom settings on the module's own fixtures. The
configure page shows the list's date and warns 183 days after the last
comparison (SPEC-004). Next: M4, checking existing media again.

## M4 steps

Proposed 2026-11-04; one step at a time, each after an "ok". The aim
(above): check existing media again from the admin interface, with every
media item checked, the run interruptible, and a second run harmless.
Tracefern's re-check (its SPEC-008, SPEC-013, SPEC-017) is the blueprint;
each Omeka equivalent is measured first.

Read, not measured (Omeka S 4.2.1 source):
- Jobs extend `Omeka\Job\AbstractJob` and are started through
  `Omeka\Job\Dispatcher`, with one of two strategies.
  - `PhpCli` starts a background PHP process through Omeka's CLI service
    (`exec`, with a PHP path that can be set in Omeka's settings).
  - `Synchronous` runs the job in the request.
- A job can be stopped (`Dispatcher::stop()`); the job itself checks
  `shouldStop()`.

The module's "no `exec`" rule covers its verification path. Whether
relying on Omeka's own dispatcher is acceptable is part of M4.1's note.

| step | what | status |
|---|---|---|
| M4.1 | Measure Omeka S jobs in the test environment: dispatching with `PhpCli` (does it run in the container, which PHP path, what happens when it cannot start), `Synchronous`, the job table and its statuses, `shouldStop()` and `Dispatcher::stop()`, a job that throws, the job log, memory over many media (`EntityManager::clear()`), and where an administrator can start one (a button on the configure page, or a page of the module's own, and the ACL for it) | done 2026-11-04 (`notes/step-18-jobs.md`) |
| M4.2 | Measure a re-check at scale with a probe job: a few thousand media of the fixture types in the test environment; time per item, memory, stop and start again, two runs at once, an upload during a run; and what a re-check does to an existing row (SPEC-002's upsert) | done 2026-11-04 (`notes/step-20-recheck-at-scale.md`) |
| M4.3 | The M4 spec (`draft`). Choices to put to Maurice: which media a run checks (all, those without a row, those checked with another verifier version or trust source, those whose file changed since the check); whether a re-check replaces the row or keeps the first verdict until M5's evidence keeps history; who may start a run; how progress shows | done 2026-11-04 (`specs/SPEC-005-recheck-existing-media.md`, approved) |
| M4.4 | after approval: tests seen red, the implementation, green, Traceability | done 2026-11-04 (`notes/step-21-recheck.md`) |

**M4 is done (2026-11-04):** a global administrator checks existing
media again from the configure page, all of them or those without a
result, as an Omeka job that can be stopped and run again with the same
result (SPEC-005). The settings texts name the runs (SPEC-004 amendment
2). Next: M5, the evidence.

## M5 steps

Proposed 2026-11-04; one step at a time, each after an "ok". The aim
(above): keep the verifier's evidence document (format version 1) for
each check, viewable and downloadable. The stored document must be
byte-identical to `evidenceToJson()` and must survive a verifier update.
Decision 5 in `NOTES.md` already chose the place: a table of the
module's own, one row per check, never updated.

Read, not measured (verifier v0.10.0, `docs/evidence-format.md`):
- `VerificationReport::evidenceToJson()` gives an ASCII document that is
  the same for the same file, settings and validation time.
- It holds the verdict, the trust lists, and for each judged manifest
  the certificates, time-stamp tokens and OCSP responses as bytes. It
  holds no hash of the file; saying which file it belongs to is the
  archive's task.
- `trust_lists` names only lists given through
  `TrustSettings::fromTrustLists()`; for a settings document
  (`fromJson()`, which SPEC-004 uses) it is `[]`. The evidence then does
  not say which lists judged the file; only the row's `trust` column
  does. `fromTrustLists()` takes one signer list and one TSA list, so
  DigiCert's anchor would have to be joined to the TSA list, which
  changes that list's hash.
- A file without a manifest, and a check that ends in `error`, have
  either an evidence document with no manifests or no report at all.
  Which of the two is to measure.

Open for Maurice, after measuring:
- whether each re-check (M4) adds a document, or only one whose content
  differs from the last;
- what happens to the evidence when its media item is deleted, and when
  the module is uninstalled;
- who may view and download it;
- whether the bundled lists move to `fromTrustLists()`, so the evidence
  names them (a SPEC-004 amendment).

| step | what | status |
|---|---|---|
| M5.1 | Measure the evidence document with the module's fixtures and settings, outside Omeka: its size per fixture (the Pixel 10 photo, the OCSP fixture, an unsigned file, an altered one), whether it is ASCII and deterministic, what `trust_lists` holds with `fromJson()` and with `fromTrustLists()` (and whether the verdicts stay the same), what `none` and `error` give, and what writing it costs per check | done: [`notes/step-23-evidence-document.md`](../notes/step-23-evidence-document.md) |
| M5.2 | Measure viewing and downloading in Omeka S: a route, controller and ACL of the module's own (a probe module); a download with `Content-Type: application/json` and `Content-Disposition`; a link from the media page's block; which roles can reach it, compared with who can see the media; a MariaDB `LONGTEXT` that gives the document back byte-identical; the table's growth over repeated runs (M4.2's 3 000 media) | done: [`notes/step-24-evidence-in-omeka.md`](../notes/step-24-evidence-in-omeka.md); measured over the 1 103 media present, not 3 000 |
| M5.3 | ADR-0005, the evidence table: columns, keys and indexes; append-only; what a media deletion and an uninstall do to it; and, if M5.1 calls for it, the move to `fromTrustLists()` | done: [`ADR-0005`](adr/ADR-0005-evidence-table.md), accepted 2026-11-05 |
| M5.4 | The M5 spec (`draft`), with the open choices above and recommendations | done: [`SPEC-006`](../specs/SPEC-006-keep-the-evidence.md), approved 2026-11-05 with all recommendations |
| M5.5 | after approval: tests seen red, the implementation, green, Traceability | done: [`notes/step-25-keep-the-evidence.md`](../notes/step-25-keep-the-evidence.md) |

**M5 is done (2026-11-05):** every check with Content Credentials keeps
the verifier's evidence document, byte-identical to `evidenceToJson()`
and never changed afterwards. A row from an older verifier stays as it
was through later runs (SPEC-006 AC5, with a row the test set to `0.9.0`;
no real verifier update was performed). Next: M6, the AI label.

## M6 steps

Proposed 2026-11-05; one step at a time, each after an "ok". The aim
(above): the label "AI-generated (signed)", only on a manifest that
verifies, as SPEC-003 of Tracefern.

Read, not measured here (Tracefern's `specs/SPEC-003-ai-label.md`,
`specs/SPEC-027-ai-in-the-history.md`, `notes/m3-ai-label.md`, measured
there with older verifier versions; this repository's
`notes/step-16-trust-lists.md`, `notes/step-13-admin-views.md`):
- The statement is an IPTC digital source type on an action, in
  `toArray()`: `manifests[…].assertions[]`, `c2pa.actions` or
  `c2pa.actions.v2`, `data.actions[].digitalSourceType`. Tracefern reads
  it from there and never from `$report->store`, which is `@internal`.
- Tracefern's SPEC-003 counted only the exact URI of
  `trainedAlgorithmicMedia` in the active manifest. Its SPEC-027 later
  found that this misses two kinds of real file: an AI image processed
  afterwards, with the statement in a `parentOf` ingredient, and a photo
  edited with AI (`compositeWithTrainedAlgorithmicMedia`). It added
  "AI-edited (signed)" and a gate on ingredients.
- The evidence document holds no assertions (the verifier's
  `docs/evidence-format.md`), so the statement cannot be read back from
  it; it needs a column of its own, as `trust` got (SPEC-002 amendment 1).
- This module has no fixture with such a statement. Tracefern's OpenAI
  image turns `Invalid` on 2027-04-23 (step 16), so a test built on it
  would break then. Tracefern also has `c2pa-verifier-ai-parent-chain.png`
  (MIT) and Amazon Titan (Apache-2.0).
- Where a label can be shown is measured (step 13); the block, the column
  and the filter exist (SPEC-003).

Open for Maurice, after measuring:
- only the active manifest, as M6's row says, or the history and
  "AI-edited (signed)" too, as Tracefern ended up doing;
- whether the browse filter gets the label as well;
- which fixtures to copy, and whether to make one with a long life.

| step | what | status |
|---|---|---|
| M6.1 | Measure the statement outside Omeka with verifier 0.10.0 and the module's settings: per fixture (this module's and Tracefern's AI fixtures) the verdict, the source types per manifest and their ingredient relationships, what `toArray()` gives for a malformed or tampered statement, how long each verdict holds (as in step 16), and which fixture can carry the tests for years | done: [`notes/step-26-ai-statement.md`](../notes/step-26-ai-statement.md) |
| M6.2 | The M6 spec (`draft`), with the open choices above and recommendations, and the amendments it needs: SPEC-002 (the column), SPEC-003 (the label in the block and the column), and any other spec the change touches | done: [`specs/SPEC-007-ai-label.md`](../specs/SPEC-007-ai-label.md), approved 2026-11-05 |
| M6.3 | after approval: tests seen red, the implementation, green, Traceability | done: [`notes/step-27-the-ai-label.md`](../notes/step-27-the-ai-label.md) |

No ADR is foreseen: no new dependency, and a column in the module's table
follows ADR-0003. If M6.1 shows otherwise, a step is added.

**M6 is done (2026-11-05):** a file that verifies and says it was made or edited
with generative AI shows "AI-generated (signed)" or "AI-edited (signed)" on the
media page and in the browse, which can filter on either. This is wider than
M6's row above, by Maurice's decision in SPEC-007. Next: M7, the release build.

## M7 steps

Proposed 2026-11-05; one step at a time, each after an "ok". The aim
(above): a zip with a prefixed verifier that installs on a clean Omeka S, an
uninstall that removes everything, and then the decision on registering on
omeka.org. Nothing is published in M7 without Maurice's go.

Read, not measured for this module as it is now (this repository's
`notes/step-11-loading-the-verifier.md`, `notes/step-21-recheck.md`,
`docs/adr/ADR-0002-dependencies.md`, SPEC-006; Tracefern's `tools/build.sh` and
SPEC-009):
- Step 11 measured Strauss 0.30.0 on a build copy of M1: the verifier moves to
  `vendor-prefixed/` under `ContentCredentials\Vendor\`. The rewrite covers `use`
  lines and namespace strings, not paths. The path
  `vendor/provemark/c2pa-verifier/src` is written out in three places
  (`TrustConfig`, `RecheckJob`, `AfterFlushListener`), so the build has to
  arrange those. Everything since M1 has never been prefixed.
- Strauss enters as a build tool with its own ADR or an amendment to ADR-0002,
  which says so. Strauss 0.31.1 is not measured.
- Tracefern builds from `git archive HEAD`, with `export-ignore` in
  `.gitattributes`. This repository has no `.gitattributes` yet, and tests,
  fixtures, probes, specs and notes must stay out of the zip.
- The integration tests run against the mounted repository, not against a zip.
  "Installs on a clean Omeka S" needs an environment of its own, as the tests
  reinstall the module in the one on port 8088.
- `Requirements` refuses PHP below 8.3 at install. Whether a built zip still
  does this on PHP 8.2 is to be measured, as the prefixed verifier is not
  loadable there.
- Uninstall drops both tables and deletes the two settings. Left behind, as far
  as is recorded: the module's rows in Omeka's `job` table (step 21). The uninstall
  confirmation does not warn that the evidence goes (ADR-0005, SPEC-006). Which
  hook that page offers has not been measured.
- A bundled trust list is judged by its age from the last comparison with
  upstream (ADR-0004), so `tools/trust/compare.sh` belongs before a release.

Open for Maurice, after measuring:
- whether uninstall deletes the module's job rows, which Omeka itself keeps for
  its own jobs, or leaves them and says so;
- whether the warning about the evidence is feasible where the choice is made,
  and otherwise where it goes;
- the version number of the first release (it stays 0.1.0 until then);
- whether CI builds the zip and installs it on every push, or only at a release;
- whether to register on omeka.org, and when the repository goes public.

| step | what | status |
|---|---|---|
| M7.1 | Measure the build on a copy, never shipped: Strauss (0.30.0 against 0.31.1), the prefixed verifier and the paths, what is in the zip and its size; the zip on a clean Omeka S (PHP 8.3, in a separate environment) with verdicts and evidence documents compared with the verifier's CLI per fixture; the refusal on PHP 8.2; next to a module that loads another copy of the verifier | done: [`notes/step-28-the-release-build.md`](../notes/step-28-the-release-build.md) |
| M7.2 | Measure uninstall: a full comparison of database, settings and files before install and after install, use and uninstall (a check on upload, a re-check, evidence); what the job rows hold and what deleting them does; which hook Omeka's uninstall confirmation offers | done: [`notes/step-29-uninstall.md`](../notes/step-29-uninstall.md) |
| M7.3 | The ADR for the build tool (or an amendment to ADR-0002), on what M7.1 measured | done: [`docs/adr/ADR-0006-prefixing-the-verifier.md`](adr/ADR-0006-prefixing-the-verifier.md), accepted 2026-11-06 |
| M7.4 | The M7 spec (`draft`), with the open choices above and recommendations, and the amendments it needs: SPEC-001 (uninstall), SPEC-006 (the warning, if it comes) | done: [`specs/SPEC-008-release-build.md`](../specs/SPEC-008-release-build.md), approved 2026-11-06 |
| M7.5 | after approval: tests seen red, the implementation (`tools/build.sh`, `.gitattributes`, the verifier's path, uninstall, the CI job if chosen), green, Traceability | done: [`notes/step-30-the-release-build-built.md`](../notes/step-30-the-release-build-built.md) |
| M7.6 | The release check, not the release: the bundled lists compared with upstream, the version number, README and CHANGELOG, what omeka.org asks of an add-on read and written down; Maurice decides on a public repository and registration. Nothing goes public before that go | checked: [`notes/step-31-the-release-check.md`](../notes/step-31-the-release-check.md); the decisions are open |
| M7.7 | Refresh the two C2PA lists to `64b3f9c` (SPEC-004 and SPEC-005 amended) | done: [`notes/step-32-refreshing-the-lists.md`](../notes/step-32-refreshing-the-lists.md) |
| M7.8 | The zip's tests on PHP 8.4 and 8.5, as the README promises "8.3 or later" | done: [`notes/step-33-the-zip-on-newer-php.md`](../notes/step-33-the-zip-on-newer-php.md) |
| M7.9 | README and CHANGELOG ready to publish; the version stays 0.1.0 | done: `README.md`, `CHANGELOG.md`; [`notes/step-34-readme-and-paths.md`](../notes/step-34-readme-and-paths.md) |
| M7.10 | The probes' local paths made relative; the history is not rewritten | done: [`notes/step-34-readme-and-paths.md`](../notes/step-34-readme-and-paths.md) |
| M7.11 | A run whose process has gone can be ended from the configure page (review finding 1, SPEC-005) | done: [`notes/step-36-ending-a-run-that-has-gone.md`](../notes/step-36-ending-a-run-that-has-gone.md) |
| M7.12 | A run that failed is not `completed` (review finding 2, SPEC-005) | done: [`notes/step-37-a-run-that-fails.md`](../notes/step-37-a-run-that-fails.md) |
| M7.13 | The AI label reads instance labels (review finding 3, SPEC-007) | done: [`notes/step-38-instance-labels.md`](../notes/step-38-instance-labels.md) |
| M7.14 | The smaller findings 4 to 8 of the review | done: [`notes/step-39-a-refused-form.md`](../notes/step-39-a-refused-form.md), [`notes/step-40-a-refused-start.md`](../notes/step-40-a-refused-start.md), [`notes/step-41-a-damaged-list.md`](../notes/step-41-a-damaged-list.md), [`notes/step-42-invisible-characters.md`](../notes/step-42-invisible-characters.md), [`notes/step-43-the-signer-line.md`](../notes/step-43-the-signer-line.md) |
| M7.15 | Clean-ups 1 to 3 of the review, before the release (Maurice, 2026-11-07): two unused constants, the build's silence when Strauss fails | done: [`notes/step-44-two-unused-constants.md`](../notes/step-44-two-unused-constants.md), [`notes/step-45-when-strauss-fails.md`](../notes/step-45-when-strauss-fails.md) |
| M7.16 | The check before going public, and a read-only CI (Maurice, 2026-11-07) | done: [`notes/step-46-before-going-public.md`](../notes/step-46-before-going-public.md) |
| M7.17 | A security policy, `SECURITY.md` | done: [`notes/step-47-security-policy.md`](../notes/step-47-security-policy.md) |
| M7.18 | Maurice's trial of the zip, before the release | done: [`notes/step-48-the-trial.md`](../notes/step-48-the-trial.md) |
| M7.19 | Release 0.1.0: tag `v0.1.0` and the GitHub release with the zip | done: [`notes/step-49-release-0-1-0.md`](../notes/step-49-release-0-1-0.md) |
| M7.20 | Retry GitHub's release downloads, after a 504 outage turned CI red | done: [`notes/step-50-retrying-downloads.md`](../notes/step-50-retrying-downloads.md) |
| M7.21 | CI keeps its downloads; the test image builds from the fetched Omeka S zip | done: [`notes/step-51-caching-downloads.md`](../notes/step-51-caching-downloads.md) |

**M7.5 is done (2026-11-06):** `tools/build.sh` makes the zip, which holds `vendor-prefixed/` and no
`vendor/` (ADR-0006; M7's row above says "prefixed `vendor/`"), and which is tested in a clean
Omeka S. Uninstalling removes the module's job rows too, and the description warns about the
evidence. What ships is a list in the build, not `.gitattributes`. Next: M7.6, the release check.

**M7.6's check is done (2026-11-06);** nothing is published. What follows depends on Maurice's
decisions in step 31: the lists, the version, the README, the zip on PHP 8.4 and 8.5, a public
repository, the registration. M7 is done when the zip is released, or when he decides otherwise.

**M7.7 to M7.10 are done (2026-11-06).** Maurice has looked at the zip in a browser. The review of
[step 35](../notes/step-35-the-code-review.md) added M7.11 to M7.14, which come before a release. After them,
what is left of M7 is Maurice's: a public repository, the release and the registration on omeka.org.

**M7 is done (2026-11-08):** the repository is public (2026-11-07) and 0.1.0 is released
([step 49](../notes/step-49-release-0-1-0.md)). The registration on omeka.org is Maurice's decision.
