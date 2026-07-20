FROM php:8.2-apache

# 1. Install PostgreSQL development headers and the PDO Postgres extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# 2. Tell Apache to expose your Render environment variables to PHP
RUN echo "PassEnv DB_DRIVER DB_HOST DB_PORT DB_NAME DB_USER DB_PASS TOKEN_ENCRYPTION_KEY TELEGRAM_BOT_TOKEN TIKTOK_REDIRECT_URI" > /etc/apache2/conf-enabled/expose-env.conf

# 3. Copy your project files to the Apache web directory
COPY . /var/www/html/

# 4. Give Apache (www-data) ownership of the directory so it can save uploaded files
RUN chown -R www-data:www-data /var/www/html

# 5. Enable Apache mod_rewrite
RUN a2enmod rewrite

# Expose the standard web port
EXPOSE 80