FROM php:8.2-apache
COPY ./site/ /var/www/html/
WORKDIR /var/www/html
EXPOSE 80
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN docker-php-ext-install pdo pdo_mysql