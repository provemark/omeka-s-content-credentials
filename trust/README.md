# Bundled trust lists

These files are what Content Credentials (C2PA) for Omeka S trusts by
default (SPEC-004, ADR-0004). Nothing here is fetched at run time; a new
copy arrives only with a release of the module. An administrator can
replace all of it with custom trust settings on the module's configure
page.

| file | source | SHA-256 | what it anchors |
|---|---|---|---|
| `C2PA-TRUST-LIST.pem` | [`c2pa-org/conformance-public`](https://github.com/c2pa-org/conformance-public/tree/main/trust-list), `trust-list/`, commit `47009ee` (2026-10-17) | `b3949271a07b007e92ef1d18050b55388f5e0d2686a16fd864176c48421af8ae` | the certificate authorities the C2PA conformance programme accepts for signers (trust kind `manifest`); 32 certificates |
| `C2PA-TSA-TRUST-LIST.pem` | the same directory and commit | `7182cb227c02701be0eebd67ab635fca7f85431c32796fb53b267c657cd2c675` | the timestamp authorities the programme accepts (trust kind `tsa`); 23 certificates |
| `DigiCertTrustedRootG4.crt.pem` | [DigiCert](https://cacerts.digicert.com/DigiCertTrustedRootG4.crt.pem) | `ce7d6b44f5d510391be98c8d76b18709400a30cd87659bfebe1c6f97ff5181ee` | timestamp authorities (trust kind `tsa`), used only when the DigiCert option is on (the default) |

The list date is 2026-10-17, the date of commit `47009ee`, which last
changed the two lists. The copy was last compared with the published lists
on 2026-11-04 (identical at `d6dec63`, 2026-10-26, the newest commit
touching `trust-list/` that day). The module warns on its configure page
183 days after that comparison.

## Licence

The two C2PA lists are © the Coalition for Content Provenance and
Authenticity (C2PA), licensed under
[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/), and included
unchanged. The module's own licence (GPL-3.0-or-later) does not extend to
them.

The DigiCert root is a public certificate that DigiCert publishes for
anyone to install. Its SHA-256 certificate fingerprint is
`55:2F:7B:DC:F1:A7:AF:9E:6C:E6:72:01:7F:4F:12:AB:F7:72:40:C7:8E:76:1A:C2:03:D1:D9:D2:0A:C8:99:88`;
it is valid until 2038-01-15. It is not on the C2PA's TSA list. With it, a
file whose signing certificate has since expired verifies when a DigiCert
timestamp authority vouches for the signing time; without it, such a file
is `Invalid`. See the verifier's `docs/trust-settings.md`.

## Refreshing

Before a release, a maintainer runs `tools/trust/compare.sh` (development
only, never shipped). It compares these files with the newest published
lists. Then either only the comparison date changes, or the files, their
hashes, the commit and both dates change together, here and in
`src/Trust/BundledLists.php`. A unit test checks that the files have the
recorded hashes.
