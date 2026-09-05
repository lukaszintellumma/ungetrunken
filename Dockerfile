FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql opcache \
 && a2enmod rewrite headers

COPY app/    /var/www/app/
COPY public/ /var/www/html/

RUN printf '%s\n' \
  'opcache.enable=1' \
  'opcache.validate_timestamps=1' \
  'expose_php=Off' \
  'display_errors=Off' \
  'log_errors=On' \
  > /usr/local/etc/php/conf.d/ungetrunken.ini

RUN chown -R www-data:www-data /var/www
