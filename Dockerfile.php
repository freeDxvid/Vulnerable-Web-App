FROM php:8.2-apache-trixie

# Install curl extension (needed for SSRF) and libxml
RUN apt-get -y update && apt-get -y install --no-install-recommends libcurl4-openssl-dev libxml2-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

ARG UID=1000
ARG GID=1000
ARG XXE_FLAG=FLAG_PLACEHOLDER_xxe
ARG TYPE_JUGGLING_FLAG=FLAG_PLACEHOLDER_type_juggling

# Create a non-root user to run PHP
RUN groupadd -g "$GID" php \
    && useradd --create-home --no-log-init --base-dir /var/www/html \
               -u "$UID" -g "$GID" php

# Place the XXE flag as a local file
RUN echo "$XXE_FLAG" > /etc/flag.txt && chmod 644 /etc/flag.txt

# Type Juggling flag via environment
ENV TYPE_JUGGLING_FLAG=${TYPE_JUGGLING_FLAG}

RUN echo "extension=curl" >> /usr/local/etc/php/conf.d/custom.ini

USER php
