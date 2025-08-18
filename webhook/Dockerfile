FROM php:8.2-apache

# Enable Apache rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy webhook and minimal app files
COPY webhook/ /var/www/html/webhook/
COPY webhook/.htaccess /var/www/html/.htaccess

# Healthcheck endpoint
RUN echo "OK" > /var/www/html/healthz

EXPOSE 80

CMD ["apache2-foreground"]

