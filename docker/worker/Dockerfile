FROM php:7.0-cli

MAINTAINER Yanick Witschi <yanick.witschi@terminal42.ch>

# Install the packages we need
RUN apt-get update
RUN apt-get install -y git-all mercurial

# Copy resolver files
COPY ./bin /var/www/composer-resolver/bin
COPY ./src /var/www/composer-resolver/src
COPY ./vendor /var/www/composer-resolver/vendor
COPY ./web /var/www/composer-resolver/web
COPY ./app.php /var/www/composer-resolver/app.php

# Copy php.ini
COPY ./docker/worker/php.ini /usr/local/etc/php/conf.d/composer-resolver.ini

ENV COMPOSER_CACHE_DIR=/var/composer-cache

CMD php /var/www/composer-resolver/bin/worker.php
