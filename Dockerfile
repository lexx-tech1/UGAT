FROM php:8.2-apache

# System dependencies (needed for mPDF, zip, curl)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        mysqli \
        pdo \
        pdo_mysql \
        gd \
        zip \
        mbstring \
        xml \
        bcmath \
        opcache

# Fix "More than one MPM loaded" — wipe all MPM entries, link only prefork
RUN find /etc/apache2/mods-enabled/ -name 'mpm_*.load' -delete; \
    find /etc/apache2/mods-enabled/ -name 'mpm_*.conf' -delete; \
    ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load; \
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set document root to the ugat app folder
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# PHP production config
RUN echo "display_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "error_log = /dev/stderr" >> /usr/local/etc/php/php.ini \
    && echo "upload_max_filesize = 10M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 12M" >> /usr/local/etc/php/php.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/php.ini \
    && echo "max_execution_time = 60" >> /usr/local/etc/php/php.ini

# Copy application files
COPY ugat/ /var/www/html/

# Create upload directories and set permissions
RUN mkdir -p /var/www/html/uploads/avatars \
             /var/www/html/uploads/inventory \
             /var/www/html/uploads/receipts \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Railway injects PORT at runtime — fix MPM at startup then launch Apache
CMD ["/bin/sh", "-c", \
    "find /etc/apache2/mods-enabled/ -name 'mpm_*.load' -delete; \
     find /etc/apache2/mods-enabled/ -name 'mpm_*.conf' -delete; \
     ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load; \
     ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf; \
     sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && \
     sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-enabled/000-default.conf && \
     apache2-foreground"]
