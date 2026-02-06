FROM php:8.2-cli
RUN apt-get update -y && \
    apt-get install -y libicu-dev git libbz2-1.0 libbz2-dev libssl-dev libzip-dev
RUN docker-php-ext-install bz2 intl mysqli phar zip pdo_mysql

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

RUN ln -fs /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

#RUN rm -rf /var/www/html && ln -fs /app/tests/_data/testapp/www /var/www/html
#COPY app.conf /etc/apache2/sites-available/app.conf

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader
COPY . .

COPY tests/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod a+x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
