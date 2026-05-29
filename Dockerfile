# Base ultraligera de PHP-FPM con Alpine
FROM php:8.2-fpm-alpine

# 1. Instalamos Nginx y la extensión de base de datos en un solo paso
RUN apk add --no-cache nginx \
    && docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli

# 2. Copiamos la configuración de Nginx al directorio de Alpine
COPY nginx.conf /etc/nginx/http.d/default.conf

# 3. Creamos un script de inicio que arranca PHP en segundo plano y Nginx en primer plano
RUN echo -e "#!/bin/sh\nphp-fpm -D\nnginx -g 'daemon off;'" > /start.sh \
    && chmod +x /start.sh

# 4. Exponemos el puerto 80 y ejecutamos el script al iniciar el contenedor
EXPOSE 80
CMD ["/start.sh"]