FROM gone/php:nginx AS web

# Use the public dir from benzine-html
RUN sed -i 's|/app/public|/app/vendor/benzine/benzine-html/public|g' /etc/nginx/sites-enabled/default

# Create a healthcheck that makes sure our httpd is up
HEALTHCHECK --interval=30s --timeout=3s \
    CMD curl -f http://localhost/v1/ping || exit 1

FROM web AS frontend

RUN mv /app/src/FrontendControllers /app/src/Controllers

FROM web AS backend

RUN mv /app/src/BackendControllers /app/src/Controllers

FROM gone/php:cli AS worker-feed

# Enable PCNTL
RUN sed -i 's|disable_functions|#disabled_functions|g' /etc/php/*/cli/php.ini

COPY feed-ingester.runit /etc/service/feed-ingester/run

HEALTHCHECK --interval=30s --timeout=3s \
    CMD ps aux | grep -v grep | grep feed-ingester

