#!/bin/sh
# Writes Omeka S's database.ini from the environment, then hands over to the
# command (apache2-foreground by default).
set -eu

cat > /var/www/html/config/database.ini <<EOF
user     = "${OMEKA_DB_USER}"
password = "${OMEKA_DB_PASSWORD}"
dbname   = "${OMEKA_DB_NAME}"
host     = "${OMEKA_DB_HOST}"
EOF

exec "$@"
