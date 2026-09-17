FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx mysql-client libpng-dev libjpeg-turbo-dev freetype-dev gettext-dev icu-dev libxml2-dev

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY nginx.conf /etc/nginx/http.d/default.conf

COPY . /www/wwwroot/email_service_portal

RUN chown -R www-data:www-data /www/wwwroot/email_service_portal

EXPOSE 80

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
