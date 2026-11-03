# ADR-0001: PHP 8.3 as the floor

| Field    | Value            |
|----------|------------------|
| Status   | proposed         |
| Date     | 2026-11-03       |
| Decided  | —                |

## Context

Omeka S 4.2.1 runs from PHP 8.1 (`composer.json`: `"php": ">=8.1"`;
`Omeka\Stdlib\Environment::PHP_MINIMUM_VERSION` is `8.1.0`). The module
verifies with `provemark/c2pa-verifier`, which requires `^8.3` (its
`composer.json`) and, on 8.3, `ext-sodium` for Ed25519 signatures.
The verifier is the module's reason to exist; a module without it is empty.

Measured 2026-11-03 (`notes/step-04-dependencies.md`): in the
`composer.lock` that ships inside the Omeka S 4.2.1 release, no package
excludes PHP 8.3; 4 of 81 packages exclude PHP 8.4 (`laminas-dom`,
`laminas-mvc-plugin-fileprg`, `laminas-mvc-plugin-prg`,
`laminas-zendframework-bridge`, each `~8.1.0 || ~8.2.0 || ~8.3.0`); and
29 exclude PHP 8.5, all Laminas packages. These are declared ranges, not
measured failures.

The same trade-off was made for Drupal 10 in `provemark/c2pa-verifier`:
a floor above the host's, stated plainly.

## Decision

The module requires PHP 8.3 or later. Installations of Omeka S on PHP 8.1
or 8.2 cannot use it.

- `composer.json` requires `"php": "^8.3"` and sets the platform to
  `8.3.0`, so the lock file always installs on the floor.
- `config/module.ini` will state it where Omeka S lets a module state it
  (to measure in M0.5), and the module will refuse to install, with a
  message, on an older PHP rather than fail later.
- The integration environment runs PHP 8.3: the one version inside both
  the verifier's range and every range Omeka S 4.2.1's own dependencies
  declare.
- CI (once there is a remote) runs the unit tests on PHP 8.3, 8.4 and 8.5,
  as the verifier does. Whether the integration tests also run on 8.4 and
  8.5 is decided when CI is built, after measuring Omeka S itself there.

## Consequences

- Institutions still on PHP 8.1 or 8.2 are excluded until they upgrade.
  The README says so under its requirements.
- For Omeka S 4.2.1, the supported combination is in practice PHP 8.3; on
  8.4 and 8.5 the module works as far as Omeka S itself does, which this
  project does not promise.
- Raising the floor later is a new ADR.
