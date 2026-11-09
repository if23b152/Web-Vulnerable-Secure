FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev unzip less \
  && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

RUN docker-php-ext-install fileinfo || true

RUN a2enmod rewrite headers

# Server-Version nicht preisgeben, Directory-Listing aus, .htaccess erlauben
RUN { \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
      echo 'TraceEnable Off'; \
      echo '<Directory /var/www/html>'; \
      echo '    Options -Indexes +FollowSymLinks'; \
      echo '    AllowOverride All'; \
      echo '</Directory>'; \
      echo '<FilesMatch "^\.(env|git|ht)">'; \
      echo '    Require all denied'; \
      echo '</FilesMatch>'; \
    } > /etc/apache2/conf-available/security-hardening.conf \
  && a2enconf security-hardening

# Upload-Verzeichnis außerhalb des Webroots (APP_UPLOAD_DIR=/var/uploads in .env)
RUN mkdir -p /var/uploads && chown www-data:www-data /var/uploads && chmod 700 /var/uploads
