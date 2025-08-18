# Use official PHP image
FROM php:8.2-cli

# Copy project files into container
COPY . /app
WORKDIR /app

# Expose port Railway uses
EXPOSE 8080

# Start PHP built-in server on port 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
