# Added: minimal PHP runtime for Phase 1 MVP
FROM php:8.3-cli-alpine

WORKDIR /var/www/html

# Added: bootstrap a minimal public entrypoint so container can start safely
RUN mkdir -p /var/www/html/public \
    && printf '%s\n' '<?php echo "PlantUML MVP API placeholder";' > /var/www/html/public/index.php

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "/var/www/html/public"]
