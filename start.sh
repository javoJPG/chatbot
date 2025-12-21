#!/bin/bash

# Obtener puerto de Railway o usar 80 por defecto
PORT=${PORT:-80}

# Asegurar que solo mpm_prefork esté habilitado (última verificación antes de iniciar)
echo "Verificando configuración de MPMs..."
rm -f /etc/apache2/mods-enabled/mpm_event.load 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_worker.load 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_event.conf 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_worker.conf 2>/dev/null || true

# Configurar Apache para usar el puerto dinámico
echo "Configurando Apache para puerto $PORT"

# Actualizar ports.conf
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf

# Actualizar virtual host
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Verificar configuración antes de iniciar
echo "Verificando configuración de Apache..."
apache2ctl configtest

# Iniciar Apache en primer plano
echo "Iniciando Apache en puerto $PORT"
exec apache2ctl -D FOREGROUND

