# Step 44 (M7.15): two unused constants removed

2026-11-07. Clean-ups 1 and 2 of the review (step 35, "Clean-ups, not before a release"), which
Maurice moved before the release: `TrustConfig::SETTINGS` and `Recheck::STATES` were read nowhere.

## Measured

- `grep` over `src`, `Module.php`, `tests`, `specs` and `docs`: each name only at its definition.
  `Recheck::endLine()` names the five states itself; the uninstall deletes the two settings by name.
- After removing them: PHPStan level max no errors, Deptrac no violations, the unit suite 347 passed.

No behaviour changes, so no spec and no new test: what would show a mistake is PHPStan, which reports
any use of a constant that does not exist.
