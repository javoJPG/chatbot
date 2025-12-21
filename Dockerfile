FROM php:8.2-apache

# Deshabilitar todos los MPMs explícitamente
RUN set -eux; \
    a2dismod mpm_event 2>/dev/null || true; \
    a2dismod mpm_worker 2>/dev/null || true; \
    rm -f /etc/apache2/mods-enabled/mpm_event.load 2>/dev/null || true; \
    rm -f /etc/apache2/mods-enabled/mpm_worker.load 2>/dev/null || true; \
    rm -f /etc/apache2/mods-enabled/mpm_event.conf 2>/dev/null || true; \
    rm -f /etc/apache2/mods-enabled/mpm_worker.conf 2>/dev/null || true

# Habilitar solo mpm_prefork (necesario para PHP)
RUN a2enmod mpm_prefork

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Configurar Apache para permitir acceso al directorio
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
RUN sed -i '/<Directory \/var\/www\/html>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf || \
    echo '<Directory /var/www/html>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>' >> /etc/apache2/apache2.conf

# Configurar DirectoryIndex para usar index.php
RUN echo 'DirectoryIndex index.php index.html' > /etc/apache2/conf-available/directory-index.conf
RUN a2enconf directory-index

# Copiar archivos de la aplicación
COPY . /var/www/html/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html
RUN chmod 644 /var/www/html/*.php

# Copiar script de inicio
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Exponer puerto
EXPOSE 80

# Usar script de inicio
CMD ["/start.sh"]