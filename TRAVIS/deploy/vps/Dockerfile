FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install mysqli pdo_mysql mbstring curl \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY deploy/vps/apache-travis.conf /etc/apache2/conf-available/travis.conf
RUN a2enconf travis

COPY deploy/vps/php-production.ini /usr/local/etc/php/conf.d/travis-production.ini
COPY app/ /var/www/html/

# Deployment packages are intentionally restrictive on the host. Normalize the
# image copy so Apache can traverse directories and read application files.
RUN chmod -R a+rX /var/www/html

RUN mkdir -p \
        /var/www/html/storage/cache \
        /var/www/html/Web_app/uploads/logs \
        /var/www/html/assets/uploads/announcements \
        /var/www/html/computer_vision/uploads/videos \
        /var/www/html/computer_vision/calibration_profiles \
    && chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/Web_app/api \
        /var/www/html/Web_app/uploads \
        /var/www/html/assets/uploads \
        /var/www/html/computer_vision

