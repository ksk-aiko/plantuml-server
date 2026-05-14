# Added: minimal PHP runtime for Phase 1 MVP
FROM php:8.3-cli-alpine

WORKDIR /var/www/html

# Changed: use project source as web root entrypoint
COPY public/ /var/www/html/public/

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "/var/www/html/public"]
