# Changelog

This project follows the milestones in `docs/milestones.md`. Dates are the
day the work was committed.

## Unreleased

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
