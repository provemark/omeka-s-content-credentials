#!/bin/sh
# Starts the integration environment and installs Omeka S once in each of
# its two installations (M0.3, SPEC-001). Idempotent: a second run finds
# them installed and leaves them.
set -eu
cd "$(dirname "$0")/../.."

docker compose build --quiet
docker compose up --detach --wait

for service in omeka omeka-php82; do
    set +e
    docker compose exec -T "$service" php /usr/local/lib/omeka-install.php
    status=$?
    set -e
    if [ "$status" -ne 0 ] && [ "$status" -ne 2 ]; then
        echo "Error: installing Omeka S in $service failed (exit $status)." >&2
        exit 1
    fi
done

for port in "${CC_TEST_PORT:-8088}" "${CC_TEST_PORT_PHP82:-8089}"; do
    code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/login")
    if [ "$code" != 200 ]; then
        echo "Error: http://127.0.0.1:$port/login answered HTTP $code." >&2
        exit 1
    fi
done
echo "Omeka S is up at http://127.0.0.1:${CC_TEST_PORT:-8088}/ (PHP 8.3) and http://127.0.0.1:${CC_TEST_PORT_PHP82:-8089}/ (PHP 8.2)"
