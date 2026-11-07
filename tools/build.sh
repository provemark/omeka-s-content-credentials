#!/bin/sh
# Builds build/ContentCredentials-<version>.zip, the release of this module, from
# the committed HEAD (ADR-0006, SPEC-008). Uncommitted files are not in a release.
#
# Usage: tools/build.sh [tree-ish]
# tools/env/release.sh gives a tree of the working tree, to test what is about to
# be committed.
#
# 1. git archive of what ships, named below, plus Composer's two files.
# 2. composer install --no-dev with the committed lock, in the build folder; the
#    verifier's command-line tool, which the module does not use, removed.
# 3. Strauss, pinned by version and SHA-256, moves the verifier under
#    ContentCredentials\Vendor\ into vendor-prefixed/ and rewrites the call sites
#    in the build's copy of src/.
# 4. Only what runs is kept: no Composer autoloader may load in Omeka S (step 11).
set -eu

ref=${1:-HEAD}
root=$(cd "$(dirname "$0")/.." && pwd)
out="$root/build"
work="$out/release"
dir="$work/ContentCredentials"

strauss_version=0.31.1
strauss_sha256=17dc2baf27b9a3f52834e3db729c8365d8915a80e37a087d253cf705387aa887
strauss="$out/.tools/strauss-$strauss_version.phar"

rm -f "$out"/ContentCredentials-*.zip
rm -rf "$work"
mkdir -p "$dir" "$out/.tools"

if [ ! -f "$strauss" ]; then
    curl -sSfL -o "$strauss.part" "https://github.com/BrianHenryIE/strauss/releases/download/$strauss_version/strauss.phar"
    mv "$strauss.part" "$strauss"
fi
actual=$(php -r 'echo hash_file("sha256", $argv[1]);' "$strauss")
if [ "$actual" != "$strauss_sha256" ]; then
    echo "Error: $strauss has SHA-256 $actual, expected $strauss_sha256. Delete it to download it again." >&2
    exit 1
fi

git -C "$root" archive "$ref" Module.php LICENSE README.md CHANGELOG.md composer.json composer.lock config src trust view | tar -x -C "$dir"
version=$(php -r '$i = parse_ini_file($argv[1], true); echo $i["info"]["version"];' "$dir/config/module.ini")

composer install --working-dir="$dir" --no-dev --optimize-autoloader --no-interaction --no-progress --quiet --no-scripts
rm -rf "$dir/vendor/provemark/c2pa-verifier/src/Cli"
composer dump-autoload --working-dir="$dir" --no-dev --optimize --no-interaction --quiet --no-scripts
# Strauss's output is shown only when it fails or prefixes nothing (SPEC-008 AC17).
verifier="$dir/vendor-prefixed/provemark/c2pa-verifier"
if ! strauss_output=$(cd "$dir" && php "$strauss" --no-interaction 2>&1) || [ ! -d "$verifier" ]; then
    echo "Error: Strauss did not prefix the verifier into $verifier. Its output:" >&2
    printf '%s\n' "$strauss_output" >&2
    rm -rf "$work"
    exit 1
fi
find "$verifier" -mindepth 1 -maxdepth 1 ! -name src ! -name LICENSE ! -name composer.json -exec rm -rf {} +
find "$dir/vendor-prefixed" -mindepth 1 -maxdepth 1 ! -name provemark -exec rm -rf {} +
rm -rf "$dir/vendor" "$dir/composer.json" "$dir/composer.lock"

(cd "$work" && zip -qr -X "$out/ContentCredentials-$version.zip" ContentCredentials)
rm -rf "$work"
echo "Built $out/ContentCredentials-$version.zip from $(git -C "$root" rev-parse --short "$ref")"
