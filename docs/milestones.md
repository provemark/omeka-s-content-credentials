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
| M4.2 | Measure a re-check at scale with a probe job: a few thousand media of the fixture types in the test environment; time per item, memory, stop and start again, two runs at once, an upload during a run; and what a re-check does to an existing row (SPEC-002's upsert) | proposed |
| M4.3 | The M4 spec (`draft`). Choices to put to Maurice: which media a run checks (all, those without a row, those checked with another verifier version or trust source, those whose file changed since the check); whether a re-check replaces the row or keeps the first verdict until M5's evidence keeps history; who may start a run; how progress shows | proposed |
| M4.4 | after approval: tests seen red, the implementation, green, Traceability | proposed |
