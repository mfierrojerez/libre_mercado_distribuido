FROM php:8.2-apache

# Instalamos las extensiones necesarias para PDO y MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Habilitamos mod_rewrite de Apache por si lo necesitas para URLs amigables
RUN a2enmod rewrite