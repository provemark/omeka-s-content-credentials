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
