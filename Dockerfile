FROM php:8.2-apache

# System deps for GD
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libwebp-dev unzip git \
  && rm -rf /var/lib/apt/lists/*

# Enable PHP extensions (pdo_mysql, gd)
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
  && docker-php-ext-install -j$(nproc) gd pdo_mysql

# Apache configuration
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
 && a2enmod rewrite

# Copy source
COPY . /var/www/html

# Ensure uploads directory exists with writable perms
RUN mkdir -p /var/www/html/uploads /var/www/html/uploads/media /var/www/html/uploads/products /var/www/html/uploads/settings \
 && chown -R www-data:www-data /var/www/html/uploads \
 && chmod -R 775 /var/www/html/uploads

# Environment variables (override at deploy time)
ENV DB_HOST=localhost \
    DB_NAME=thismychoice_lite \
    DB_USER=root \
    DB_PASS="" \
    GOOGLE_API_KEY="" \
    ANTHROPIC_API_KEY="" \
    OPENAI_API_KEY=""

# Expose port
EXPOSE 80

# No entrypoint override; use Apache default

