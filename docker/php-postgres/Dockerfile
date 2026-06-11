ARG PHP_FPM_IMAGE=itkdev/php8.4-fpm:latest
FROM ${PHP_FPM_IMAGE}

USER root
RUN apt-get update \
    && apt-get install -y --no-install-recommends php8.4-pgsql \
    && rm -rf /var/lib/apt/lists/*
USER deploy
