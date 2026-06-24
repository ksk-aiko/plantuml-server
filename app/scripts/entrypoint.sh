#!/bin/sh
set -eu

mkdir -p /tmp/plantuml_exports

# Run cron daemon for periodic temp file cleanup.
crond

exec php -S 0.0.0.0:8000 -t /var/www/html/public
