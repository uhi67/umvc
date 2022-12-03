FROM php:7.4-apache-buster

RUN apt-get update -y && \
    apt-get install -y libicu-dev git libbz2-1.0 libbz2-dev libssl-dev libzip-dev
RUN docker-php-ext-install bz2 intl mysqli phar zip pdo_mysql

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

RUN ln -fs /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

RUN rm -rf /var/www/html && ln -fs /app/tests/_data/testapp/www /var/www/html

COPY app.conf /etc/apache2/sites-available/app.conf

WORKDIR /app

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod a+x /usr/local/bin/entrypoint.sh
CMD printenv | sed 's/^\([a-zA-Z0-9_]*\)=\(.*\)$/export \1="\2"/g' > /root/env.sh \
    && /usr/local/bin/entrypoint.sh || true \
    && exec apache2-foreground
