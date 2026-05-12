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

# تثبيت Redis و PCOV مع التفعيل القسري للإعدادات
RUN pecl install redis pcov && \
  docker-php-ext-enable redis pcov && \
  echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini && \
  echo "pcov.directory=app" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

# تثبيت إضافات PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets

# إضافة Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# تثبيت المكتبات مع السماح ببيئة التطوير لتشغيل Pest والتغطية
RUN composer install --optimize-autoloader

CMD ["php-fpm"]