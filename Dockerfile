FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*

RUN printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

ENV APACHE_LOG_LEVEL=debug

EXPOSE 80

CMD ["apache2-foreground"]