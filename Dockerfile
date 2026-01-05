FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libssl-dev \
    pkg-config \
    unzip \
    git \
    libzip-dev \
    && docker-php-ext-install zip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache to allow .htaccess
# This enables the rewrite rules defined in your .htaccess file
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Install dependencies
# Remove local composer.lock and vendor to ensure fresh install for this environment
RUN rm -rf vendor composer.lock

# --no-dev: Don't install development dependencies
# --optimize-autoloader: Optimize autoloader for production
RUN composer install --no-dev --optimize-autoloader

# Set permissions
# Ensure Apache user (www-data) owns the files
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
