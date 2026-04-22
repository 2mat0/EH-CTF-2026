FROM php:8.2-apache

# Install the SQLite PDO extension
RUN apt-get update && apt-get install -y libsqlite3-dev sqlite3 && rm -rf /var/lib/apt/lists/*

# Apache needs to be able to create/write the SQLite database file
RUN docker-php-ext-install pdo pdo_sqlite

# Copy the application into Apache's document root
COPY index.php /var/www/html/

RUN chown -R www-data:www-data /var/www/html


EXPOSE 80
