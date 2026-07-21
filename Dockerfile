FROM php:8.2-apache

# 1. Install PostgreSQL development headers and the PDO Postgres extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# 2. Increase PHP upload limits to 50MB
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini

# 3. Tell Apache to expose your Render environment variables to PHP
RUN echo "PassEnv DB_DRIVER DB_HOST DB_PORT DB_NAME DB_USER DB_PASS TOKEN_ENCRYPTION_KEY TELEGRAM_BOT_TOKEN TIKTOK_CLIENT_KEY TIKTOK_CLIENT_SECRET TIKTOK_REDIRECT_URI" > /etc/apache2/conf-enabled/expose-env.conf

# 4. Copy your project files to the Apache web directory
COPY . /var/www/html/

# 5. Delete any old blocking .htaccess inside your uploads folder
RUN rm -f /var/www/html/uploads/.htaccess

# 6. Give Apache (www-data) ownership of the directory
RUN chown -R www-data:www-data /var/www/html

# 7. Enable Apache mod_rewrite
RUN a2enmod rewrite

# Expose the standard web port
EXPOSE 80