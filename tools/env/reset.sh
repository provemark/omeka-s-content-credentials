#!/bin/sh
# Removes the integration environment with its database and files, then
# starts a fresh one (M0.3). Touches only the compose project
# content-credentials-test.
set -eu
cd "$(dirname "$0")/../.."
docker compose down --volumes
exec tools/env/up.sh
