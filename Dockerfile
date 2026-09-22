# syntax=docker/dockerfile:1
#
# Image produksi RakKu untuk Coolify, mengikuti susunan yang sudah berjalan di
# proyek Masjid An-Nur: FrankenPHP melayani HTTP biasa di dalam container,
# sedangkan TLS diselesaikan Cloudflare Tunnel di depannya. Scheduler (termasuk
# pemrosesan queue) dijalankan lewat Scheduled Task Coolify, bukan proses di sini.

FROM dunglas/frankenphp:php8.4-bookworm

# intl diminta Filament, zip untuk export XLSX, pcntl agar batas waktu job queue berlaku.
RUN install-php-extensions \
        intl \
        zip \
        pdo_mysql \
        opcache \
        pcntl

# Composer dijalankan dengan PHP yang sama seperti runtime agar pemeriksaan ekstensi akurat.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependency dipasang lebih dulu supaya layer ini ter-cache selama composer.lock tidak berubah.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# dump-autoload juga menjalankan filament:upgrade yang menerbitkan aset CSS/JS Filament ke public/.
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/php.ini /usr/local/etc/php/conf.d/rakku.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# HTTPS bawaan FrankenPHP dimatikan: TLS diselesaikan Cloudflare.
ENV SERVER_NAME=":80"

EXPOSE 80

# /up menjawab tanpa menyentuh database, jadi menguji "aplikasi melayani request".
# start-period 60 detik: entrypoint menjalankan migrate + optimize (kompilasi view Filament) sebelum FrankenPHP menyala.
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1:80/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
