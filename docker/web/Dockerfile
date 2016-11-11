FROM nginx:stable-alpine

MAINTAINER Yanick Witschi <yanick.witschi@terminal42.ch>

# Copy resolver files
COPY ./bin /var/www/composer-resolver/bin
COPY ./src /var/www/composer-resolver/src
COPY ./vendor /var/www/composer-resolver/vendor
COPY ./web /var/www/composer-resolver/web
COPY ./app.php /var/www/composer-resolver/app.php

# Copy nginx config
COPY ./docker/web/nginx.conf /tmp/nginx-conf-tpl.conf

# Replace env vars and run
CMD /bin/sh -c "envsubst '\$COMPOSER_RESOLVER_JOBS_QUEUE_KEY \$COMPOSER_RESOLVER_POLLING_FREQUENCY \$COMPOSER_RESOLVER_JOBS_TTL \$COMPOSER_RESOLVER_JOBS_ATPJ \$COMPOSER_RESOLVER_WORKERS' < /tmp/nginx-conf-tpl.conf > /etc/nginx/conf.d/default.conf && nginx -g 'daemon off;'"
