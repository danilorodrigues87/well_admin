FROM php:8.1-apache

# Instalar dependências do sistema (incluindo pacotes de imagem para o GD)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli zip gd

# Instalar o Composer dentro do container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Copiar arquivos do projeto
COPY . /var/www/html/

# Definir o diretório de trabalho
WORKDIR /var/www/html/

# Rodar o composer install para gerar a pasta vendor/
RUN composer install --no-interaction --optimize-autoloader --ignore-platform-reqs

# Definir permissões de acesso aos arquivos
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
