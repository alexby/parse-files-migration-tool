FROM php:7.0-cli

RUN curl -o /usr/bin/docker-php-pecl-install -L https://raw.githubusercontent.com/helderco/docker-php/master/template/bin/docker-php-pecl-install \
    && chmod +x /usr/bin/docker-php-pecl-install

RUN apt-get update && apt-get install -y \
    libssl-dev \
    git

RUN docker-php-pecl-install mongodb

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

WORKDIR /app
