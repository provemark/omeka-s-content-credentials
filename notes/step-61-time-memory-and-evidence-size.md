# Step 61 (M7.31): time, memory and evidence size

2026-11-08. Findings L2 and L3 of the security review (step 58) were suspicions. This step measures
them. Maurice: "ok, begin met 1 tot en met 3". Nothing in the module was changed.

## L2: can a file make the upload request hit PHP's limits?

The module verifies inside the upload request. A fatal error (time or memory limit) there would give
a 500 on an upload that is kept. Measured with the verifier as the module calls it,
`(new Verifier)->verify($stream, null)`, timed with `hrtime`, `memory_get_peak_usage(true)`:

| file | size | macOS, PHP 8.5.12 | container, PHP 8.3.36 |
|---|---|---|---|
| the Pixel photo | 5,837,446 B | 0.03 s, peak 8.0 MiB | |
| the Amazon Titan PNG | 1,926,692 B | 0.03 s, peak 8.0 MiB | |
| the Pixel photo + 100 MiB of zeros | 110,695,046 B | 0.29 s, peak 8.0 MiB | |
| the Pixel photo + 500 MiB of zeros | 530,125,446 B | 1.35 s, peak 8.0 MiB | 2.55 s, peak 8.0 MiB |

The padded files end `Invalid` with `assertion.dataHash.mismatch` "sha256 over 529984223 of
530125446 bytes": the whole file was hashed, as a stream; memory does not grow with the file. (Without
trust settings the Pixel photo is `Invalid` by `signingCredential.expired`: its signer is accepted
only through the timestamp authority in the bundled lists, with which it is `Trusted` in Omeka.)

The environment's PHP allows uploads of 64 MiB (`upload_max_filesize`, `post_max_size`) and
128 MiB of memory. So the size of a file does not bring the check near PHP's limits: a file large
enough to take 30 s (PHP's default `max_execution_time` for the web) would be several GiB, far beyond
any upload limit (reasoned from the measured rate).

Not measured: a manifest built to be slow to parse (deep CBOR, many assertions). The verifier caps
the store at 16 MiB and has item limits; the review found no such file, and none was built.

**Conclusion:** no change. The limit stays as recorded in the review.

## L3: evidence that cannot be stored

The evidence column is `LONGTEXT` (`Schema.php`), so the limit is the database's
`max_allowed_packet`: 16,777,216 bytes in the test environment (MariaDB 11.4.13, `SELECT
@@max_allowed_packet`). Measured evidence sizes: 2,272 B (`fixture-signed.webp`), 7,866 B (Pixel),
21,947 B (Amazon Titan), 33,885 B (`c2pa-rs-ocsp.jpg`).

The verifier accepts a manifest store of at most 16 MiB (`DEFAULT_MAX_LBOX`,
`DEFAULT_MAX_CHUNK_LENGTH`). Evidence holds the store's certificates and time-stamp tokens in
base64, so a store built almost entirely of those could, in theory, give a document above 16 MiB
(reasoned, not built). The real files are three orders of magnitude below it. If it happens, the check
fails closed: "Could not be checked", no evidence, the upload kept.

**Conclusion:** no change; recorded as a known limit.

## Limits

- One machine and one container; a slow disk or a shared host is slower.
- The time of the whole upload in Omeka (thumbnails) was not measured here; Omeka's own derivative
  step is not the module's.
