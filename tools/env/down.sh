#!/bin/sh
# Stops the integration environment; its database and files are kept (M0.3).
set -eu
cd "$(dirname "$0")/../.."
docker compose down
