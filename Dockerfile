# Use official PHP + Apache base image
FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Copy the application code to Apache document root
COPY . /var/www/html

# Set proper file permissions
RUN chown -R www-data:www-data /var/www/html

# Enable the Apache rewrite module (optional but often needed for routing)
RUN a2enmod rewrite

# Render expects the container to listen on port 80 (Apache does this by default)
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
