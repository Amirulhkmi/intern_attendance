# Use the PHP Apache image (includes web server)
FROM php:8.2-apache

# Enable mysqli extension
RUN docker-php-ext-install mysqli

# Set working directory to the Apache web root
WORKDIR /var/www/html

# Copy your project files into the container
COPY . .

# Railway will provide the PORT environment variable,
# Apache listens on that automatically, so no CMD override is needed.
