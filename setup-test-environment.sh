#!/bin/bash

# Script to setup test environment with Certbot SSL certificates
# Usage: ./setup-test-environment.sh yourdomain.com email@example.com

# Check if domain name is provided
if [ -z "$1" ]; then
    echo "Error: Domain name is required"
    echo "Usage: ./setup-test-environment.sh yourdomain.com email@example.com"
    exit 1
fi

# Check if email is provided
if [ -z "$2" ]; then
    echo "Error: Email is required for Let's Encrypt"
    echo "Usage: ./setup-test-environment.sh yourdomain.com email@example.com"
    exit 1
fi

DOMAIN=$1
EMAIL=$2
TEST_DOMAIN="test.$DOMAIN"

echo "Setting up test environment for $TEST_DOMAIN..."

# Create directories for Certbot
mkdir -p data/certbot/conf
mkdir -p data/certbot/www

# Create directories for Nginx logs
mkdir -p nginx/logs

# Create dummy certificates for initial Nginx startup
mkdir -p nginx/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout nginx/ssl/$TEST_DOMAIN.key \
    -out nginx/ssl/$TEST_DOMAIN.crt \
    -subj "/C=VN/ST=HoChiMinh/L=HoChiMinh/O=BanHang/OU=IT/CN=$TEST_DOMAIN"

# Update environment variables
sed -i '' "s/APP_URL=.*/APP_URL=https:\/\/$TEST_DOMAIN/" env.test
sed -i '' "s/FACEBOOK_REDIRECT_URI=.*/FACEBOOK_REDIRECT_URI=https:\/\/$TEST_DOMAIN\/oauth\/facebook\/callback/" env.test
sed -i '' "s/GOOGLE_REDIRECT_URI=.*/GOOGLE_REDIRECT_URI=https:\/\/$TEST_DOMAIN\/oauth\/google\/callback/" env.test

# Update frontend environment
cat > env.frontend.test << EOF
NEXT_PUBLIC_API_URL=https://$TEST_DOMAIN/api
NEXT_PUBLIC_BACKEND_URL=https://$TEST_DOMAIN
NEXT_PUBLIC_APP_ENV=testing
EOF

# Update Nginx configuration
sed -i '' "s/server_name .*/server_name $TEST_DOMAIN;/g" nginx/conf.d/test.conf

# Deploy with docker-compose
echo "Starting containers with dummy SSL..."
docker-compose -f docker-compose.test.yml down
docker-compose -f docker-compose.test.yml up -d

echo "Waiting for Nginx to start..."
sleep 10

# Request real certificates from Let's Encrypt
echo "Requesting Let's Encrypt certificates for $TEST_DOMAIN..."
docker-compose -f docker-compose.test.yml run --rm certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email $EMAIL \
    --agree-tos \
    --no-eff-email \
    --force-renewal \
    -d $TEST_DOMAIN

# Reload Nginx to use the new certificates
echo "Reloading Nginx to use the new certificates..."
docker-compose -f docker-compose.test.yml exec nginx nginx -s reload

echo "Test environment setup completed for $TEST_DOMAIN."
echo "Visit https://$TEST_DOMAIN to access your application."
echo "Make sure to update your DNS settings to point $TEST_DOMAIN to your server IP." 