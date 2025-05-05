#!/bin/bash

# Script triển khai ứng dụng trên môi trường test
# Sử dụng cho tự động hóa triển khai (CI/CD)

set -e

# Màu sắc cho output
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}Bắt đầu triển khai ứng dụng trên môi trường test...${NC}"

# Di chuyển đến thư mục dự án
cd /www/banhang

# Pull code mới từ repository
echo -e "${YELLOW}Pull code mới từ repository...${NC}"
cd /www/banhang/backend
git pull origin main

cd /www/banhang/frontend
git pull origin main

cd /www/banhang

# Rebuild và restart containers
echo -e "${YELLOW}Rebuild và restart containers...${NC}"
docker-compose -f docker-compose.test.yml build backend frontend
docker-compose -f docker-compose.test.yml up -d --force-recreate backend frontend

# Chạy migration
echo -e "${YELLOW}Chạy migration Laravel...${NC}"
docker-compose -f docker-compose.test.yml exec -T backend php artisan migrate --force

# Clear cache Laravel
echo -e "${YELLOW}Clear cache Laravel...${NC}"
docker-compose -f docker-compose.test.yml exec -T backend php artisan config:clear
docker-compose -f docker-compose.test.yml exec -T backend php artisan cache:clear
docker-compose -f docker-compose.test.yml exec -T backend php artisan route:clear
docker-compose -f docker-compose.test.yml exec -T backend php artisan view:clear

# Optimize Laravel
echo -e "${YELLOW}Optimize Laravel...${NC}"
docker-compose -f docker-compose.test.yml exec -T backend php artisan optimize

# Khởi động lại queue worker
echo -e "${YELLOW}Restart queue worker...${NC}"
docker-compose -f docker-compose.test.yml exec -T backend php artisan queue:restart

echo -e "${GREEN}Triển khai hoàn tất!${NC}"
echo -e "${GREEN}Ứng dụng đã được cập nhật tại https://test.banhang.ai${NC}"

# Hiển thị trạng thái các container
echo -e "${YELLOW}Trạng thái các container:${NC}"
docker-compose -f docker-compose.test.yml ps 