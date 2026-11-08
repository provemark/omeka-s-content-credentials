# Step 53 (M7.23): Dependabot for the GitHub Actions

2026-11-08. Since step 46 (M7.16) the workflow pins each action to a commit SHA, with the version in a
comment. A SHA never moves, so the pins do not pick up fixes either; nothing told us when
`actions/checkout`, `actions/cache` or `shivammathur/setup-php` had a newer release. Proposed after the
release as maintenance, together with step 52; split from it as its own concept.

## What was built

`.github/dependabot.yml`: version updates for the `github-actions` ecosystem in `/` (which covers
`.github/workflows/`), monthly, at most five open pull requests. A Dependabot pull request changes the
SHA and the version comment, and runs CI like any other pull request: with `contents: read`
(step 46), so it can do no more than any outside pull request.

Left out, on purpose:

- **Composer.** The verifier (`provemark/c2pa-verifier`, `^0.10`) is bundled with Strauss into the
  release zip, so an update of it changes what ships: a release decision, made by hand with
  `composer update` and the full `composer check`. The development tools are in the same lock file.
- **Dependabot security updates and alerts** (repository settings, both `disabled` per
  `gh api repos/provemark/omeka-s-content-credentials --jq .security_and_analysis`). Turning them on
  is a repository setting, which is Maurice's.
- **Weekly.** The workflow uses three actions; monthly is enough, and is the rhythm Maurice can follow
  next to the trust lists.

Of the sister projects only `provemark/content-credentials` has a `dependabot.yml` (npm, composer and
actions, weekly, `gh api …/contents/.github/dependabot.yml`); `c2pa-verifier` and
`tracefern-image-check` have none.

## Measured

- The file parses as YAML (`ruby -ryaml`), with `version: 2` and one update entry.
- After the push: see the record below.
