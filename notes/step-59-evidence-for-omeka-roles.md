# Step 59 (M7.29): the evidence pages for Omeka's own roles only

2026-11-08. Finding L1 of the security review (step 58). Maurice: "ok, begin met 1 tot en met 3";
SPEC-006 amendment 1 was part of that proposal.

## What was measured

In the test environment (Omeka S 4.2.1, PHP 8.3) with the module installed, a PHP script inside the
container bootstrapped Omeka (`Omeka\Mvc\Application::init`), so that the module's `onBootstrap()`
had run, added a role `guest` to `Omeka\Acl`, as the Guest module does, and asked
`isAllowed(<role>, 'ContentCredentials\Omeka\EvidenceController', <action>)`:

- before: `true` for `guest` and for each of Omeka's six roles;
- after: `false` for `guest`, `true` for the six.

The Guest module itself was not installed; the role is added the way its code is described, not
measured with it.

## What was built

- SPEC-006 amendment 1.
- **Test first:** `tests/Integration/EvidenceTest.php` "AC10: a role another module adds is not
  allowed the evidence controller", which runs that script. Red before the change: only `guest`
  differed (`true` for browse, show and download).
- **The change:** `Module::onBootstrap()` allows the controller for `Acl::ROLE_RESEARCHER` up to
  `Acl::ROLE_GLOBAL_ADMIN` instead of `null` (every role).
- Green after, with the existing AC10 test "access follows the media item" (anonymous and the six
  roles against a public and a private media item) unchanged and green.

`composer check`: green, 349 unit, 146 integration and 23 release tests.

## Limits

- A module that adds a role meant for the admin interface (none known) would now need its own
  allow rule for these pages. That is the safe default.
