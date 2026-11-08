# Step 50 (M7.20): retrying GitHub's release downloads

2026-11-08. After release 0.1.0 (step 49), CI went red on `6464e54` and on the tag `v0.1.0`: every job
that downloads a GitHub release (Omeka S 4.2.1, for PHPStan, the Docker image and the release zip)
failed with `curl: (22) The requested URL returned error: 504`, from 08:48 UTC on. The jobs that
download nothing passed. Rerunning the failed jobs at 08:50 failed the same way: the assistant
reran after a single successful download, while GitHub's downloads kept failing. Maurice asked for a
retry ("ok, voeg de --retry toe").

## What was measured

Against a local HTTP/1.1 server that answers 504 or 503 a given number of times, then 200
(curl 8.7.1, macOS):

| flags | server | result |
|---|---|---|
| none | 2 × 504 | exit 22, 1 request |
| `--retry 8 --retry-delay 2` | 2 × 504 | exit 0, 3 requests |
| `--retry 8 --retry-delay 2` | 3 × 503 | exit 0, 4 requests |
| `--retry 8 --retry-delay 1` | 9 × 504 | exit 22, 9 requests (gives up) |
| `--retry 8 --retry-delay 2` | 404 | exit 22, 1 request (not retried) |

Against GitHub itself, while it answered 504 (Strauss 0.31.1's release download):

| flags | result |
|---|---|
| `--retry 2 --retry-delay 3` | **exit 56, 1 attempt, not retried** |
| `--retry 2 --retry-delay 3 --http1.1` | exit 22, 3 attempts, 6 s |
| `--retry 2 --retry-delay 3 --retry-all-errors` | exit 56, 3 attempts, 6 s |

So `--retry` alone does nothing here: over HTTP/2, curl 8.7.1 reports GitHub's 504 as error 56, a
receive error, which `--retry` does not count as transient. The local server spoke HTTP/1.1, which is
why the first table looked right. What curl 8.5 on the CI runner and curl 8.14.1 in the Docker image
do over HTTP/2 was not measured; `--http1.1` makes them all take the path measured above.

## What was built

The three downloads of a GitHub release use `--http1.1 --retry 10 --retry-delay 30`, with a comment
on each: Omeka S in `docker/omeka/Dockerfile`, Strauss in `tools/build.sh`, Omeka S in
`tools/fetch-omeka.sh`. Ten retries 30 s apart cover about five minutes. `--retry-all-errors` was not
chosen: it would also retry a 404, so a wrong version would fail only after five minutes. The probe
scripts in `tools/probes/` are not changed; they are a record.

## Measured on the change

`tools/build.sh` with the Strauss cache emptied, at 08:54:37 UTC while GitHub still answered 504:
seven `curl: (22) … 504` lines, then the download succeeded, 215 s in all; the phar is byte-identical
to the cached copy, and the build went on to a zip. (That build overwrote the local
`build/ContentCredentials-0.1.0.zip`; the released zip is the one on GitHub, step 49.)

After GitHub recovered, `composer check` ran green: 347 unit, 144 integration and 23 release tests.

## Limits

- A GitHub outage longer than about five minutes still fails CI. This one lasted longer: downloads
  answered 504 from before 08:48 to at least 09:05:55 and 200 again by 09:11 (polled by the
  assistant); GitHub's status page showed no incident at 09:05. The first `composer check` on the
  change failed for this reason: rebuilding the test image, the Dockerfile's curl (8.14.1) logged
  eleven 504s per image over five minutes and gave up, which also measures the retry inside the
  image.
- The hash checks after each download are unchanged; a retry cannot let another file through.
