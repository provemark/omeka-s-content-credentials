#!/bin/sh
# Unpacks the pinned Omeka S release into build/omeka-s/ for static analysis
# (ADR-0002, M0.7). The version and SHA-256 are read from
# docker/omeka/Dockerfile, so the integration image and PHPStan see the same
# release. Does nothing when the unpacked copy is already that version.
set -eu
root=$(cd "$(dirname "$0")/.." && pwd)
dockerfile="$root/docker/omeka/Dockerfile"

version=$(sed -n 's/^ARG OMEKA_VERSION=//p' "$dockerfile")
sha256=$(sed -n 's/^ARG OMEKA_SHA256=//p' "$dockerfile")
if [ -z "$version" ] || [ -z "$sha256" ]; then
    echo "Error: no OMEKA_VERSION or OMEKA_SHA256 in $dockerfile." >&2
    exit 1
fi

out="$root/build"
target="$out/omeka-s"
stamp="$target/.omeka-version"
if [ -f "$stamp" ] && [ "$(cat "$stamp")" = "$version $sha256" ]; then
    exit 0
fi

zip="$out/.tools/omeka-s-$version.zip"
mkdir -p "$out/.tools"
if [ ! -f "$zip" ]; then
    curl -sSfL -o "$zip.part" "https://github.com/omeka/omeka-s/releases/download/v$version/omeka-s-$version.zip"
    mv "$zip.part" "$zip"
fi
actual=$(php -r 'echo hash_file("sha256", $argv[1]);' "$zip")
if [ "$actual" != "$sha256" ]; then
    echo "Error: $zip has SHA-256 $actual, expected $sha256. Delete it to download it again." >&2
    exit 1
fi

unpack="$out/.tools/unpack"
rm -rf "$unpack" "$target"
mkdir -p "$unpack"
unzip -q "$zip" -d "$unpack"
mv "$unpack/omeka-s" "$target"
rmdir "$unpack"
echo "$version $sha256" > "$stamp"
echo "Omeka S $version unpacked in build/omeka-s/"
