FROM php:8.3-apache

# Ekstensi untuk MySQL (opsional). pdo_sqlite, mbstring, xml sudah bawaan image.
RUN docker-php-ext-install pdo_mysql

# Batas upload sesuai aplikasi (5 MB) + sedikit ruang
RUN { echo "upload_max_filesize=6M"; echo "post_max_size=8M"; } > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
COPY . /var/www/html/

# Folder data lokal tidak dipakai di produksi (DB ada di Volume /data)
RUN rm -rf /var/www/html/data && mkdir -p /data \
    && chown -R www-data:www-data /var/www/html /data

# Railway memberi port lewat variabel $PORT
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-8080}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT:-8080}>/\" /etc/apache2/sites-available/000-default.conf && mkdir -p /data && chown -R www-data:www-data /data && exec apache2-foreground"]
