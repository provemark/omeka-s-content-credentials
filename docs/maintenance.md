# Maintenance

What keeps the module correct while no feature is being built: the trust lists, the verifier,
Omeka S, PHP and the CI actions. Agreed 2026-11-08, after release 0.1.3. Maurice decides each
release; the assistant may run the checks and propose.

## The rhythm

| When | What | Done by |
|---|---|---|
| Monthly, when Dependabot opens a pull request | Review and merge updates of the pinned GitHub Actions, if CI is green on them | Maurice merges |
| Quarterly: the first week of February, May, August and November | The quarterly check below | assistant runs it, Maurice decides on a release |
| When it happens | A new Omeka S release, a new verifier release, a new PHP minor version, a security report | as below |

The trust-list comparison date is 2026-11-06. The configure page warns 183 days after it, on
2027-05-08 (`BundledLists::STALE_AFTER_DAYS`). The quarterly checks of February and May 2027 both
fall before that date, so a release with a new comparison date can always come before the warning.

## The quarterly check

1. **Trust lists.** Run `tools/trust/compare.sh`. It prints the newest upstream commit touching
   `trust-list/` in `c2pa-org/conformance-public`, and whether each bundled list is equal to it.
   - Equal: only the comparison date changes.
   - Different: the files, their SHA-256, the commit and both dates change together.

   Either way, follow "Refreshing" in [`trust/README.md`](../trust/README.md) and
   `src/Trust/BundledLists.php`; the unit tests check that the two agree with the files. Either way
   it needs a release, since the date ships in the zip.
2. **The verifier.** Look for a new release of `provemark/c2pa-verifier`
   (`gh release list -R provemark/c2pa-verifier`). The module requires `^0.11`. A new 0.11.x
   is a patch release of the module if `composer check` is green after `composer update
   provemark/c2pa-verifier`. A 0.12 or later is its own step: read its changelog for changes to
   verdicts, status codes and the evidence format (SPEC-006 stores `evidenceToJson()` byte for
   byte), and measure before deciding. Dependabot leaves Composer alone on purpose
   ([`.github/dependabot.yml`](../.github/dependabot.yml)).
3. **Omeka S.** Look for a new release (`gh api repos/omeka/omeka-s/releases --jq '.[0].tag_name'`).
   A new 4.x: change `OMEKA_VERSION` and `OMEKA_SHA256` in `docker/omeka/Dockerfile` and the version in
   the image names and job names (`compose.yaml`, `tools/env/compose.release.yaml`,
   `.github/workflows/ci.yml`), run `composer check`, and note what changed in a step note.
   `module.ini` allows `^4.2.1`, so a working 4.x needs no release; one that breaks the module
   does. A 5.0 is its own step.
4. **Development tools.** Run `composer audit`. An advisory in a development tool is updated as in
   step 60; one in the verifier or anything that ships is a security release.
5. **PHP.** The module promises PHP 8.3 and later, and CI runs 8.3, 8.4 and 8.5. When a new PHP
   minor version is released, add it to the CI matrix and to the pinned images in
   `tools/env/release.sh`, and to the README's requirements once it is green.
6. **Record.** One line in `docs/milestones.md` per check, with what was found, even when nothing
   changed.

## A security report

[`SECURITY.md`](../SECURITY.md) says what counts and how to report. A confirmed vulnerability gets
a fix with a test seen red, and a patch release without waiting for the quarter.

## A release

Each release so far follows the same routine (steps 49, 56, 62 and 65):

1. Commit the version in `config/module.ini` and the dated CHANGELOG section first:
   `tools/build.sh` builds from the commit.
2. Measure the upgrade from the previous published zip in a clean Omeka S: the stored verdicts and
   evidence byte-identical, the module's pages as expected, a new upload checked.
3. `tools/env/reset.sh`, then `composer check`. Without the reset the test database keeps the
   previous version, Omeka S waits for an upgrade and every integration test fails (step 65).
4. Push only after the check is read green; tag only after CI is green on the commit.
5. Rebuild the zip from the tagged commit, compare it unpacked with the measured one, and publish
   it with its SHA-256 in the release text.
6. Download it with `gh` and anonymously, compare the hash, and write the step note.

The module was registered on omeka.org on 2026-11-11 (step 66): every release that is not a draft
is published there automatically. Setting a release to draft or pre-release takes it off again.
