#!/bin/sh
# Compares the bundled C2PA trust lists in trust/ with the newest published
# copy in c2pa-org/conformance-public (ADR-0004, SPEC-004). Development only:
# never shipped, never run by the module. Needs the GitHub CLI (gh).
#
# Usage: tools/trust/compare.sh
# Prints the newest upstream commit touching trust-list/, and for each list
# whether it equals the bundled file. Changes nothing; refreshing is done by
# hand as trust/README.md describes.
set -eu
cd "$(dirname "$0")/../.."
repo=c2pa-org/conformance-public
commit=$(gh api "repos/$repo/commits?path=trust-list&per_page=1" -q '.[0].sha')
date=$(gh api "repos/$repo/commits/$commit" -q '.commit.committer.date')
echo "newest upstream commit touching trust-list/: $(echo "$commit" | cut -c1-7) ($date)"
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
status=0
for file in C2PA-TRUST-LIST.pem C2PA-TSA-TRUST-LIST.pem; do
    gh api -H "Accept: application/vnd.github.raw" "repos/$repo/contents/trust-list/$file?ref=$commit" > "$tmp/$file"
    upstream=$(shasum -a 256 "$tmp/$file" | cut -d' ' -f1)
    bundled=$(shasum -a 256 "trust/$file" | cut -d' ' -f1)
    if [ "$upstream" = "$bundled" ]; then
        echo "$file: equal ($bundled)"
    else
        echo "$file: DIFFERS (bundled $bundled, upstream $upstream)"
        status=1
    fi
done
exit $status
