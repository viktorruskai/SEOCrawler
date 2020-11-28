FROM php:7.4-fpm-alpine

RUN apk --update --no-cache add \
        make \
        nano \
        libxml2-dev \
        curl \
        nodejs \
        libmcrypt-dev \
        mysql-client
RUN docker-php-ext-install \
        pdo_mysql \
        xml

# Copy existing application directory contents
COPY . /var/www

WORKDIR /var/www

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

CMD ["php-fpm"]