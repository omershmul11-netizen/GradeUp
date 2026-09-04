FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libreoffice-math \
        libreoffice-writer \
        poppler-utils \
        fontconfig \
        fonts-liberation2 \
        python3 \
        python3-docx \
        python3-numpy \
        python3-pil \
    && docker-php-ext-install curl mysqli pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
