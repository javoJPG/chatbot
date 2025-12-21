#!/bin/bash

# Obtener puerto de Railway o usar 80 por defecto
PORT=${PORT:-80}

# Configurar Apache para usar el puerto dinámico
echo "Configurando Apache para puerto $PORT"

# Actualizar ports.conf
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf

# Actualizar virtual host
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Iniciar Apache en primer plano
echo "Iniciando Apache en puerto $PORT"
exec apache2ctl -D FOREGROUND

