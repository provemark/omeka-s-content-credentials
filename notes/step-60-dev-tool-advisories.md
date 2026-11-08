# Step 60 (M7.30): the development tools' advisories

2026-11-08. Step 58 found three advisories in `composer audit`, in development tools only.
Maurice: "ok, begin met 1 tot en met 3".

## What was measured

- `composer audit` before: `symfony/config` (XXE in `XmlUtils::parse()`), `symfony/filesystem`
  (a path prefix before `://` treated as a root) and `symfony/yaml` (exponential parse times), all
  fixed in 7.4.23. `composer audit --no-dev`: none, so the zip was never affected.
- `composer why`: the three come only from `deptrac/deptrac` 4.7.2 (and from each other).

## What was done

`composer update symfony/config symfony/filesystem symfony/yaml`: `config` 7.4.22 → 7.4.23,
`filesystem` 7.4.21 → 7.4.23, `yaml` 7.4.22 → 7.4.23; only `composer.lock` changed. No new
dependency, so no ADR. `composer audit` after: "No security vulnerability advisories found."

`composer check`: green, 349 unit, 146 integration and 23 release tests.

## Limits

Advisories appear over time; Dependabot (step 53) covers the GitHub Actions only, not Composer.
Running `composer audit` before each release is the habit this step suggests.
