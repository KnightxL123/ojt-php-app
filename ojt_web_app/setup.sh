#!/bin/bash
echo "Setting up PostgreSQL support..."

# Install PostgreSQL extensions
apt-get update
apt-get install -y libpq-dev
docker-php-ext-install pdo pdo_pgsql pgsql

echo "PostgreSQL extensions installed"
