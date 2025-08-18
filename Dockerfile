# Use official PHP image
FROM php:8.2-cli

# Copy project files into container
COPY . /app
WORKDIR /app

# Expose port 80
EXPOSE 80

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:80", "-t", "."]
