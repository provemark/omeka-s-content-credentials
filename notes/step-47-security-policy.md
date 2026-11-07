# Step 47 (M7.17): a security policy

2026-11-07. The second change of step 46, approved by Maurice ("ok, doe punt 3 en 4").

`SECURITY.md` says what counts as a vulnerability in this module, what does not, how to report one,
and which versions get fixes. It follows `provemark/c2pa-verifier`'s `SECURITY.md` (read with
`gh api`): the same acknowledgement within a week, the same fix rule (spec amendment, red test,
change), credit in the step note, no bug bounty. A wrong verdict of the verifier itself is sent
there; this module only shows that verdict.

It names GitHub's private vulnerability reporting. That is **off** on this repository and on the
three sister repositories (measured: `gh api repos/provemark/<repo>/private-vulnerability-reporting`
gives `false` for each). Turning it on is a repository setting, left to Maurice with going public;
(Turned on on 2026-11-07, when the repository went public: see `NOTES.md`, decision 6.) Until then the policy also pointed to the contact on the repository's profile, as the verifier's
does.

`SECURITY.md` is not in the release zip: the zip's files are fixed by SPEC-008, and GitHub shows the
policy from the repository. The README links to it with an absolute address, like its other links,
because the README is in the zip.

## Read, not measured

- That the claims under "What counts" hold: each names something the specs already require
  (SPEC-002 the verdict as the verifier gives it, SPEC-003 escaping, SPEC-002 the upload always goes
  ahead, SPEC-004 the lists' SHA-256), and those are tested there; this step adds no test.
