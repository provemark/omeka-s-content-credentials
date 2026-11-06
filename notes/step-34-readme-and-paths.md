# Step 34 (M7.9, M7.10): the README, and the probes' paths

2026-11-06. Two of Maurice's decisions on step 31, each in its own commit.

## M7.9: the README and the changelog

omeka.org publishes the README with each version, so it now speaks to someone installing the module:
requirements, installing, what it does, what it does not do, deleting and uninstalling. The milestone
story moved out, as `CHANGELOG.md` and `docs/milestones.md` hold it. The version stays 0.1.0, and the
changelog's section says "not yet released"; a release gives it its date.

Measured with `unzip` and a shell loop over the README's links, on `tools/build.sh`'s zip:

| zip | relative links | missing |
|---|---|---|
| the old README (control) | 5 | `docs/milestones.md`, `NOTES.md`, `AI-LOG.md` |
| the new README | 3 | none |

This is a one-off check, not a test: no criterion asks for it. The four links to files that the zip
does not hold are now absolute, to `blob/main` of the repository, and those files exist there. **They
lead nowhere while the repository is private** (reasoned), which depends on Maurice's decision.

The states and labels were taken from `Presentation`, the requirements from `config/module.ini`
and `Requirements`, the formats from `AfterFlushListener::MEDIA_TYPES`, and "the public site and the
API show nothing" from SPEC-003 AC11. The README's sentences on what a state means are a summary
written for this step, and no one has yet read them in the zip but the assistant.

## M7.10: the probes' paths

Four probe files (`tools/probes/JobProbe/scripts/acl.php`, `measure.php`,
`tools/probes/TrustSettings/expiry.php`, `measure.php`) named a folder on one machine. They now
find the repository from `__DIR__`, and the sister repository `C2PA_Verifier_WP` as a folder next
to it, as their comments already said. Measured: `git grep /Users/maurice` now finds only step 31's
description, and each of the four files' paths resolves to the same folders as before (`php -r`,
`realpath`). The probes were not run again: they are records of earlier measurements, and their
results stand on the code of that day.

As Maurice decided, the history is not rewritten: the old paths stay on 9 lines in it. They name a
user name and a local folder, not a secret.

