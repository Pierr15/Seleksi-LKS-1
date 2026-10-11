FROM php:8.3-apache

ENV TZ=Asia/Jakarta \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && a2enmod rewrite headers \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory %s>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' "$APACHE_DOCUMENT_ROOT" \
        > /etc/apache2/conf-available/orbit-docroot.conf \
    && a2enconf orbit-docroot

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libonig-dev unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli intl zip mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/entrypoint.sh /usr/local/bin/orbit-entrypoint.sh
RUN chmod +x /usr/local/bin/orbit-entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/orbit-entrypoint.sh"]
CMD ["apache2-foreground"]
