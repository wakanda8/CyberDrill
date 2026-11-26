FROM php:8.4-apache

# 1. Install required Linux tools and PostgreSQL drivers
RUN apt-get update && apt-get install -y libpq-dev zip unzip git curl

# 1.5 Install Node.js (Needed for Vite)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

RUN docker-php-ext-install pdo pdo_pgsql

# 2. Enable Apache rewrite module (Needed for Laravel routes)
RUN a2enmod rewrite

# 2.5 Don't advertise the PHP or Apache version in response headers or error pages
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/zz-hardening.ini \
    && sed -ri -e 's/^ServerTokens .*/ServerTokens Prod/' -e 's/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf

# 3. Change Apache's root directory to Laravel's /public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Set the working directory
WORKDIR /var/www/html

# 5. Copy the project files into the container
COPY . .

# 6. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 7. Install NPM packages and Build Vite Assets
RUN npm install
RUN npm run build

# 8. Give Laravel permission to save files (like sessions and caches)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 9. Command to run when the server starts
CMD php artisan migrate --force && exec apache2-foreground