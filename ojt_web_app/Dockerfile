# Use official PHP CLI image
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Copy all project files into container
COPY . /app

# Expose port (Render will assign $PORT)
EXPOSE 10000

# Start PHP built-in server serving ojt_web_app folder
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t ojt_web_app"]
