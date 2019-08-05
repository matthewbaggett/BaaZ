FROM gone/php:cli AS worker
# Enable PCNTL
RUN sed -i 's|disable_functions|#disabled_functions|g' /etc/php/*/cli/php.ini

FROM worker AS worker-feed
COPY feed-ingester.runit /etc/service/feed-ingester/run
HEALTHCHECK --interval=30s --timeout=3s \
    CMD ps aux | grep -v grep | grep feed-ingester

FROM worker AS worker-images
COPY image-downloader.runit /etc/service/image-downloader/run
HEALTHCHECK --interval=30s --timeout=3s \
    CMD ps aux | grep -v grep | grep image-downloader

FROM worker AS worker-solr
COPY solr-ingester.runit /etc/service/solr-ingester/run
HEALTHCHECK --interval=30s --timeout=3s \
    CMD ps aux | grep -v grep | grep solr-ingester

FROM worker AS worker-stats
COPY stats-generator.runit /etc/service/stats-generator/run
HEALTHCHECK --interval=30s --timeout=3s \
    CMD ps aux | grep -v grep | grep stats-generator

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