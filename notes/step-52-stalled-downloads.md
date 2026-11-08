# Step 52 (M7.22): ending stalled downloads

2026-11-08. Step 51 recorded that at 11:05 UTC a request to GitHub's release downloads could hang
without an answer, and that the downloads had no time limit: a hanging curl holds a CI job until
GitHub's six-hour job limit, and a local `composer check` indefinitely. Maurice asked for this step
after the release ("ok, werk stap 52 uit").

## What was measured

curl 8.7.1 on macOS, against a local server (`python3`) that accepts the connection and then either
answers nothing (*silent*), or sends headers and 1,000 of 1,000,000 bytes and stops (*stall*), or
stalls on the first request and answers in full on the next (*stall once*). `--retry 2
--retry-delay 1`, `--http1.1` throughout:

| limit | server | result |
|---|---|---|
| `-m 4` | silent | three times error 28 after 4 s, exit 28, 14 s |
| `-m 4` | stall | the same |
| `--speed-limit 1000 --speed-time 4` | silent | three times error 28 ("Operation too slow"), exit 28, 14 s |
| `--speed-limit 1000 --speed-time 4` | stall | the same |
| `--speed-limit 1000 --speed-time 4`, `--retry 1` | stall once | one error 28, then exit 0 with the full body, 5 s |

So both limits end a stalled transfer as error 28, and `--retry` retries error 28. The server logged
each retry as a new request.

A connection that never completes (`10.255.255.1`, unroutable):

| limit | result |
|---|---|
| `--speed-limit 1000 --speed-time 4` | error 28 only after 75 s, curl's own connect limit |
| `--connect-timeout 4` | error 28 after 4 s |
| `--connect-timeout 3 --speed-limit 1000 --speed-time 4 --retry 1` | twice error 28 after 3 s, exit 28, 7 s |

So the low-speed limit does not cover the connect phase; `--connect-timeout` does, and is retried too.

## What was built

The two downloads of a GitHub release, Omeka S in `tools/fetch-omeka.sh` and Strauss in
`tools/build.sh`, add `--connect-timeout 30 --speed-limit 1000 --speed-time 60`, with a comment. Since
step 51 the Docker image downloads nothing, so these are all the downloads there are.

`-m` (a limit on the whole transfer) was not chosen: it also ends a download that is slow but moving,
and the right number depends on the line. The low-speed limit ends a transfer only after a minute
below 1,000 bytes per second. A stall now costs about 90 s per attempt (60 s, plus the 30 s retry
delay), and ten retries bound the worst case at about 15 minutes (reasoned from the flags, not
measured).

## Measured on the change

- The exact flags against GitHub: the Omeka S 4.2.1 zip in 1 s, SHA-256 `cc27d1c7…`, equal to
  `OMEKA_SHA256` in `docker/omeka/Dockerfile`.
- With `build/.tools/` emptied of the Omeka S zip and the Strauss 0.31.1 phar:
  `sh tools/fetch-omeka.sh` exit 0 in 2 s and `sh tools/build.sh` exit 0 in 7 s, both files
  byte-identical to the copies set aside (`cmp`).
- `composer check`: green, 347 unit, 144 integration and 23 release tests.

## Limits

- The curl on the CI runner (8.5) and its behaviour on a real stall were not measured; `--connect-timeout`,
  `--speed-limit` and `--speed-time` are long-standing curl options (read in curl's manual, not
  measured on 8.5).
- The probe scripts in `tools/probes/` are not changed; they are a record.
