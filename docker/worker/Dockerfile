FROM php:7-cli

# Copy resolver files
COPY ./bin /var/www/composer-resolver/bin
COPY ./src /var/www/composer-resolver/src
COPY ./vendor /var/www/composer-resolver/vendor
COPY ./web /var/www/composer-resolver/web
COPY ./app.php /var/www/composer-resolver/app.php

# Copy php.ini
COPY ./docker/php-fpm/php.ini /usr/local/etc/php/conf.d/composer-resolver.ini
