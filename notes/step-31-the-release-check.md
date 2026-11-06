# Step 31 (M7.6): the release check

2026-11-06. What stands between the zip of step 30 and a first release. This step reads and
measures, and changes nothing in the module. Publishing, the version and the registration are
Maurice's decisions. This note gives him what they rest on.

## The bundled lists against upstream (measured)

`tools/trust/compare.sh` (`gh`, 2026-11-06): both C2PA lists **differ** from the newest published
ones. `tools/probes/ReleaseCheck/lists.php`, by certificate fingerprint:

| list | bundled | upstream | added | removed |
|---|---|---|---|---|
| `C2PA-TRUST-LIST.pem` | 32 | 33 | Samsung C2PA Root CA G1, valid to 2046-10-26 | none |
| `C2PA-TSA-TRUST-LIST.pem` | 23 | 24 | the same certificate | none |

- The `.pem` files last changed in `64b3f9c` (2026-11-04T17:09:28Z), by the commit history of both
  paths. The newest commit touching `trust-list/`, `a95f2c4`, changed only the `.json` files and
  the log. So the new list date would be 2026-11-04, and the commit `64b3f9c`.
- `tools/probes/ReleaseCheck/verdicts.php`: all 10 fixtures keep their verdict with the upstream
  lists (56 certificates against 58 in the settings, so the lists were in use). None of them is
  signed under that root, so this shows nothing was broken, not that a Samsung file becomes `Trusted`.
- The first run of that comparison said `settings changed: NO`: its replacement missed, and it had
  compared the bundled lists with themselves. The script's own check showed it; the numbers above are
  from the second run.

Refreshing is ADR-0004's procedure, but SPEC-004 and SPEC-005 write the date, the commit and the
hashes into their criteria (`c2pa-2026-10-17`, `47009ee`, `b3949271…`), so it takes an amendment.

## What omeka.org asks (read)

Read through a summarising fetch on 2026-11-06, so not verbatim throughout:
`omeka.readthedocs.io/en/latest/registerAnAddon.html` (written for Omeka Classic; it names
`plugin.ini` and `theme.ini`) and `omeka.org/s/docs/developer/modules/`. The form is at
`omeka.org/register/` (HTTP 200), not opened further.

| requirement | this module |
|---|---|
| a GitHub repository registered once; later releases are picked up by themselves | the repository is private (`gh`); that a private one cannot be registered is reasoned |
| a release that is not a draft or prerelease, with a tag; the tag need not be the version | none yet |
| an attached `.zip`, valid, with one top-level directory named as registered | `ContentCredentials/` only (SPEC-008 AC3) |
| the zip's name "can be anything", the directory's name recommended | `ContentCredentials-<version>.zip`; allowed, not what is recommended |
| the ZIP built by `git archive` | the procedure's example; ours is built by `tools/build.sh`. The check as listed looks at the asset, not how it was made (read) |
| an INI file that parses, with `[info]` and `version` | `config/module.ini`, for Omeka S (its developer page) |
| `README` in capitals, published with the version, "ready for publication" | `README.md` is in the zip, **not ready** (below) |
| no licence requirement stated | GPL-3.0-or-later, with the verifier's MIT and the lists' CC BY in the zip |
| working code is the author's task; Omeka does not test it | CI |

`module_link` and `author_link` are optional; the modules page shows them (`browse.phtml`, read).
The module has neither, and has nothing to link to while the repository is private.

Name (measured 2026-11-06): the page `omeka.org/s/modules/` (165 962 bytes, `curl`) has no "C2PA",
"content credentials" or "ContentCredentials"; `gh search repos` finds nothing for
"ContentCredentials omeka" or "c2pa omeka". Omeka S's newest release is still v4.2.1.

## What is not ready (measured)

- **The README in the zip.** Its status says "In development; nothing has been released", its
  requirements "(planned)", and three of its relative links lead to files the zip does not hold:
  `AI-LOG.md`, `NOTES.md`, `docs/milestones.md`. `LICENSE` and `trust/README.md` do work.
- **The changelog** has only "Unreleased".
- **The version** is 0.1.0 by rule, until now.
- **`upgrade()` does not exist.** Until now a schema change meant a reinstall. After a first
  release that would delete an archive's evidence, so the first change to the tables after it
  needs an `upgrade()` and its own spec (reasoned).
- **The zip on PHP 8.4 and 8.5** was never run, while the README says "PHP 8.3 or later". Unit tests
  and static checks do run on them in CI.

## Before the repository goes public (measured)

- Authors and committers: all 50 commits are the GitHub `noreply` address.
- History searched (`git log --all -p`): no private key header and no private address. No file named
  `*.key`, `.env` or `CLAUDE*` was ever tracked. This is a search on patterns, not a secret scanner.
- `/Users/maurice/…` is in four probe files (`tools/probes/JobProbe`, `TrustSettings`) and on 9 lines
  of the history: a user name and a local folder, not a secret. The paths also make those
  probes run nowhere else.
- `CLAUDE.md` is named in `.gitignore`, and the pattern `CLAUDE` in SPEC-008 AC3, its test and step 28.
- `tools/probes/` (49 files of 244) goes public with the rest, as do the notes and `AI-LOG.md`, which
  is the project's way of working.
- The test passwords in the compose files are for local containers only and say so.

## Not done

- The zip looked at in a browser, by the assistant. Before a release, Maurice's look is the one thing
  no test replaces.
- The registration form itself, and what omeka.org does with a repository that is renamed later,
  which matters given the possible renaming of ProveMark (`NOTES.md`, decision 1).
