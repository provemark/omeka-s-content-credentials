# ADR-0004: Bundling the trust lists

| Field    | Value            |
|----------|------------------|
| Status   | accepted         |
| Date     | 2026-11-04       |
| Decided  | Maurice van Loon |

## Context

Decision 4 (`NOTES.md`, 2026-11-03) chose to work as Tracefern does:
- bundle the C2PA trust lists;
- put the DigiCert Trusted Root G4 behind its own option, on by default;
- let custom settings replace everything;
- warn when the copy is old;
- never fetch.

Three files from outside this project would ship with the module. They
are data, not code, but the project rule is that nothing from outside
enters without an ADR. This ADR records what is bundled, from where,
under which licence, and how a copy is checked and refreshed. It rests on
`notes/step-16-trust-lists.md` (2026-11-04):

- Tracefern's copy of the two C2PA lists is byte-identical to
  `c2pa-org/conformance-public` at commit `47009ee` (2026-10-17). It is
  still identical at the newest commit that touched `trust-list/`,
  `d6dec63` (2026-10-26), which changed other files there.
- The DigiCert root's SHA-256 fingerprint is the one the verifier
  publishes in `docs/trust-settings.md`, and the root is valid until
  2038-01-15.
- With the two lists, the Pixel 10 photo is `Trusted`. With the DigiCert
  root as well, three files whose signers have expired are `Valid`
  instead of `Invalid`.
- Building the settings from the three files costs about 15 ms per
  upload in Omeka.

The verifier itself bundles no trust list on purpose: a list copied into
a package goes stale without anyone noticing (`docs/trust-settings.md`
there). Bundling here is the module's choice, and so is keeping the copy
honest.

## Decision

**What is bundled.** A directory `trust/` in the module holds:

| file | source | licence | trust kind |
|---|---|---|---|
| `C2PA-TRUST-LIST.pem` | `c2pa-org/conformance-public`, `trust-list/`, commit `47009ee` | CC BY 4.0, © C2PA | `manifest` |
| `C2PA-TSA-TRUST-LIST.pem` | the same | CC BY 4.0, © C2PA | `tsa` |
| `DigiCertTrustedRootG4.crt.pem` | `https://cacerts.digicert.com/DigiCertTrustedRootG4.crt.pem`, as copied by Tracefern | a public certificate, published for anyone to install | `tsa`, only when the DigiCert option is on |

All three are copied unchanged from Tracefern's `trust/`, which matches
upstream (step 16).

**Attribution.** `trust/README.md` names each file's source, commit,
licence and SHA-256, and gives the CC BY 4.0 notice and the DigiCert
fingerprint. The module's README points to it. The files are unchanged,
as CC BY 4.0 allows and asks to be stated.

**Recorded in code, checked by a test.** A class in the module
(`ContentCredentials\Trust\BundledLists`, the name to be fixed in the
spec) holds, for each file:
- the file name and its SHA-256;
- the upstream commit and its date;
- the date of the last comparison with upstream.

A unit test checks that the files in `trust/` have exactly those hashes,
so a changed file without a changed record fails `composer check`.
Nothing is checked at run time: the files arrive with the module.

**Two dates, one warning.** The settings page shows:
- the date of the upstream commit that last changed the lists
  (2026-10-17, `47009ee`);
- the date the copy was last compared with upstream (2026-11-04).

The age warning counts from the **comparison date**. Only a release can
bring a new copy, and what an administrator needs to know is how long
ago someone last confirmed the copy was current, not how long the C2PA
has gone without changing it. The threshold is 183 days (about six
months), as in Tracefern.

**Refreshing, by a maintainer, before a release.** A development script,
`tools/trust/compare.sh`, fetches the two files at the newest upstream
commit touching `trust-list/` and compares their hashes with the bundled
ones. It is never shipped, and the module never runs it.
- If the hashes are equal, only the comparison date changes.
- If they differ, the files, hashes, commit and both dates change
  together, in one commit with a note on what changed.

Either way the verdict tests of M3 run again. A refreshed list can turn a
fixture's verdict, so a changed verdict is a finding to record, not a
test to adjust silently.

**The DigiCert root** is refreshed only when DigiCert replaces it. It
expires on 2038-01-15. Its fingerprint is checked by the same unit test.

## Consequences

- `Trusted` is reachable by default, without the administrator pasting
  anything, on files whose signers chain to a C2PA anchor.
- The module carries about 70 KB of third-party data under CC BY 4.0,
  with its notice in `trust/README.md`. The module's own GPL-3.0-or-later
  licence does not extend to these files.
- A copy older than six months since its last comparison warns the
  administrator. A maintainer who releases without comparing ships an
  older comparison date, and the warning comes sooner: the cost of not
  checking shows up where it belongs.
- Uploads are checked against the copy bundled at the time. A verdict
  stored before a refresh is not updated by it. Re-checking is M4, and
  the trust source recorded with each verdict (M3's spec) tells which
  copy decided it.
- Custom settings replace the bundled lists entirely (decision 4), so
  an institution with its own policy is not bound by this copy.

## Alternatives considered

- **Bundle nothing; the administrator pastes settings.** Faithful to the
  verifier's stance, but then no installation reaches `Trusted` until
  someone builds a settings file by hand. Rejected in decision 4.
- **Fetch the lists at run time.** Always current, but it breaks the
  module's rule of no network. It would also make a verdict depend on
  what a server answered that day. Rejected.
- **A Composer package of trust lists.** None exists. Publishing one
  would add a release chain for 70 KB of data, and the verifier
  deliberately does not ship lists. Rejected.
- **Copy the files into the database at install.** The copy would then
  not change with a module update, and the record of what decided a
  verdict would live in two places. Rejected.
- **Count the warning from the upstream commit date.** That would warn
  about a copy that is still current whenever the C2PA leaves the lists
  unchanged for six months. Rejected in favour of the comparison date;
  both dates are shown.

## Refreshes

The decision above stands; its values are those of the first copy. The
current ones are in `trust/README.md` and `BundledLists`.

- 2026-11-06: `47009ee` to `64b3f9c`, one root added to both lists. Record:
  `notes/step-32-refreshing-the-lists.md`.
