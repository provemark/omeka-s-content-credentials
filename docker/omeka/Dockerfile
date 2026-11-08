# The integration environment's Omeka S (M0.3, notes/step-03-test-environment.md).
#
# Built from the release zip of the pinned tag, checked against its SHA-256,
# on the official PHP image pinned by digest. PHP 8.3 is the floor the
# verifier sets (^8.3) and the default; Omeka S 4.2.1 itself runs from 8.1.
# compose.yaml builds it a second time on PHP 8.2, where the module must
# refuse to install (SPEC-001 AC6).
ARG BASE_IMAGE=php:8.3-apache@sha256:a2d500abd37ddb8b999e52c11f7bb340772ad5665b254bbb0b2762d6f131acb9
FROM ${BASE_IMAGE}

ARG OMEKA_VERSION=4.2.1
ARG OMEKA_SHA256=cc27d1c7aca0209523d19aa285f4a08e29e34950dcc446951a7c1311de348e82

# pdo_mysql is the one extension Omeka S needs that the image lacks
# (fileinfo, mbstring, PDO, xml, json, openssl and sodium are present).
# ImageMagick is Omeka S's default thumbnailer; unzip unpacks the release.
RUN apt-get update \
    && apt-get install -y --no-install-recommends imagemagick unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# --retry: a 5xx of GitHub's release downloads is retried for about five minutes; a 404 is not.
# --http1.1: over HTTP/2, curl 8.7.1 reported GitHub's 504 as error 56 and did not retry it.
RUN curl -sSfL --http1.1 --retry 10 --retry-delay 30 -o /tmp/omeka-s.zip \
        "https://github.com/omeka/omeka-s/releases/download/v${OMEKA_VERSION}/omeka-s-${OMEKA_VERSION}.zip" \
    && echo "${OMEKA_SHA256}  /tmp/omeka-s.zip" | sha256sum -c - \
    && unzip -q /tmp/omeka-s.zip -d /tmp \
    && rm -rf /var/www/html \
    && mv /tmp/omeka-s /var/www/html \
    && rm /tmp/omeka-s.zip \
    && chown -R www-data:www-data /var/www/html/files /var/www/html/logs

# Omeka S's .htaccess needs AllowOverride.
RUN sed -i 's#AllowOverride None#AllowOverride All#' /etc/apache2/apache2.conf

# PHP's default upload limit (2M) refuses the 5.8 MB Pixel 10 fixture of
# SPEC-004 (measured, notes/step-17-trust-settings.md); Omeka S installations
# commonly raise it the same way.
RUN printf 'upload_max_filesize = 64M\npost_max_size = 64M\n' > /usr/local/etc/php/conf.d/uploads.ini

COPY entrypoint.sh /usr/local/bin/omeka-entrypoint
COPY install.php /usr/local/lib/omeka-install.php

ENTRYPOINT ["omeka-entrypoint"]
CMD ["apache2-foreground"]
