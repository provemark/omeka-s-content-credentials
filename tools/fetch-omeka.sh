#!/bin/sh
# Downloads the pinned Omeka S release zip into build/.tools/ once, checks its
# SHA-256, and unpacks it into build/omeka-s/ for static analysis (ADR-0002,
# M0.7). The Docker images are built from a copy of the same zip in
# docker/omeka/, so the integration image and PHPStan see the same release.
# The version and SHA-256 are read from docker/omeka/Dockerfile. Unpacks
# nothing when the unpacked copy is already that version.
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

zip="$out/.tools/omeka-s-$version.zip"
mkdir -p "$out/.tools"
if [ ! -f "$zip" ]; then
    # --retry: a 5xx of GitHub's release downloads is retried for about five minutes; a 404 is not.
    # --http1.1: over HTTP/2, curl 8.7.1 reported GitHub's 504 as error 56 and did not retry it.
    # --connect-timeout, --speed-limit/--speed-time: a connection or a transfer that stalls ends as
    # error 28 and is retried, instead of hanging (step 52); a slow but moving download goes on.
    curl -sSfL --http1.1 --retry 10 --retry-delay 30 --connect-timeout 30 --speed-limit 1000 --speed-time 60 -o "$zip.part" "https://github.com/omeka/omeka-s/releases/download/v$version/omeka-s-$version.zip"
    mv "$zip.part" "$zip"
fi
actual=$(php -r 'echo hash_file("sha256", $argv[1]);' "$zip")
if [ "$actual" != "$sha256" ]; then
    echo "Error: $zip has SHA-256 $actual, expected $sha256. Delete it to download it again." >&2
    exit 1
fi

# The copy the Docker images are built from (docker/omeka/Dockerfile).
context="$root/docker/omeka"
if ! cmp -s "$zip" "$context/omeka-s-$version.zip"; then
    rm -f "$context"/omeka-s-*.zip
    cp "$zip" "$context/omeka-s-$version.zip"
fi

if [ -f "$stamp" ] && [ "$(cat "$stamp")" = "$version $sha256" ]; then
    exit 0
fi

unpack="$out/.tools/unpack"
rm -rf "$unpack" "$target"
mkdir -p "$unpack"
unzip -q "$zip" -d "$unpack"
mv "$unpack/omeka-s" "$target"
rmdir "$unpack"
echo "$version $sha256" > "$stamp"
echo "Omeka S $version unpacked in build/omeka-s/"
