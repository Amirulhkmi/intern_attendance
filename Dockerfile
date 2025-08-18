FROM php:8.2-apache

# Enable mysqli extension
RUN docker-php-ext-install mysqli

# Set working directory
WORKDIR /var/www/html
COPY . .

# Tell Apache to listen on the port provided by the Railway runtime
ENV PORT=80
RUN sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

# Start Apache in the foreground
CMD ["apache2-foreground"]
