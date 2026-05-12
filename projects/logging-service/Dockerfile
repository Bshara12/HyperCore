FROM php:8.2-fpm-alpine

# تثبيت الحزم الأساسية في Alpine
RUN apk update && apk add --no-cache \
  git \
  unzip \
  curl \
  libxml2-dev \
  libzip-dev \
  oniguruma-dev \
  openssl \
  ca-certificates \
  autoconf \
  make \
  g++ \
  linux-headers

# تثبيت أداة التغطية PCOV وتفعيلها قسرياً
# لاحظ أننا حددنا المسار هنا ليناسب مشروعك /var/www/html
RUN pecl install pcov && \
  docker-php-ext-enable pcov && \
  echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini && \
  echo "pcov.directory=/var/www/html" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

# تثبيت إضافات PHP
RUN docker-php-ext-install pdo pdo_mysql zip bcmath

# إضافة Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

# إزالة --no-dev لضمان وجود Pest وأدوات الاختبار
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]