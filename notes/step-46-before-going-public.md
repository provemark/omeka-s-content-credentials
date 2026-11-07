# Step 46 (M7.16): the check before going public, and a read-only CI

2026-11-07. Maurice asked for a check of the repository before it is made public, and then for the
two changes it proposed ("ok, doe punt 3 en 4"). Nothing is public yet; that step is his. This note
covers the check and the first change; the second, `SECURITY.md`, is step 47.

## The check (measured, read only)

On `7501f40`, 81 commits, all branches (`git log --all`):

- **Authors:** all 81 by one author and committer, `16209371+Maurice1969DB@users.noreply.github.com`.
  No commit message names a co-author or another tool.
- **The local brief** was never committed; it is the first line of `.gitignore` and is named once in
  step 31.
- **Secrets:** `git log --all -p` searched for private key headers and for GitHub, AWS, Slack and
  API-key token patterns: none. Passwords found are those of the local Docker test environment
  (`test-only-admin-password`, and `omeka` / `root` for MariaDB), which open nothing else. The
  repository has no Actions secrets or variables (`gh secret list`, `gh variable list`).
- **Local paths:** about twenty lines in the history name `/Users/maurice/Documenten/…` (old probe
  files, step 34 removed them from the tree). They show a user name and folder names, nothing
  secret. The history is not rewritten, as decided in step 31.
- **Fixtures:** every file's source and licence is in `tests/Fixtures/c2pa/README.md`, with the
  licence texts beside them; the trust lists' in `trust/README.md`.
- **Linked repositories:** `provemark/c2pa-verifier`, `tracefern-image-check` and
  `content-credentials` are public (`gh repo view`), so the README's links work once this one is.
- **For Maurice to weigh:** his name (about 130 times in specs, milestones and `AI-LOG.md`), his
  quoted Dutch instructions in `AI-LOG.md`, his GitHub name, and the Composer name
  `mauricevanloon/…` become public. All of this is on purpose; it is listed so the choice is made
  knowingly.

## Read-only CI

Once public, anyone can open a pull request, and `ci.yml` runs on pull requests. Two changes:

- `permissions: contents: read` at the top. The last run's log already showed `Contents: read`,
  `Metadata: read`, `Packages: read` (measured: `gh run view --log`, run of `7501f40`), so the
  organisation's default is already restrictive; the line makes the workflow not depend on that
  setting, and drops `Packages`. No step writes: no upload, no release, no `gh`, no token used.
- Each action pinned to a commit instead of a tag, with the version as a comment:
  `actions/checkout` v7.0.1 (`3d3c42e`), `actions/cache` v6.1.0 (`55cc834`),
  `shivammathur/setup-php` 2.40.0 (`eb7c497`). Measured: these are the commits the tags pointed to
  (`gh api repos/<action>/commits/<tag>`), and the last run's log names the same SHAs for checkout
  and setup-php. So the workflow runs the same code as before; a moved tag no longer changes it.

## Not measured

- The organisation's default token setting itself: reading it needs `admin:org`, which is not
  asked for. Only its effect in the log is measured.
- How the run behaves on a pull request from a fork; there has been none.
