FROM alpine:edge

MAINTAINER Yanick Witschi <yanick.witschi@terminal42.ch>

# Install the packages we need
RUN apk update

RUN apk add --no-cache --repository "http://dl-cdn.alpinelinux.org/alpine/edge/testing" \
    ca-certificates \
    git \
    mercurial \
    openssh \
    subversion \
    php7 \
    php7-zip \
    php7-ctype \
    php7-curl \
    php7-iconv \
    php7-json \
    php7-openssl \
    php7-phar \
    php7-posix \
    php7-opcache

RUN mv /usr/bin/php7 /usr/bin/php

# Copy resolver files
COPY ./bin /var/www/composer-resolver/bin
COPY ./src /var/www/composer-resolver/src
COPY ./vendor /var/www/composer-resolver/vendor
COPY ./web /var/www/composer-resolver/web
COPY ./app.php /var/www/composer-resolver/app.php

# Copy php.ini
COPY ./docker/worker/php.ini /etc/php7/conf.d/composer-resolver.ini

CMD php /var/www/composer-resolver/bin/worker.php
