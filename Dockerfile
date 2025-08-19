# Use official PHP + Apache base image
FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy the application code to Apache document root
COPY . /var/www/html

# Set proper file permissions
RUN chown -R www-data:www-data /var/www/html

# Enable the Apache rewrite module (optional but often needed for routing)
RUN a2enmod rewrite

# Tell Railway which port to use
ENV PORT=8080
EXPOSE 8080

# Start Apache
CMD ["apache2-foreground"]
