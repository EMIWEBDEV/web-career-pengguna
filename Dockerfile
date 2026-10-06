# =================================================================
# Stage 1: Build front-end assets 
# =================================================================
FROM node:18-alpine AS node-builder
WORKDIR /app
ENV NODE_OPTIONS=--max-old-space-size=3072

# Salin file package untuk caching
COPY package*.json ./

# Install node dependencies
RUN npm ci

# Salin SEMUA file proyek. Ini memastikan semua file konfigurasi (vite, postcss, tailwind)
# dan source code (resources/js) tersedia untuk proses build.
COPY . .

# Jalankan build. Sekarang, build akan berjalan dengan semua file yang diperlukan.
RUN npm run build

# =================================================================
# Stage 2: PHP Runtime dengan Apache (Lingkungan Produksi)
# =================================================================
FROM php:8.3-apache

ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies & PHP extensions
# - poppler-utils: pdftoppm (render PDF->image cepat, untuk secure PDF viewer)
# - ghostscript: dibutuhkan Imagick untuk membaca PDF (watermark) — TANPA ini render PDF sangat lambat
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libzip-dev gnupg2 \
    libmagickwand-dev imagemagick ghostscript poppler-utils \
    fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml zip

# Install Composer (Tidak ada perubahan)
# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer


# Install Microsoft ODBC Driver & SQL Server extensions (Tidak ada perubahan)
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /etc/apt/keyrings/microsoft.gpg \
    && echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/11/prod bullseye main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 unixodbc-dev \
    && pecl install sqlsrv pdo_sqlsrv imagick \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv imagick \
    && rm -rf /var/lib/apt/lists/*

# ImageMagick policy default MEMBLOKIR PDF (CVE Ghostscript). Aktifkan kembali
# agar Imagick bisa membaca/menulis PDF untuk proses watermark.
RUN set -eux; \
    for f in /etc/ImageMagick-6/policy.xml /etc/ImageMagick-7/policy.xml; do \
        if [ -f "$f" ]; then \
            sed -ri 's!<policy domain="coder" rights="none" pattern="PDF" ?/>!<policy domain="coder" rights="read|write" pattern="PDF" />!g' "$f"; \
        fi; \
    done

# Set working directory
WORKDIR /var/www/html

# Salin file composer dan install dependencies tanpa menjalankan skrip
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --optimize-autoloader

# Salin semua file aplikasi
COPY . .

COPY --from=node-builder /app/public/build ./public/build

# DIREKTORI KERJA LARAVEL DIBUAT DI SINI, BUKAN DIANDALKAN DARI GIT.
#
# `config/view.php` menghitung lokasi cache Blade dengan realpath(), dan
# realpath() mengembalikan FALSE untuk direktori yang tidak ada. Bila
# storage/framework/views tidak ikut ke dalam image, `view:compiled` menjadi
# false dan `php artisan view:clear` gagal dengan "View path not found" —
# menjatuhkan seluruh build, seperti yang terjadi.
#
# Git tidak bisa menyimpan direktori kosong, jadi keberadaannya bergantung pada
# berkas penanda yang mudah sekali ikut tersapu aturan .gitignore atau
# .dockerignore. Membuatnya di sini memutus ketergantungan itu: build tidak lagi
# bisa dijatuhkan oleh satu baris ignore yang ditambahkan berbulan-bulan lalu.
RUN mkdir -p \
        storage/framework/views \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/logs \
        storage/app/public \
        bootstrap/cache

RUN cp .env.example .env \
    && php artisan key:generate --ansi \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# =================================================================
# Konfigurasi Final Server & Entrypoint
# =================================================================
# Salin konfigurasi PHP kustom
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Arahkan DocumentRoot Apache ke folder public Laravel dan aktifkan rewrite
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && a2enmod rewrite

# Biarkan CMD default dari base image php:apache yang akan berjalan
EXPOSE 80
