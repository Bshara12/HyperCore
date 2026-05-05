FROM php:8.2-fpm-alpine

# تثبيت الحزم الأساسية في Alpine
RUN apk update && apk add --no-cache \
  git \
  curl \
  zip \
  unzip \
  libpng-dev \
  libxml2-dev \
  oniguruma-dev \
  openssl \
  ca-certificates \
  autoconf \
  make \
  g++ \
  linux-headers

# تثبيت Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# تثبيت إضافات PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets

# إضافة Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

CMD ["php-fpm"]
