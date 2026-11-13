FROM php:8.3-cli

# Ekstensi MySQL (opsional). pdo_sqlite, mbstring, xml sudah bawaan image.
RUN docker-php-ext-install pdo_mysql

# Batas upload sesuai aplikasi (5 MB) + sedikit ruang
RUN { echo "upload_max_filesize=6M"; echo "post_max_size=8M"; } > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app
COPY . /app/

# Lokasi Volume Railway untuk database SQLite
RUN mkdir -p /data

# Server bawaan PHP, 4 worker, port dari Railway ($PORT)
CMD ["sh", "-c", "mkdir -p /data && PHP_CLI_SERVER_WORKERS=4 exec php -S 0.0.0.0:${PORT:-8080} -t /app"]
