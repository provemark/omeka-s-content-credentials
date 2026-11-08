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

## Measured in CI

- Run 42472189621 (`ef0ebf5`), attempt 1: green at 10:51 UTC, while GitHub's release downloads were
  still unsteady (the retry of step 50 carried the downloads). It saved `omeka-s-25087d72…`
  (17,056,753 bytes, from a static-checks job at 10:37:45) and `strauss-66233575…` (1,971,714 bytes,
  from a release job at 10:46:28) (`gh cache list`).
- The same run, attempt 2, every job rerun: green. Each of the seven jobs that download logs "Cache
  restored from key" for `omeka-s-…`, and each of the three release jobs also for `strauss-…`. The
  log has no `curl:` line and no `releases/download` address. (curl runs silently unless it fails, so
  the absence of those lines shows no failed download, not that none was attempted; that none was
  attempted follows from the scripts, which download only a missing file.)

## Limits

- GitHub evicts a cache unused for seven days. On a quiet repository the next run downloads again,
  with step 50's retry.
- The images still pull the PHP and MariaDB images from Docker Hub and run `apt-get`; an outage there
  is not covered.
- At 11:05 UTC GitHub's release downloads still answered 502 and 503, and a request could hang
  without an answer: curl has no time limit here. Not changed in this step.

## The tag run (measured)

The tag run of `v0.1.0` (42464340662) runs the code as tagged, without the retry and without the
cache, so it could only pass once GitHub's release downloads were steady again. A loop fetched the
Omeka S zip once a minute (`curl --http1.1 -m 90`) and counted answers of 200 in a row: ten in a row
at 11:56:20 UTC. Its failed jobs were then rerun (`gh run rerun 42464340662 --failed`), and attempt 4
was green at 12:07:59 UTC (`gh run view 42464340662 --json attempt,conclusion,updatedAt`). Release
0.1.0 has a green CI run.
