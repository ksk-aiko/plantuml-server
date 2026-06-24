# Added: minimal PHP runtime for Phase 1 MVP
FROM php:8.3-cli-alpine

WORKDIR /var/www/html

# Changed: use project source as web root entrypoint
COPY public/ /var/www/html/public/
COPY scripts/cleanup-temp-files.sh /usr/local/bin/cleanup-temp-files.sh
COPY scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY crontabs/root /etc/crontabs/root

RUN chmod +x /usr/local/bin/cleanup-temp-files.sh /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
