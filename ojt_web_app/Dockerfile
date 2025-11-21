# Use official PHP CLI image
FROM php:8.2-cli

# Install PostgreSQL extensions instead of MySQL
RUN apt-get update && apt-get install -y \
        libpq-dev \
        git \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app

# Expose port for Render
EXPOSE 10000

# Start PHP built-in server pointing to ojt_web_app
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t ojt_web_app"]
