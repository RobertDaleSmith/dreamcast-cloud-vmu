FROM php:7.4-apache

# Install required dependencies
RUN apt-get update && apt-get install -y \
    imagemagick \
    libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP GD extension
RUN docker-php-ext-install gd

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Create uploads directory and set permissions
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads

# Copy Apache configuration
COPY .htaccess /var/www/html/.htaccess