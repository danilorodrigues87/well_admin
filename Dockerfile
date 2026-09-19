FROM php:8.1-apache

# Instalar extensões necessárias do PHP e MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar o mod_rewrite do Apache para suporte a reescrita de URLs (.htaccess)
RUN a2enmod rewrite

# Copiar os ficheiros do projeto para a pasta do servidor Apache
COPY . /var/www/html/

# Ajustar permissões da pasta
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80