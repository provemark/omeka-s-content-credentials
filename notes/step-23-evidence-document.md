# Step 23 (M5.1): the evidence document, outside Omeka

2026-11-05. M5 is meant to keep the verifier's evidence document for each
check (format version 1, `VerificationReport::evidenceToJson()`, the
verifier's `docs/evidence-format.md`). Before a spec rests on it, this
step measures what that document is with the module's own fixtures and
trust settings: its size, whether it is ASCII and deterministic, what
`trust_lists` holds, what files without a manifest and broken files give,
and what writing it costs. Omeka is not involved; M5.2 measures viewing
and downloading.

## Setup (measured)

- `tools/probes/EvidenceProbe/measure.php` (never shipped) loads
  `vendor/autoload.php` and the module's `TrustChoice`, verifies each
  input with each of five trust settings, and prints one JSON line per
  pair. It writes each document to disk.
- Run on the host (PHP 8.5.12) and in the `omeka` container (PHP 8.3.36),
  both with verifier 0.10.0:
  `php tools/probes/EvidenceProbe/measure.php <out-dir>`.
- **The 13 inputs:**
  - the Pixel 10 photo, `c2pa-rs-ocsp.jpg`, the signed and unsigned
    JPEG, PNG and WebP fixtures, and `auth-1-1.gif`;
  - the altered JPEG made the way `VerifierCli::alteredJpeg()` makes it;
  - the first 64 KiB of the Pixel 10 photo;
  - an empty file;
  - `FF D8` followed by 164 bytes of noise.
- **The five settings:**
  - `none`;
  - `json`: the bundled lists as a settings document, as SPEC-004 builds
    them (`TrustChoice::bundledJson(false)`, then `fromJson()`);
  - `json+digicert`: the same with the DigiCert root;
  - `lists`: `TrustSettings::fromTrustLists(C2PA list, TSA list)`;
  - `lists+digicert`: the same, with the DigiCert root appended to the
    TSA list after a newline.
- Each pair is verified three times:
  - as the module does it, without a validation time (the clock);
  - twice with a fixed validation time, 2026-11-05T12:00:00Z;
  - once more by the clock, 1 s after the first.

## Measured

| what | result |
|---|---|
| exceptions | none: `verify()` returned a report for all 65 pairs, including the empty file, the noise, the truncated photo and the GIF |
| size, no manifest (unsigned fixtures, GIF, broken files) | 194 bytes with `none` or a settings document; 679 bytes with `fromTrustLists()` |
| size, signed fixtures (JPEG, PNG, WebP) | 2 272 bytes; 2 757 with `fromTrustLists()`. Altered JPEG: 2 274 / 2 759 |
| size, Pixel 10 photo (5.6 MB) | 7 866 bytes untrusted (`none`); 8 412 `Trusted` (`json`); 9 446 `Trusted` (`lists`) |
| size, `c2pa-rs-ocsp.jpg` (two manifests, 4 certificates, 2 time-stamp tokens, 2 OCSP responses) | 33 885 `Invalid` (`json`); 34 433 `Valid` (`json+digicert`); 34 370 / 35 462 with `fromTrustLists()` |
| ASCII | all 65 documents hold only `\n` and printable ASCII. None ends in a newline: the last byte is `}` |
| the same file, settings and fixed time, twice | the same bytes in 65 of 65 |
| PHP 8.5 against PHP 8.3 | the 41 documents that do not depend on the clock are byte-identical (`sha256` compared); none differs |
| the clock, 1 s apart | 24 of 65 documents differ: those with a manifest judged at `time_source: "clock"` (the signed and altered fixtures with any settings; the Pixel 10 photo without settings; `c2pa-rs-ocsp.jpg` without DigiCert). The only lines that differ are `judged_at`: 1 of 22 for `fixture-signed.jpg`, 2 of 90 for `c2pa-rs-ocsp.jpg`. With a fixed time the source becomes `"given"` |
| documents that do not depend on the clock | no manifest, or every manifest judged at a trusted time-stamp (`time_source: "timestamp"`): the Pixel 10 photo with trust lists, `c2pa-rs-ocsp.jpg` with DigiCert |
| `trust_lists` | `[]` with `none`, `json` and `json+digicert`. Two entries with `fromTrustLists()`: `manifest`/`pem` `b3949271…` and `tsa`/`pem` `7182cb22…`, the hashes of the files in `trust/` (`BundledLists::FILES`). With DigiCert appended, the TSA entry is `8a17132b…`, the hash of the joined text, which matches no published list |
| verdicts, settings document against `fromTrustLists()` | the same in 13 of 13 files (state, time source, signer anchor and TSA anchor per manifest), both with and without DigiCert |
| a file without a manifest | `"validation_state": "Invalid"`, `"active_manifest": null`, `"manifests": {}`. The module stores such a check as `none` (`Verdict::fromReport()`, `src/Check/Verdict.php:60`) |
| cost of `evidenceToJson()` | first call (PHP 8.5) 0.13 ms for the 34 KB document, 0.03 ms for the Pixel 10 one; the median of 20 calls at most 0.07 ms (PHP 8.3). `verify()` itself: 2–47 ms; building the settings: 26–35 ms, the same for both forms. Peak memory 12 MB |

## Read, not measured

- `EvidenceDocument` (verifier `src/Verifier/EvidenceDocument.php`)
  keeps nothing between calls: each call builds the document from the
  report again.
- `MediaCheck::check()` stores `error` in four places: the PHP version
  is too low, the verifier cannot be loaded, the original cannot be
  read, or `verify()` throws. In all four there is no report, so there is
  no document. No input here made `verify()` throw, so the last case was
  not reached.
- `docs/evidence-format.md`: within `version` 1 keys are only added.
  `verifier` names the release that wrote the document. A document
  stored as written therefore stays readable after a verifier update.
  Byte-identity after an update can only mean the stored bytes, since a
  later verifier may write a different document for the same file.

## What follows for M5.3 and M5.4 (reasoned)

- **There is a document for every check except `error`.** A spec can
  promise one for `Trusted`, `Valid`, `Invalid` and `none`, and none for
  `error`.
- **`none` and the document disagree.** For a file without a manifest
  the document says `Invalid`, while the module shows `none` (SPEC-002,
  after Tracefern). SPEC-006 has to choose:
  - store no document for `none`;
  - or store it, and show it only next to the module's verdict, never as
    the verdict.
- **Re-checks and the clock.** A file signed without a trusted
  time-stamp gives a new document at every check. The only difference is
  `judged_at`, which is the time of the check, and that is true
  information.
  - "Only one that differs from the last" would therefore still add a
    document for most `Valid` files at every run.
  - Comparing while ignoring `judged_at` would be the module reading
    meaning into the format.
  - Passing a validation time would change the verdict's meaning.
  - This is the choice for Maurice in the M5 steps, now with numbers.
- **Size.** In step 20's seed mix, 20 media take about 75 KB of evidence
  with the `json` settings: 14 signed fixtures, 4 unsigned, one OCSP
  file and one Pixel 10 photo. That is about 3.8 KB a media item, so
  about 12 MB for one run over the 3 097 media of step 20 (reasoned from
  the sizes above, not measured in Omeka). With `fromTrustLists()` each
  document grows by 485 bytes. A MariaDB `LONGTEXT` holds every size
  seen here; M5.2 measures it.
- **Cost is no reason to leave a document out.** At most 0.13 ms
  against 2–47 ms for `verify()`. Writing the row is M5.2's measurement.
- **`fromTrustLists()` would name the lists without changing any verdict
  here.** Without DigiCert, the two hashes are exactly the published
  lists. With DigiCert joined to the TSA list, the document names a list
  nobody published. The module already knows which lists it used (the
  `trust` column, `BundledLists::FILES`).
  - Keeping `fromJson()` and recording the lists next to the document
    keeps SPEC-004 as it is.
  - Moving to `fromTrustLists()` is a SPEC-004 amendment and gives
    DigiCert users a hash nobody can look up.
  - This goes to ADR-0005.
