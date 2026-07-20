FROM php:8.2-apache

# Install PostgreSQL development headers and the PDO Postgres extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copy your project files to the Apache web directory
COPY . /var/www/html/

# Enable Apache mod_rewrite (so your htaccess files work if you use them)
RUN a2enmod rewrite

# Expose the standard web port
EXPOSE 80