# Content Credentials (C2PA) for Omeka S

An Omeka S module that verifies the Content Credentials (C2PA) of every
uploaded media file, stores the verdict with the media, and shows it in
the admin interface. It only checks: it never signs, never blocks an
upload and opens no network connection. Verification is done by
[`provemark/c2pa-verifier`](https://github.com/provemark/c2pa-verifier),
in pure PHP.

Later it will also keep the evidence behind each verdict, the
certificates, time-stamps and trust lists it rested on, so that what held
when a file came in stays on record after a certificate has expired.

## Status

In development; nothing has been released. Milestone M1 is done: every
new JPEG, PNG and WebP media item is verified once, after it is saved,
and the verdict is stored with it (SPEC-002). Nothing shows it yet; that
is M2. The plan is in [`docs/milestones.md`](docs/milestones.md),
the decisions are in [`NOTES.md`](NOTES.md).

## Requirements (planned)

- Omeka S 4.2.x
- PHP 8.3 or later (the verifier's floor; Omeka S itself runs from 8.1)

## How this is built

This module is built with Claude Code; [`AI-LOG.md`](AI-LOG.md) records
what the assistant produced and what was decided by the maintainer.

## Licence

GPL-3.0-or-later, see [`LICENSE`](LICENSE). The bundled verifier is MIT.
