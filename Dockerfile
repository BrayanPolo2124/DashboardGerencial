# Usamos una imagen oficial de PHP con Apache integrada
FROM php:8.1-apache

# Instalamos extensiones necesarias de MySQL/PDO para conectar a la base de datos sihos
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitamos mod_rewrite de Apache por si tu MVC usa URLs amigables o el .htaccess
RUN a2enmod rewrite

# Copiamos todo el código de tu proyecto al directorio web del servidor Apache
COPY . /var/www/html/

# Ajustamos permisos de la carpeta web
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Puerto por defecto que Render asigna automáticamente
EXPOSE 80