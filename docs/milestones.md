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
| M0.4 | ADR-0001 the PHP floor (8.3: installations on 8.1 and 8.2 excluded); ADR-0002 the dependencies (the verifier, the development tools); then `composer.json` | proposed |
| M0.5 | An empty module skeleton (`config/module.ini`, `Module.php`, `config/module.config.php`); measure whether the namespace must equal the directory, and that it installs and uninstalls cleanly in the pinned Omeka S | proposed |
| M0.6 | `specs/TEMPLATE.md` and SPEC-000 with `bin/spec-check.php` (a port from the verifier), tests first and seen red | proposed |
| M0.7 | `composer check`: spec-check, Pint, PHPStan at level max, Deptrac, Pest (unit, and integration with the install test); green on an empty `src/` | proposed |
