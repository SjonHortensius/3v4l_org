FROM php:8.2-fpm

RUN set -xe; \
    apt-get update; \
    apt-get install -y --no-install-recommends libmemcached-dev libssl-dev zlib1g-dev; \
    pecl install memcached-3.2.0; \
    docker-php-ext-enable memcached; \
    apt-get install -y --no-install-recommends libpq-dev; \
    docker-php-ext-install pdo pdo_pgsql; \
    apt-get install -y --no-install-recommends libgmp-dev; \
    docker-php-ext-install gmp; \
    rm -rf /tmp/*; \
    rm -rf /var/lib/apt/lists/*;
