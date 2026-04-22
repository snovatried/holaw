FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    msmtp \
    msmtp-mta \
    ca-certificates \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

RUN printf "sendmail_path = \"/usr/bin/msmtp -t -i\"\n" > /usr/local/etc/php/conf.d/mail.ini

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY . /var/www/html/

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
