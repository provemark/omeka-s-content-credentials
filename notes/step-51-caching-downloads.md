# Step 51 (M7.21): CI keeps its downloads

2026-11-08. Step 50's retry covers about five minutes of GitHub download errors. The outage came back
at 09:34 UTC and lasted longer: in CI run 42467741569 (`75195f7`) the retry saved four jobs (8, 3, 2
and 3 failed attempts before a download succeeded) and two jobs gave up after eleven 504s over five
minutes. Maurice: "ok, werk stap 51 uit".

## What was built

- **One download, one place.** `tools/fetch-omeka.sh` downloads the Omeka S release zip into
  `build/.tools/` only when it is missing, checks its SHA-256 every time, and puts a copy next to the
  Dockerfile, `docker/omeka/omeka-s-4.2.1.zip` (ignored by git), replacing a copy with other bytes.
  It unpacks into `build/omeka-s/` for PHPStan as before, and skips only that when already done.
  Before, it returned early on an unpacked copy, before looking at the zip.
- **The image no longer downloads Omeka S.** `docker/omeka/Dockerfile` copies the zip from its build
  context and checks the SHA-256 again. `tools/env/up.sh` and `tools/env/release.sh` run
  `tools/fetch-omeka.sh` before `docker compose build`.
- **CI keeps the downloads.** The three jobs that download (static checks, integration, release zip)
  restore and save `build/.tools/omeka-s-*.zip` with `actions/cache`, keyed on
  `docker/omeka/Dockerfile`; the release jobs also `build/.tools/strauss-*.phar`, in a cache of its
  own keyed on `tools/build.sh`. Those files pin the versions and hashes. A changed key falls back to the previous files; the scripts download only a file
  that is missing (a new version has a new name) and check every hash, so a cached file can never
  replace a pinned one.

## Tried first, not kept

Compose's `additional_contexts`, passing `build/.tools/` to the build directly. Locally it failed:
"the classic builder doesn't support additional contexts" (Docker 29.5.2 on Colima, Compose 5.3.1,
no buildx plugin). It would have worked on the CI runner, but it would have made buildx a new local
requirement; the copy in the build context works with both builders.

## Measured

- Without a fetched copy, `docker compose build --no-cache omeka` stops: "COPY failed: file not
  found in build context … omeka-s-4.2.1.zip".
- After `tools/fetch-omeka.sh`, the build passes: `COPY omeka-s-${OMEKA_VERSION}.zip`,
  `/tmp/omeka-s.zip: OK`.
- With another file in place of the copy (the 0.1.0 module zip), the build stops at the hash:
  `sha256sum: WARNING: 1 computed checksum did NOT match`. The next `tools/fetch-omeka.sh` replaced
  it with the right zip (`cmp`), and the build passed again.
- `composer check` on the change: 347 unit, 144 integration and 23 release tests passed; the test
  images were rebuilt from the copy.
- `git grep github.com/omeka -- docker compose.yaml tools/env`: no match; the images contact GitHub
  for nothing. (They still pull the PHP and MariaDB images from Docker Hub and run `apt-get`.)

## One cache first, two after (measured)

The first commit (`f703c07`) used one key for both files in all three jobs. A key is saved once, by
the first job to finish: `gh cache list` showed one `downloads-…` entry of 17,056,755 bytes, saved at
10:26:12 by a static-checks job, which has no Strauss. The release jobs would have downloaded Strauss
on every run. Split into two caches the same day.

## Not yet measured

A CI run that fills the cache and a later run that restores it without downloading. At 10:25 UTC,
when this was committed, GitHub's release downloads still answered 504.
