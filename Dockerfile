FROM php:7.4-fpm-alpine

RUN apk --update --no-cache add \
    && libmcrypt-dev \
    mysql-client libmagickwand-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-install mcrypt pdo_mysql \
    && rm -rf /var/cache/apk/*

# Copy existing application directory contents
COPY . /var/www

WORKDIR /var/www

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

CMD ["php-fpm"]