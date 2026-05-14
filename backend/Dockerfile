# Immagine base con PHP 8.3 e Apache
FROM chialab/php:8.3-apache

# Installiamo le librerie di sistema necessarie per l'estensione GD
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Configuriamo e installiamo l'estensione GD (gestione immagini PNG, JPEG, FreeType)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

RUN docker-php-ext-install pcntl

# Abilitiamo mod_rewrite di Apache, necessario per le rotte di Laravel
RUN a2enmod rewrite

# Spostiamo la DocumentRoot di Apache da /var/www/html a /var/www/html/public (cartella pubblica di Laravel)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

RUN sed -ri -e 's!<Directory /var/www/>!<Directory /var/www/html/public/>\n\t\tAllowOverride All\n\t\tRequire all granted!g' /etc/apache2/apache2.conf \
    || echo '<Directory /var/www/html/public/>\n\tOptions Indexes FollowSymLinks\n\tAllowOverride All\n\tRequire all granted\n</Directory>' >> /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel || true

