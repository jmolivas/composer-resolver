FROM php:7-cli

# Copy resolver files
COPY ./bin /var/www/composer-resolver/bin
COPY ./src /var/www/composer-resolver/src
COPY ./vendor /var/www/composer-resolver/vendor
COPY ./web /var/www/composer-resolver/web
COPY ./app.php /var/www/composer-resolver/app.php

RUN apt-get update

# Install PHP zip extension required for Composer artifact repositories
RUN apt-get install -y zlib1g-dev && docker-php-ext-install zip

# Install git for composer vcs repos
RUN apt-get -y install git

# Copy php.ini
COPY ./docker/worker/php.ini /usr/local/etc/php/conf.d/composer-resolver.ini

CMD php /var/www/composer-resolver/bin/worker.php
