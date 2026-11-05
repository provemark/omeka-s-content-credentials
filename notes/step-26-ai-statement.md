# Step 26 (M6.1): where a file says it was made with AI

2026-11-05. M6 is meant to show "AI-generated (signed)" only on a manifest
that verifies. Tracefern's `specs/SPEC-003-ai-label.md`,
`specs/SPEC-027-ai-in-the-history.md` and `notes/m3-ai-label.md` measured
this with older verifiers. This step measures it again with verifier 0.10.0
and this module's trust settings, outside Omeka, before a spec rests on it.

## Setup (measured)

`tools/probes/AiProbe/` (never shipped):
- `lib.php` reads `VerificationReport::toArray()` and applies two rules:
  - **SPEC-003's rule:** the exact URI of `trainedAlgorithmicMedia` on an
    action in the active manifest;
  - **SPEC-027's rule:** walking `active_manifest` links in ingredients,
    following an ingredient only when its recorded failures and the
    verifier's delta are both present and hold at most
    `signingCredential.untrusted`. `trainedAlgorithmicMedia` reached through
    `parentOf` alone is "generated"; `compositeWithTrainedAlgorithmicMedia`,
    or `trainedAlgorithmicMedia` through another relationship, is "edited".

    This is one walk, a simplification: Tracefern's amendment 1 needs two,
    so that a manifest reached as a component first still counts as a
    parent. No file measured here depends on that.
- `measure.php <dir>` measures each file with no settings, the bundled lists
  (`c2pa`) and the bundled lists with DigiCert (`default`, the module's
  default): the verdict, the manifests and both rules. With `default` it also
  gives the verdict at seven dates to 2045, with a binary search for the day
  it changes, and the cost.
- `census.php <roots>` goes through every JPEG, PNG and WebP under the roots
  whose bytes contain `digitalsourcetype/`.

**Inputs:** 13 files copied to a scratch directory, nothing added to this
repository: this module's fixtures, Tracefern's OpenAI, Amazon Titan,
parent-chain and Lightroom files, and the verifier's
`ingredient/manifest-and-dst.png`, `databox/c2pasign-logo.png` and Bing file.
Four tampered copies were made, with the PNG CRC recomputed: one bit of image
data flipped, and one letter of the statement changed, each for OpenAI and
parent-chain.

**Run:** on the host (PHP 8.5.12) and in the `omeka` container (PHP 8.3.36),
verifier 0.10.0. All 17 files gave the same results on both, timings aside.

The census ran on the host over the verifier's `tests/Fixtures/` (at
`v0.10.0-1-g5d96cff`) and Tracefern's `tests/Fixtures/`: 570 files, with the
copies present in both trees counted twice.

## Measured

| file | none / c2pa / default | source types | SPEC-003 / SPEC-027 (default) | default verdict changes |
|---|---|---|---|---|
| OpenAI (2.1 MB, MIT) | Valid / Trusted / Trusted | `c2pa.created` = trained | yes / generated | **2027-04-23**, to Invalid |
| Amazon Titan (1.8 MB, Apache-2.0) | Invalid / Invalid / Valid | `c2pa.created` = trained | yes / generated | no change to 2045 |
| parent-chain (60 KB, MIT) | Valid / Valid / Valid | active: algorithmicMedia; `parentOf`: trained | **no** / generated | **2036-09-25**, to Invalid |
| `c2pa-rs-ocsp.jpg` (in this repository) | Invalid / Invalid / Valid | active: composite; `componentOf`: trained | no / edited | no change to 2045 |
| `c2pasign-logo.png` (the verifier's) | Valid / Valid / Valid | `parentOf` twice, then trained; no recorded results | no / **no** | 2030-08-26, to Invalid |
| `fixture-signed.jpg`, `.png`, `.webp` | Valid / Valid / Valid | `c2pa.created` = **algorithmicMedia** | no / no | **2030-08-26**, to Invalid |
| Pixel 10 photo | Invalid / Trusted / Trusted | computationalCapture | no / no | no change to 2045 |
| Lightroom | Invalid / Invalid / Valid | none | no / no | no change to 2045 |
| `fixture-unsigned.jpg` | no manifest | none | no / no | — |
| Bing | Invalid (`claimSignature.missing`) in all three | none readable | no / no | no change to 2045 |
| `manifest-and-dst.png` | Invalid (`assertion.ingredient.malformed`) in all three | active: algorithmicMedia | no / no | no change to 2045 |

The tampered copies, with `default`:

| copy | verdict and codes | the rules |
|---|---|---|
| OpenAI, image bit flipped | Invalid: `assertion.dataHash.mismatch` | **yes / generated**: the statement is still read |
| parent-chain, image bit flipped | Invalid: `assertion.dataHash.mismatch` | no / **generated** |
| OpenAI, statement changed | Invalid: `assertion.hashedURI.mismatch`, `assertion.action.malformed` | no / no: `trainedXlgorithmicMedia` matches nothing |
| parent-chain, statement changed | Invalid: those two and `ingredient.manifest.mismatch`; the delta holds them, so the gate closes | no / no |

The census, with `default`:

| what | result |
|---|---|
| verdicts | 238 Valid, 4 Trusted, 328 Invalid; no exception |
| source types in toArray | `algorithmicMedia` 323, `digitalCapture` 179 (and once with a leading space, in the active manifest), `trainedAlgorithmicMedia` 15 (4 active, 11 in ingredients), `compositeWithTrainedAlgorithmicMedia` 4, `computationalCapture` 2 |
| a source type that is not a string | none |
| Valid or Trusted with SPEC-003's rule | 4 (OpenAI and Titan, in both trees) |
| Valid or Trusted with SPEC-027's rule but not SPEC-003's | 2 "generated" (parent-chain), 2 "edited" (ocsp) |
| the bytes hold the words, Valid or Trusted, and neither rule answers | 1: `c2pasign-logo.png`, its only such file. The other 7 of this kind are Invalid |

**Cost:** `toArray()` takes at most 0.04 ms on the host and 0.02 ms in the
container; `verify()` takes 0–18 ms on the host.

**Read, not measured:**
- `Verdict::fromReport()` already calls `toArray()` for `Invalid` only, so
  this adds a call for the other states.
- Tracefern's amendment 2: an ingredient's recorded results are what its
  signer wrote, not what the verifier found. Measured here too: parent-chain
  records `[]` while the delta holds `signingCredential.untrusted`.

## What follows for M6.2 (reasoned)

- **The verdict must gate the label.** A tampered image keeps its statement
  in `toArray()` under both rules. Showing it would be a claim on a file that
  does not verify.
- **The active manifest alone misses real shapes.** SPEC-003's rule misses
  parent-chain and ocsp, as Tracefern found. Which rule to use is Maurice's
  choice in M6.2.
- **SPEC-027's gate has a price.** `c2pasign-logo.png` is `Valid`, and its
  chain says trained AI two steps down, but its ingredients record no results,
  so the gate closes. The verifier's delta alone (only
  `signingCredential.untrusted`) would let it through. Failing closed gives
  no label; the file says more than the module shows. To put to Maurice.
- **`algorithmicMedia` is not the label.** All of this module's signed
  fixtures say it, so those must stay without a label. That IPTC defines it as
  media made without sampled training data is from memory, not checked here;
  M6.2 cites the vocabulary.
- **Fixtures that hold.** With the module's default settings:
  - Titan is `Valid` with the statement, to at least 2045, and `Invalid` with
    DigiCert off today, which gives both the positive and the negative;
  - `c2pa-rs-ocsp.jpg`, already here, carries "edited";
  - parent-chain holds until 2036-09-25;
  - OpenAI holds only until April 2027, so no test should rest on it. Nothing
    measured is `Trusted` with the statement and lasts, so a test can set
    `Trusted` on a row, as SPEC-003 AC1 does.

  No new fixture needs to be made. Titan adds 1.8 MB and a licence file.
- **Malformed statements** did not occur in 570 files; a non-string source type
  needs a unit test on an array, as Tracefern's has.

## Found on the way: this module's own tests have a date

`fixture-signed.jpg`, `.png` and `.webp` turn `Invalid` on 2030-08-26 under the
default settings. Measured by giving the verifier a validation time; for the
JPEG, without settings, the exact moment is in the correction below, and the
code is `signingCredential.expired`. The module checks by the clock, and 57
lines in 9 test files name `fixture-signed`. How many of them assert `Valid`
was not counted; those tests will fail then. Nothing is wrong today and nothing was changed; this is a date to deal
with before then, and it applies to the verifier's own tests as well if they
run by the clock (not checked).

## Added in M6.2

`tools/probes/AiProbe/deltas.php`, on the verifier's `tests/Fixtures/` (668
files, PHP 8.5.12, no settings): of 132 ingredients that name a manifest, in
every manifest of the store, 125 have a delta under the URI the gate builds,
also the second ingredient of a manifest (`c2pa.ingredient.v3__1`). The 7 others
include manifests not reached from the active one; which of them are reached was
not counted. An ingredient without a delta is not followed.

## Correction, the same day

Checking this note after its commit found three things:

- **The dates were up to a day late.** The first version's search stopped
  within a day, at its upper bound, and printed 2027-04-24, 2036-09-26 and
  2030-08-27. The table above now gives the day itself. A search to the second,
  without settings, on the host with PHP 8.5.12, gives the last second of
  `Valid`, followed by `signingCredential.expired` and
  `signingCredential.untrusted`:

  | file | last second `Valid` |
  |---|---|
  | OpenAI | 2027-04-23T15:51:04Z, as step 16 found |
  | parent-chain | 2036-09-25T18:03:05Z |
  | `fixture-signed.jpg` | 2030-08-26T18:46:40Z |

  `.png`, `.webp` and `c2pasign-logo.png` were not searched to the second.
- **"Ten lines" was the wrong figure.** That grep also counted other files'
  `Valid`. The section above now gives what was counted.
- **Two claims had only been read, and now hold when measured:**
  - `evidenceToJson()` holds neither `digitalSourceType` nor `"assertions"`, on
    `c2pa-rs-ocsp.jpg`, OpenAI and `fixture-signed.jpg`.
  - `toArray()` is not cached: `VerificationReport::toArray()` builds its array
    at each call, and a first call costs 0.012-0.048 ms (median of 7) against
    0.010-0.041 ms for a repeat. So the 0.04 ms above stands.
