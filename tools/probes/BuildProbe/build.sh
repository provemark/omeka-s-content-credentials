#!/bin/sh
# M7.1 probe, never shipped: builds a candidate release folder and zip from the
# committed HEAD under build/probe/, the way Tracefern's tools/build.sh does.
#
# Usage: tools/probes/BuildProbe/build.sh <strauss-version> <label>
# Leaves build/probe/<label>/ContentCredentials and build/probe/<label>.zip.
set -eu
version=$1
label=$2
root=$(cd "$(dirname "$0")/../../.." && pwd)
out="$root/build/probe"
dir="$out/$label/ContentCredentials"
strauss="$root/build/.tools/strauss-$version.phar"
case "$version" in
    0.30.0) sha=08c1a8e553594745c22294e158129005fd11ed09ed452d7d4f48566f38c66c96 ;;
    *) sha= ;;
esac
if [ ! -f "$strauss" ]; then
    curl -sSfL -o "$strauss.part" "https://github.com/BrianHenryIE/strauss/releases/download/$version/strauss.phar"
    mv "$strauss.part" "$strauss"
fi
actual=$(php -r 'echo hash_file("sha256", $argv[1]);' "$strauss")
echo "strauss $version sha256 $actual"
if [ -n "$sha" ] && [ "$actual" != "$sha" ]; then
    echo "Error: unexpected hash" >&2
    exit 1
fi

rm -rf "$out/$label" "$out/$label.zip"
mkdir -p "$dir"
# What ships, named one by one; a real build would use export-ignore.
git -C "$root" archive HEAD Module.php LICENSE README.md CHANGELOG.md composer.json composer.lock config src trust view | tar -x -C "$dir"

php -r '
$file = $argv[1];
$c = json_decode(file_get_contents($file), true);
$c["extra"]["strauss"] = [
    "target_directory" => "vendor-prefixed",
    "namespace_prefix" => "ContentCredentials\\Vendor\\",
    "packages" => ["provemark/c2pa-verifier"],
    "update_call_sites" => ["src"],
    "delete_vendor_packages" => true,
];
file_put_contents($file, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
' "$dir/composer.json"

composer install --working-dir="$dir" --no-dev --optimize-autoloader --no-interaction --no-progress --quiet --no-scripts 2>&1 | grep -v 'lock file is not up to date' || true
rm -rf "$dir/vendor/provemark/c2pa-verifier/src/Cli"
composer dump-autoload --working-dir="$dir" --no-dev --optimize --no-interaction --quiet --no-scripts
(cd "$dir" && php "$strauss" --no-interaction 2>&1 | tail -5)

# The module never loads a Composer autoloader (step 11): only the classes stay.
verifier="$dir/vendor-prefixed/provemark/c2pa-verifier"
find "$verifier" -mindepth 1 -maxdepth 1 ! -name src ! -name LICENSE ! -name composer.json -exec rm -rf {} +
find "$dir/vendor-prefixed" -mindepth 1 -maxdepth 1 ! -name provemark -exec rm -rf {} +
rm -rf "$dir/vendor" "$dir/composer.lock" "$dir/composer.json"

# Strauss rewrites namespaces, not paths (step 11).
find "$dir/src" -name '*.php' -exec sed -i '' 's#/vendor/provemark/c2pa-verifier/src#/vendor-prefixed/provemark/c2pa-verifier/src#' {} +

(cd "$out/$label" && zip -qr -X "../$label.zip" ContentCredentials)
echo "built $out/$label.zip from $(git -C "$root" rev-parse --short HEAD)"
