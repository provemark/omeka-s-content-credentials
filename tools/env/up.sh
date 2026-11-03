#!/bin/sh
# Starts the integration environment and installs Omeka S once (M0.3).
# Idempotent: a second run finds Omeka S installed and leaves it.
set -eu
cd "$(dirname "$0")/../.."

docker compose build --quiet
docker compose up --detach --wait

set +e
docker compose exec -T omeka php /usr/local/lib/omeka-install.php
status=$?
set -e
if [ "$status" -ne 0 ] && [ "$status" -ne 2 ]; then
    echo "Error: installing Omeka S failed (exit $status)." >&2
    exit 1
fi

url="http://127.0.0.1:${CC_TEST_PORT:-8088}/login"
code=$(curl -s -o /dev/null -w '%{http_code}' "$url")
if [ "$code" != 200 ]; then
    echo "Error: $url answered HTTP $code." >&2
    exit 1
fi
echo "Omeka S is up at http://127.0.0.1:${CC_TEST_PORT:-8088}/"
