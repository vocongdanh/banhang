#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Setting up Test Environment for banhang.ai ===${NC}"

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then
  echo -e "${YELLOW}Please run as root or with sudo${NC}"
  exit 1
fi

# Create necessary directories
mkdir -p data/certbot/conf data/certbot/www
mkdir -p nginx/logs
mkdir -p nginx/ssl

# Create SSL directory for Cloudflare certificates
echo -e "${YELLOW}Setting up SSL certificates...${NC}"

# Check if SSL certs already exist, otherwise create placeholders
if [ ! -f "nginx/ssl/test.banhang.ai.pem" ] || [ ! -f "nginx/ssl/test.banhang.ai.key" ]; then
  echo -e "${YELLOW}Please place your Cloudflare Origin SSL certificates in the nginx/ssl directory:${NC}"
  echo -e "${YELLOW}- nginx/ssl/test.banhang.ai.pem (certificate)${NC}"
  echo -e "${YELLOW}- nginx/ssl/test.banhang.ai.key (private key)${NC}"
  
  # Create empty files as placeholders
  touch nginx/ssl/test.banhang.ai.pem
  touch nginx/ssl/test.banhang.ai.key
  
  read -p "Press any key to continue once you've added your certificates..." -n1 -s
  echo ""
fi

# Generate Laravel App Key if not set
if grep -q "base64:YOUR_KEY_HERE" env.test; then
  echo -e "${YELLOW}Generating Laravel App Key...${NC}"
  # Generate a random key
  APP_KEY=$(openssl rand -base64 32)
  # Replace placeholder with the new key
  sed -i "s|APP_KEY=base64:YOUR_KEY_HERE|APP_KEY=base64:$APP_KEY|" env.test
fi

echo -e "${GREEN}Starting Docker Compose services...${NC}"
docker-compose -f docker-compose.test.yml down
docker-compose -f docker-compose.test.yml up -d

echo -e "${YELLOW}Waiting for services to start up...${NC}"
sleep 10

echo -e "${GREEN}Setting up Laravel backend...${NC}"
docker-compose -f docker-compose.test.yml exec backend composer install
docker-compose -f docker-compose.test.yml exec backend php artisan key:generate --force
docker-compose -f docker-compose.test.yml exec backend php artisan migrate:fresh --seed --force
docker-compose -f docker-compose.test.yml exec backend php artisan storage:link
docker-compose -f docker-compose.test.yml exec backend php artisan config:cache
docker-compose -f docker-compose.test.yml exec backend php artisan route:cache
docker-compose -f docker-compose.test.yml exec backend php artisan optimize

echo -e "${GREEN}=== Test Environment Setup Complete ===${NC}"
echo -e "${GREEN}Your test environment is now available at:${NC}"
echo -e "${YELLOW}https://test.banhang.ai${NC}"
echo -e "${GREEN}PhpMyAdmin:${NC} ${YELLOW}https://test.banhang.ai/phpmyadmin${NC}"
echo ""
echo -e "${RED}IMPORTANT:${NC}"
echo -e "- Make sure your DNS points to this server"
echo -e "- Update OAuth credentials in env.test and env.frontend.test files"
echo -e "- Add your SSL certificates to nginx/ssl/ directory"

# Display Docker container status
echo -e "${GREEN}Current Docker container status:${NC}"
docker-compose -f docker-compose.test.yml ps 