# Security

This module shows an administrator what the Content Credentials (C2PA) of an uploaded file say. Its
one job is never to show a verdict the verifier did not give, and never to let the file's own text
do more than be read. This document says what counts as a vulnerability here and how to report one.

## What counts

**A vulnerability** is anything that lets a crafted file or a crafted request:

- make the module show `Valid`, `Trusted` or "AI-generated (signed)" where the bundled verifier,
  `vendor/bin/c2pa-verify` on the same bytes, would not;
- run script or inject markup in the admin interface through text read from a file (a signer's
  name, an issuer, a status code), or through a stored evidence document;
- stop an upload, or break the media page or the media browse;
- reach the network, a file outside the uploaded original, or the settings, without the rights
  Omeka asks for them;
- swap or change the bundled trust lists unnoticed.

**Not a vulnerability here:** a wrong verdict that the verifier gives on its own. Report that to
[`provemark/c2pa-verifier`](https://github.com/provemark/c2pa-verifier/blob/main/SECURITY.md); this
module shows its verdict as it is. Also not a vulnerability here:

- a format this module does not check (see the README);
- a `Valid` verdict on a file whose signer you do not trust: `Trusted` needs a trust list;
- a verdict that changes after a certificate expires: the stored evidence keeps what held when
  the file came in.

## Reporting

Please do not open a public issue. Use GitHub's private vulnerability reporting on this repository
(**Security → Report a vulnerability**), or the contact on the repository's profile. Send the file,
or a way to make one, and what you saw. You will get an acknowledgement within a week.

A fix follows the project's own rule: a spec amendment, a test that is red on the report, then the
change. The report is credited in the note of that step unless you ask otherwise. There is no bug
bounty.

## Supported versions

Before 1.0, only the latest `0.x` release gets fixes.
