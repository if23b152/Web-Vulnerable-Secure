FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev unzip less \
  && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

RUN docker-php-ext-install fileinfo || true

RUN a2enmod rewrite
