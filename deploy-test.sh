#!/bin/bash

# Script to deploy test environment for banhang.ai
# Usage: ./deploy-test.sh

echo "Deploying test environment at test.banhang.ai..."

# Check if we're in the right directory
if [ ! -f "docker-compose.test.yml" ]; then
    echo "Error: docker-compose.test.yml not found. Make sure you're in the project root directory."
    exit 1
fi

# Create directory for SSL certificates if it doesn't exist
mkdir -p nginx/ssl

# Check if SSL certificates exist, if not create self-signed certificates
if [ ! -f "nginx/ssl/test.banhang.ai.crt" ] || [ ! -f "nginx/ssl/test.banhang.ai.key" ]; then
    echo "SSL certificates not found. Creating self-signed certificates..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout nginx/ssl/test.banhang.ai.key \
        -out nginx/ssl/test.banhang.ai.crt \
        -subj "/C=VN/ST=HoChiMinh/L=HoChiMinh/O=BanHang/OU=IT/CN=test.banhang.ai"
    
    echo "Self-signed certificates created."
fi

# Copy environment files
echo "Setting up environment files..."
cp env.test backend/.env
cp env.frontend.test frontend/.env

# Build and start containers
echo "Building and starting containers..."
docker-compose -f docker-compose.test.yml down
docker-compose -f docker-compose.test.yml build
docker-compose -f docker-compose.test.yml up -d

# Wait for containers to start
echo "Waiting for containers to start..."
sleep 10

# Generate Laravel application key
echo "Generating Laravel application key..."
docker-compose -f docker-compose.test.yml exec backend php artisan key:generate

# Run database migrations
echo "Running database migrations..."
docker-compose -f docker-compose.test.yml exec backend php artisan migrate

# Create symbolic link for storage
echo "Creating storage symbolic link..."
docker-compose -f docker-compose.test.yml exec backend php artisan storage:link

# Optimize Laravel application
echo "Optimizing Laravel application..."
docker-compose -f docker-compose.test.yml exec backend php artisan config:cache
docker-compose -f docker-compose.test.yml exec backend php artisan route:cache

echo "Test environment deployed successfully!"
echo "Access the application at https://test.banhang.ai"
echo "PhpMyAdmin is available at http://test.banhang.ai:8080"
echo ""
echo "Don't forget to add test.banhang.ai to your hosts file or DNS records."
echo "For local testing, add this to /etc/hosts: 127.0.0.1 test.banhang.ai" 