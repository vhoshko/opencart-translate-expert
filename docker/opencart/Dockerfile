# OpenCart test store for the Translate Expert module.
# One Dockerfile for every store; compose.yaml picks the PHP image and the
# OpenCart release through build args.

ARG PHP_IMAGE=php:7.4-apache

# --- Stage 1: download and verify the OpenCart release ----------------------
FROM alpine:3.20 AS fetch

ARG OC_URL
ARG OC_SHA256
ARG OC_UPLOAD_DIR=upload

RUN apk add --no-cache curl unzip \
 && curl -fsSL -o /tmp/opencart.zip "$OC_URL" \
 && echo "$OC_SHA256  /tmp/opencart.zip" | sha256sum -c - \
 && unzip -q /tmp/opencart.zip -d /tmp/oc \
 && mv "/tmp/oc/$OC_UPLOAD_DIR" /opencart

# --- Stage 2: PHP + Apache with the extensions OpenCart needs ---------------
FROM ${PHP_IMAGE}

COPY php-setup.sh /usr/local/bin/php-setup.sh
RUN sh /usr/local/bin/php-setup.sh && rm /usr/local/bin/php-setup.sh

COPY --from=fetch /opencart/ /var/www/html/
COPY entrypoint.sh /usr/local/bin/opencart-entrypoint.sh
RUN chmod +x /usr/local/bin/opencart-entrypoint.sh \
 && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["/usr/local/bin/opencart-entrypoint.sh"]
CMD ["apache2-foreground"]
