FROM nginx:stable-alpine

MAINTAINER Yanick Witschi <yanick.witschi@terminal42.ch>

# Copy resolver files
COPY ./bin /var/www/composer-resolver/bin
COPY ./src /var/www/composer-resolver/src
COPY ./vendor /var/www/composer-resolver/vendor
COPY ./web /var/www/composer-resolver/web
COPY ./app.php /var/www/composer-resolver/app.php

# Copy nginx config
COPY ./docker/web/nginx.conf /etc/nginx/conf.d/default.conf
