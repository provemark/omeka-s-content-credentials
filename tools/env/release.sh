#!/bin/sh
# Builds the zip from the working tree as it is, unpacks it in a clean Omeka S
# and tests it (SPEC-008). The environment is rebuilt at each run and taken down
# afterwards; the test environment of compose.yaml is not touched.
#
# Usage: tools/env/release.sh [--integration] [--php 8.3|8.4|8.5]
#   tests/Release, or with --integration tests/Integration on the zip (AC8);
#   the zip in Omeka S on that PHP, 8.3 by default (AC16). The refusal of AC6 is
#   on PHP 8.2 in any case.
#
# The working tree goes through a temporary index, so that what is about to be
# committed is what gets tested, and neither the real index nor the tree changes.
set -eu
cd "$(dirname "$0")/../.."

suite=Release
CC_RELEASE_PHP=8.3
while [ $# -gt 0 ]; do
    case "$1" in
        --integration) suite=Integration ;;
        --php) CC_RELEASE_PHP=${2:?--php needs a version}; shift ;;
        *) echo "Unknown argument: $1" >&2; exit 2 ;;
    esac
    shift
done
case "$CC_RELEASE_PHP" in
    8.3) CC_RELEASE_BASE_IMAGE=php:8.3-apache@sha256:a2d500abd37ddb8b999e52c11f7bb340772ad5665b254bbb0b2762d6f131acb9 ;;
    8.4) CC_RELEASE_BASE_IMAGE=php:8.4-apache@sha256:884f64b47174de2c0e813270fe7b24a18605729fcebe201302d98c38805530d0 ;;
    8.5) CC_RELEASE_BASE_IMAGE=php:8.5-apache@sha256:08af36df144462d84c7f16190557eec06cb9f656882685610b712a15eec54d46 ;;
    *) echo "No pinned image for PHP $CC_RELEASE_PHP" >&2; exit 2 ;;
esac

work=build/release-index
mkdir -p "$work"
index="$PWD/$work/index"
cp "$(git rev-parse --git-path index)" "$index"
GIT_INDEX_FILE="$index" git add -A
tree=$(GIT_INDEX_FILE="$index" git write-tree)
rm -rf "$work"
sh tools/build.sh "$tree"

COMPOSE_FILE="$PWD/tools/env/compose.release.yaml"
CC_TEST_PORT=8090
CC_TEST_PORT_PHP82=8091
export COMPOSE_FILE CC_TEST_PORT CC_TEST_PORT_PHP82 CC_RELEASE_PHP CC_RELEASE_BASE_IMAGE

version=$(php -r '$i = parse_ini_file("config/module.ini", true); echo $i["info"]["version"];')
zip="build/ContentCredentials-$version.zip"
modules=/var/www/html/modules

docker compose down --volumes --remove-orphans >/dev/null 2>&1 || true
trap 'docker compose down --volumes --remove-orphans >/dev/null 2>&1 || true' EXIT
docker compose build --quiet
docker compose up --detach --wait >/dev/null
for service in omeka omeka-php82; do
    docker compose exec -T "$service" php /usr/local/lib/omeka-install.php
    docker compose cp "$zip" "$service:/tmp/release.zip" >/dev/null 2>&1
    docker compose exec -T "$service" sh -c "cd $modules && unzip -q /tmp/release.zip && chown -R www-data:www-data ContentCredentials && rm /tmp/release.zip"
done

if [ "$suite" = Integration ]; then
    # What the tests run inside the container; nothing else is added to the zip's folder.
    docker compose exec -T omeka mkdir -p "$modules/ContentCredentials/tests/Integration"
    docker compose cp tests/Fixtures omeka:"$modules/ContentCredentials/tests/Fixtures" >/dev/null 2>&1
    docker compose cp tests/Integration/Support omeka:"$modules/ContentCredentials/tests/Integration/Support" >/dev/null 2>&1
    docker compose exec -T omeka chown -R www-data:www-data "$modules/ContentCredentials/tests"
    vendor/bin/pest --testsuite=Integration
else
    vendor/bin/pest --testsuite=Release
fi
