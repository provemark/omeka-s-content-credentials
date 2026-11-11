# Step 66 (M8.8): registration on omeka.org

2026-11-11. Maurice decided to register the module on omeka.org without waiting for a pilot
installation, and submitted the registration form himself. The assistant collected the
requirements and checked the published release against them before he sent the form.

## What omeka.org asks

Read on 2026-11-10 from
[Register an addon](https://omeka.org/s/docs/developer/register_an_addon/) and the form embedded
in [omeka.org/register](https://omeka.org/register/), a Google Form:

- A one-time registration of the GitHub repository, with four fields: the repository URL, the
  addon type, the addon directory name and a contact email. Questions go to addons@omeka.org.
- After that, omeka.org finds and publishes every new release by itself. "There will be a short
  time" between the first registration and the module appearing.
- Automated checks: a release that is not a draft, with a `.zip` asset that really is a zip, one
  top-level directory with the registered name, an INI file at `/config/module.ini` that parses
  with an `[info]` section and a `version`, and a README named in capitals.
- A release later set to draft or pre-release is removed from omeka.org.
- omeka.org does not check whether an addon works.

## Measured against release 0.1.3

Asset `ContentCredentials-0.1.3.zip`, downloaded with `gh release download v0.1.3`:

| Check | Result |
|---|---|
| Release not a draft, not a pre-release | `"draft":false,"pre":false` (`gh release view v0.1.3`) |
| Asset is a zip | `file`: "Zip archive data"; SHA-256 `e7743e85…`, as in the release text |
| One top-level directory | `unzip -Z1 \| cut -d/ -f1 \| sort -u` gives only `ContentCredentials` |
| `/config/module.ini` | present; `parse_ini_string(…, true)` gives `[info]` with version `0.1.3` |
| README in capitals | `ContentCredentials/README.md` |
| Name free | `https://omeka.org/s/modules/ContentCredentials` answered 404 |

Nothing needed to change.

## Submitted

The form was filled in as follows:

- the repository: `https://github.com/provemark/omeka-s-content-credentials`;
- the type: Omeka S module;
- the directory: `ContentCredentials`;
- the contact email: Maurice's own, which is not recorded here.

He submitted it on 2026-11-11. That the module is listed on omeka.org was not yet measured. It
will be, once omeka.org has processed the form.

## What changes from now on

Every release that is not a draft now reaches omeka.org without a further step. The release routine
already tags only after `composer check` and CI are green ([`docs/maintenance.md`](../docs/maintenance.md));
a release found broken afterwards can be taken off omeka.org by setting it to draft.
