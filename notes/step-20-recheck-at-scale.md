# Step 20 (M4.2): a re-check at scale

2026-11-04. Step 18 measured Omeka's jobs with a job that did no real
work. This step runs the module's own check (`MediaCheck`, `Recorder`,
`DbalVerdictStore`, the trust settings of SPEC-004) as a job over a few
thousand media. It measures time, memory, stopping and carrying on, two
runs at once, an upload during a run, and checking only what has no
verdict.

## Setup (measured)

- PHP 8.3 container (Omeka S 4.2.1, PHP 8.3.36, MariaDB 11.4), Docker on
  a Mac.
- `tools/probes/RecheckProbe/scripts/seed.php` added 253 items with 10
  media each (3 + 250) through the URL ingester, from the module's
  fixtures. ContentCredentials was deactivated while seeding (348 s for
  2 500 media). The mix repeats every 20 media:
  - `fixture-signed.jpg` ×8 (97 kB);
  - `fixture-signed.png` ×3 (48 kB);
  - `fixture-signed.webp` ×3 (101 kB);
  - `fixture-unsigned.jpg` ×4 (2 kB);
  - `c2pa-rs-ocsp.jpg` ×1 (286 kB);
  - the Pixel 10 photo ×1 (5.8 MB).
- With the earlier test media that made 3 097 checkable media (JPEG, PNG,
  WebP with an original), 14 of which had a verdict.
- `tools/probes/RecheckProbe/` (never shipped) holds `RecheckJob`. The
  job reads media rows through DBAL in batches of 200, ordered by id,
  after a given id. For each it builds the original's path with
  `Omeka\File\Store::getLocalPath()`, calls `Recorder::record()`, and
  asks `shouldStop()` every 50 items (every 10 in the stop case). The
  trust settings are built once per run, or per item for comparison.
  Markers go to `/tmp/recheck-probe.log`.
- `scripts/measure.php` dispatches the job through the probe's configure
  page with Omeka's default strategy (`PhpCli`) and watches the `job`
  table.

## Measured

| case | result |
|---|---|
| full run, trust built once | 3 097 media in 14.9 s; 3 097 rows afterwards, 0 `error`, 0 recorder failures; memory 12.0 MB at the start, 18.0 MB from item 500 on, peak 18.0 MB; average per item: JPEG 6.0 ms (2 219, including 111 copies of the 5.8 MB photo), PNG 1.7 ms, WebP 2.2 ms |
| an existing row | replaced in place (`INSERT … ON DUPLICATE KEY UPDATE`): media 1241's `checked_at` went from 12:40:38 to 13:27:48 |
| first 500 media, trust built per item vs once | 6.6 s vs 1.8 s; per item JPEG 13.8 vs 4.2 ms, PNG 10.6 vs 1.2 ms. Building the bundled settings costs about 9 ms an item (step 16 measured about 15 ms per upload) |
| stop after 8 s through `/admin/job/{id}/stop`, then a new run after the highest checked id | stop: HTTP 302, job `stopped` after 1 880 items (last id 2736), every checked item stored; the new run from id 2736 checked the remaining 1 217 in 6.0 s; 3 097 rows, `completed` |
| two runs of 1 000 started together | both `completed` in 4.7 s, over the same ids; 3 097 rows, no duplicates (the primary key and the upsert), 0 failures; the result is the same as one run |
| an admin upload 5 s into a full run | the upload answered 302 in 0.29 s and got its own verdict (`Valid`); the run, which reads ids in batches, picked up the new media too (3 098) |
| only media without a verdict, after deleting every tenth row (324) | 324 checked in 2.3 s; 3 098 rows |
| deleting the seed items through `ApiManager::batchDelete()` | their verdict rows went with them (0 rows without a media item; the foreign key of SPEC-002); 616 media and 616 files in `files/original` afterwards |

The verdicts after the runs: `Valid` 2 344, `none` 576, `Trusted` 140,
`Invalid` 38, all with trust source `c2pa-2026-10-17+digicert`.

Cleanup: the probe was uninstalled and removed, its 9 job rows deleted,
and the seed items deleted through the API. That deleted 254 items, one
more than seeded: an earlier test had left an item with exactly 10
URL-ingested media, the criterion I used.

## What follows for SPEC-005 (reasoned)

- **Time and memory are no reason to split the work.** About 200 media a
  second with small files, and memory flat at 18 MB, so one job can walk
  all media. Real archives hold larger originals (TIFF is out of scope;
  JPEGs of tens of MB are not), so time grows with bytes, not with
  rows. Hashing the file (`hash_file`) and parsing it are both linear in
  its size.
- **Build the trust settings once per run.** Per item it would triple
  the time. A setting changed during a run then applies from the next
  run, which the spec should say.
- **Carrying on after a stop needs no state of its own.** Walking by
  ascending id with "after id N" works. "Only media without a verdict"
  needs no position at all, but is not a re-check: it skips rows that
  are there but old. Which media a run takes is a choice for Maurice
  (M4.3).
- **Two runs at once do no harm** (same result, double work). Refusing a
  second run while one is `starting` or `in_progress` is a usability
  choice, not a correctness one.
- **Uploads and runs do not get in each other's way.**
- **Replace or keep:** the upsert replaces the row; the old verdict is
  gone. Keeping history is a schema change (and nears M5's evidence).

## Not measured

- Large originals (tens of MB) one by one, a NAS or object store, and
  hosts slower than this Mac.
- A progress display (the probe logs to a file; Omeka's job page shows
  only status and the log).
- PHP 8.2 or a host without `exec` (step 18 covers what happens there).
