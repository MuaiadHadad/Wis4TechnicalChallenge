FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy our application files
COPY . /var/www/html/

# Install dependencies at build (image layer) - still helpful for CI
RUN composer install --no-dev --optimize-autoloader || true

# PHP upload limits (100MB)
RUN bash -lc 'echo "upload_max_filesize=100M" > /usr/local/etc/php/conf.d/uploads.ini && \
              echo "post_max_size=110M" >> /usr/local/etc/php/conf.d/uploads.ini && \
              echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/uploads.ini'

# Create necessary directories
RUN mkdir -p /var/www/html/writable/cache \
    /var/www/html/writable/logs \
    /var/www/html/writable/session \
    /var/www/html/writable/uploads

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/writable

# Apache configuration
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Update Apache document root to point to public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Runtime entrypoint to ensure vendor exists when bind-mounted
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

EXPOSE 88

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
