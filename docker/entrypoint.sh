#!/bin/sh
set -e

# Pastikan direktori writable dapat ditulis oleh proses Apache (www-data)
# ketika source code di-bind mount dari Windows.
mkdir -p writable/cache writable/logs writable/session writable/uploads
chown -R www-data:www-data writable 2>/dev/null || true
chmod -R u+rwX writable 2>/dev/null || true

exec "$@"
