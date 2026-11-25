# syntax=docker/dockerfile:1
# Image WordPress / WooCommerce cho Luxury Watches (Docker Compose local và Railway).
#
#  - WordPress core, WooCommerce, Storefront, SePay Gateway: tải bản cố định, kiểm tra SHA-256.
#  - Theme con và plugin tùy chỉnh: lấy từ repository.
#  - Không chứa database, .env, wp-config thật hay bất kỳ bí mật nào.
#  - Entrypoint chạy root chỉ để trao quyền volume/disk uploads cho www-data (Render, Railway
#    gắn disk với quyền root); mọi request PHP do tiến trình Apache www-data xử lý.
#    Apache lắng nghe cổng $PORT (mặc định 8080; Render cấp 10000).
#
# Nâng phiên bản: đổi ARG tương ứng và cập nhật SHA-256 (sha256sum của file tải về).

ARG PHP_IMAGE=php:8.3.37-apache-trixie

############################################################################
# Stage 1: tải và kiểm tra mã nguồn bên thứ ba, ghép với mã nguồn dự án
############################################################################
FROM ${PHP_IMAGE} AS source

ARG WP_VERSION=7.1.4
ARG WP_SHA256=881e53a4681ff2cd911651b11cccdede5ae477ddf0765af5d6a1a8b219a1d835
ARG WOOCOMMERCE_VERSION=10.3.4
ARG WOOCOMMERCE_SHA256=578656580e0cf8b694f624bb02f41ccd09f77ef046d64102d7b7f7b0f14bf37f
ARG STOREFRONT_VERSION=4.6.1
ARG STOREFRONT_SHA256=7b0f6c6488e45c82cc39ad5cbf3dbba17f787b22c8c2ee5686781a0bee8d8388
ARG SEPAY_VERSION=1.1.24
ARG SEPAY_SHA256=8959be74bce67b0c16ee7e9d9dc245a49cc52a27ec90a4ea5fba52e7d28908da
ARG WPCLI_VERSION=2.12.0
ARG WPCLI_SHA256=ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl unzip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /build
SHELL ["/bin/bash", "-Eeuo", "pipefail", "-c"]

RUN fetch() { curl -fsSL --retry 3 -o "$2" "$1"; echo "$3  $2" | sha256sum -c -; }; \
    fetch "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" wordpress.tgz "${WP_SHA256}"; \
    tar -xzf wordpress.tgz; \
    fetch "https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip" woocommerce.zip "${WOOCOMMERCE_SHA256}"; \
    unzip -q woocommerce.zip -d wordpress/wp-content/plugins; \
    fetch "https://downloads.wordpress.org/plugin/sepay-gateway.${SEPAY_VERSION}.zip" sepay.zip "${SEPAY_SHA256}"; \
    unzip -q sepay.zip -d wordpress/wp-content/plugins; \
    fetch "https://downloads.wordpress.org/theme/storefront.${STOREFRONT_VERSION}.zip" storefront.zip "${STOREFRONT_SHA256}"; \
    unzip -q storefront.zip -d wordpress/wp-content/themes; \
    fetch "https://github.com/wp-cli/wp-cli/releases/download/v${WPCLI_VERSION}/wp-cli-${WPCLI_VERSION}.phar" wp-cli.phar "${WPCLI_SHA256}"; \
    # Bỏ plugin/theme mặc định không dùng.
    rm -rf wordpress/wp-content/plugins/akismet wordpress/wp-content/plugins/hello.php wordpress/wp-content/themes/twenty*; \
    rm -f ./*.tgz ./*.zip

# Mã nguồn dự án (chỉ những gì .dockerignore cho phép).
COPY wordpress/wp-content/themes/luxury-watches-child wordpress/wp-content/themes/luxury-watches-child
COPY wordpress/wp-content/themes/astra wordpress/wp-content/themes/astra
COPY wordpress/wp-content/plugins/luxury-auctions wordpress/wp-content/plugins/luxury-auctions
COPY wordpress/wp-content/plugins/luxury-chatbox wordpress/wp-content/plugins/luxury-chatbox
COPY wordpress/wp-content/plugins/ai-content-guard wordpress/wp-content/plugins/ai-content-guard
COPY docker/wp-config-docker.php wordpress/wp-config.php
COPY docker/healthz.php wordpress/healthz.php

# Media có sẵn của dự án: nạp vào volume uploads ở lần chạy đầu (xem entrypoint).
COPY wordpress/wp-content/uploads seed/uploads

# Quyền chuẩn: thư mục 755, file 644 (mã nguồn chỉ đọc với www-data).
RUN find wordpress seed -type d -exec chmod 755 {} + \
    && find wordpress seed -type f -exec chmod 644 {} + \
    && rm -rf wordpress/wp-content/uploads \
    && mkdir -p wordpress/wp-content/uploads \
    && (cd seed && find uploads -type f -printf '%P %s\n' | sort | sha256sum | cut -c1-16) > seed/VERSION

############################################################################
# Stage 2: image chạy thật
############################################################################
FROM ${PHP_IMAGE}

LABEL org.opencontainers.image.title="luxury-watches-wordpress" \
      org.opencontainers.image.description="WordPress + WooCommerce cho Luxury Watches"

SHELL ["/bin/bash", "-Eeuo", "pipefail", "-c"]

# PHP extension cho WordPress / WooCommerce (mbstring, opcache có sẵn trong image gốc).
RUN savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libavif-dev libfreetype6-dev libicu-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev; \
    docker-php-ext-configure gd --with-avif --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" bcmath exif gd intl mysqli pdo_mysql zip; \
    docker-php-ext-enable opcache; \
    # Giữ lại thư viện runtime, gỡ gói -dev dùng để biên dịch.
    apt-mark auto '.*' >/dev/null; \
    [ -z "$savedAptMark" ] || apt-mark manual $savedAptMark >/dev/null; \
    extDir="$(php -r 'echo ini_get("extension_dir");')"; \
    ldd "$extDir"/*.so | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) next; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search | cut -d: -f1 | sort -u | xargs -r apt-mark manual >/dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*; \
    # Kiểm tra extension bắt buộc.
    for ext in mysqli pdo_mysql gd intl zip exif mbstring "Zend OPcache"; do php -m | grep -qx "$ext"; done

COPY docker/php/luxury.ini /usr/local/etc/php/conf.d/zz-luxury.ini

# Apache: rewrite cho permalink, headers; cổng lấy từ $PORT; chạy được không cần root.
COPY docker/apache/wordpress.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers >/dev/null \
    && echo 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/luxury.conf \
    && a2enconf luxury >/dev/null \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

COPY --from=source /build/wordpress/ /var/www/html/
COPY --from=source /build/seed/ /usr/src/lw-seed/
COPY --from=source /build/wp-cli.phar /usr/local/bin/wp
COPY docker/lw-env.php /var/www/lw-env.php
COPY docker/entrypoint.sh /usr/local/bin/lw-entrypoint

RUN chmod 755 /usr/local/bin/wp /usr/local/bin/lw-entrypoint \
    && sed -i 's/\r$//' /usr/local/bin/lw-entrypoint \
    && chmod 644 /var/www/lw-env.php \
    && chown www-data:www-data /var/www/html/wp-content/uploads

ENV PORT=8080
WORKDIR /var/www/html
EXPOSE 8080

# Volume/disk uploads được gắn ở /var/www/html/wp-content/uploads (compose, Render, Railway).
# Không khai báo VOLUME để nền tảng tự quản lý.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS -o /dev/null "http://127.0.0.1:${PORT}/healthz.php" || exit 1

ENTRYPOINT ["lw-entrypoint"]
CMD ["apache2-foreground"]
