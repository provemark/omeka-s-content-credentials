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
| M1.2 | Measure a module-owned table (entity, install and uninstall SQL) and the hook after the flush; ADR-0003 storage | done 2026-11-04 (`notes/step-10-storage-and-after-flush.md`); ADR-0003 proposed |
| M1.3 | Measure loading the verifier without the module's whole `vendor/` | proposed |
| M1.4 | The M1 spec (`draft`), resting on M1.1 to M1.3 | proposed |
