#!/bin/bash

# Script để cài đặt Docker và triển khai môi trường test trên server Ubuntu
# Dùng cho Ubuntu với SSL Cloudflare và đường dẫn backend là /www/banhang/backend

set -e

# Màu sắc cho output
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}Bắt đầu cài đặt môi trường test cho Banhang...${NC}"

# Kiểm tra xem script có chạy với quyền sudo không
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}Script này cần được chạy với quyền sudo${NC}" 
   exit 1
fi

# Cập nhật hệ thống
echo -e "${YELLOW}Cập nhật hệ thống...${NC}"
apt-get update
apt-get upgrade -y

# Cài đặt các gói cần thiết
echo -e "${YELLOW}Cài đặt các gói phụ thuộc...${NC}"
apt-get install -y \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    software-properties-common \
    git

# Cài đặt Docker nếu chưa cài
if ! command -v docker &> /dev/null; then
    echo -e "${YELLOW}Cài đặt Docker...${NC}"
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io
fi

# Cài đặt Docker Compose nếu chưa cài
if ! command -v docker-compose &> /dev/null; then
    echo -e "${YELLOW}Cài đặt Docker Compose...${NC}"
    LATEST_COMPOSE=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep -oP '"tag_name": "\K(.*)(?=")')
    curl -L "https://github.com/docker/compose/releases/download/${LATEST_COMPOSE}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
fi

# Tạo thư mục dự án nếu chưa tồn tại
echo -e "${YELLOW}Tạo cấu trúc thư mục dự án...${NC}"
mkdir -p /www/banhang/{backend,frontend,nginx/conf.d,nginx/ssl,mariadb/initdb.d,certbot/conf,certbot/www}

# Di chuyển đến thư mục dự án
cd /www/banhang

# Tạo thư mục MariaDB initdb
mkdir -p mariadb/initdb.d

# Tạo file SQL khởi tạo database
cat > mariadb/initdb.d/init_db.sql << 'EOF'
CREATE DATABASE IF NOT EXISTS banhang_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON banhang_test.* TO 'banhang'@'%';
FLUSH PRIVILEGES;
EOF

echo -e "${YELLOW}Kiểm tra và tạo thư mục SSL...${NC}"
mkdir -p nginx/ssl

echo -e "${YELLOW}Vui lòng sao chép chứng chỉ SSL từ Cloudflare vào thư mục nginx/ssl:${NC}"
echo -e "${YELLOW}1. Đặt chứng chỉ gốc vào ${RED}nginx/ssl/test.banhang.ai.pem${NC}"
echo -e "${YELLOW}2. Đặt khóa riêng tư vào ${RED}nginx/ssl/test.banhang.ai.key${NC}"

# Tạo tệp .env.test từ mẫu
if [ ! -f env.test ]; then
    echo -e "${YELLOW}Tạo tệp env.test...${NC}"
    cp -n env.test .env.test || echo -e "${RED}Không thể tạo tệp .env.test${NC}"
    echo -e "${YELLOW}Vui lòng cập nhật mật khẩu và các giá trị khác trong tệp .env.test${NC}"
fi

# Tạo tệp env.frontend.test từ mẫu
if [ ! -f env.frontend.test ]; then
    echo -e "${YELLOW}Tạo tệp env.frontend.test...${NC}"
    cp -n env.frontend.test env.frontend.test || echo -e "${RED}Không thể tạo tệp env.frontend.test${NC}"
    echo -e "${YELLOW}Vui lòng cập nhật các giá trị trong tệp env.frontend.test${NC}"
fi

# Kiểm tra cấu hình nginx
if [ ! -f nginx/conf.d/test.conf ]; then
    echo -e "${RED}File cấu hình Nginx không tồn tại.${NC}"
    exit 1
fi

# Kiểm tra SSL từ Cloudflare
if [ ! -f nginx/ssl/test.banhang.ai.pem ] || [ ! -f nginx/ssl/test.banhang.ai.key ]; then
    echo -e "${RED}Các tập tin SSL từ Cloudflare chưa được cài đặt${NC}"
    echo -e "${YELLOW}Vui lòng đặt các tập tin SSL vào đúng vị trí và chạy lại script${NC}"
    exit 1
fi

# Khởi động Docker Compose
echo -e "${YELLOW}Khởi động các dịch vụ Docker...${NC}"
docker-compose -f docker-compose.test.yml up -d

# Generate Laravel app key
echo -e "${YELLOW}Tạo Laravel app key...${NC}"
docker-compose -f docker-compose.test.yml exec backend php artisan key:generate --force

# Chạy migration
echo -e "${YELLOW}Chạy migration Laravel...${NC}"
docker-compose -f docker-compose.test.yml exec backend php artisan migrate --force

# Tạo storage link
echo -e "${YELLOW}Tạo storage link...${NC}"
docker-compose -f docker-compose.test.yml exec backend php artisan storage:link

# Optimize Laravel
echo -e "${YELLOW}Optimize Laravel...${NC}"
docker-compose -f docker-compose.test.yml exec backend php artisan optimize

echo -e "${GREEN}Cài đặt hoàn tất!${NC}"
echo -e "${GREEN}Ứng dụng đã được triển khai tại https://test.banhang.ai${NC}"
echo -e "${YELLOW}Vui lòng kiểm tra các thiết lập bảo mật và cấu hình Cloudflare SSL cho tên miền của bạn.${NC}"

# Hiển thị trạng thái các container
echo -e "${YELLOW}Trạng thái các container:${NC}"
docker-compose -f docker-compose.test.yml ps 