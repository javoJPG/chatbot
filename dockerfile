FROM php:8.2-apache

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Copiar archivos de la aplicación
COPY . /var/www/html/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Exponer puerto 80
EXPOSE 80

# Las variables de entorno se pueden pasar con -e o --env-file
# Ejemplo: docker run -e GREEN_ID=xxx -e GREEN_TOKEN=yyy ...
