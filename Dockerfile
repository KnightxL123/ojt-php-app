# Use official PHP CLI image
FROM php:8.2-cli

# Set working directory inside container
WORKDIR /app

# Copy all project files into container
COPY . /app

# Expose port for Render (Render provides $PORT environment variable)
EXPOSE 10000

# Set the start command to run PHP built-in server
# Use $PORT environment variable on Render
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000}"]
