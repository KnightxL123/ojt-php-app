# Use official PHP image
FROM php:8.2-cli

# Copy your app
COPY . /app
WORKDIR /app

# Expose port (Render will assign $PORT)
EXPOSE 10000

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:10000"]
