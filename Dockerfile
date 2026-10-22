FROM php:8.5-apache
RUN printf '%s\n' \
    'error_reporting = E_ALL' \
    'log_errors = On' \
    'display_errors = Off' \
    'display_startup_errors = Off' \
    'error_log = /proc/self/fd/2' \
    > /usr/local/etc/php/conf.d/zz-logging.ini

RUN apt-get update \
    && apt-get install -y libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli curl mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY . /var/www/html

RUN printf "display_errors = Off\nlog_errors = On\nerror_reporting = E_ALL\noutput_buffering = 4096\n" > /usr/local/etc/php/conf.d/zzz-app.ini

RUN chown -R www-data:www-data /var/www/html
