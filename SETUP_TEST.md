# Hướng dẫn Cài đặt Môi trường Test

Tài liệu này hướng dẫn cách cài đặt và triển khai môi trường test cho ứng dụng Banhang trên server Ubuntu sử dụng Docker và SSL Cloudflare.

## Yêu cầu hệ thống

- Server Ubuntu (đề xuất Ubuntu 20.04 LTS trở lên)
- Quyền sudo
- Tên miền đã được cấu hình trỏ về IP server (test.banhang.ai)
- Chứng chỉ SSL từ Cloudflare

## Cấu trúc thư mục

```
/www/banhang/
├── docker-compose.test.yml
├── .env.test
├── env.frontend.test
├── backend/
│   ├── Dockerfile.test
│   ├── php.ini
│   ├── supervisor.conf
│   ├── scheduler.cron
├── frontend/
│   ├── Dockerfile.test
├── nginx/
│   ├── conf.d/
│   │   ├── test.conf
│   ├── ssl/
│   │   ├── test.banhang.ai.pem  (SSL từ Cloudflare)
│   │   ├── test.banhang.ai.key  (SSL từ Cloudflare)
├── mariadb/
│   ├── initdb.d/
│   │   ├── init_db.sql
├── certbot/
│   ├── conf/
│   ├── www/
```

## Bước 1: Chuẩn bị Server và Cài đặt Docker

1. Cập nhật hệ thống:
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```

2. Chạy script cài đặt Docker:
   ```bash
   sudo chmod +x install_docker_test.sh
   sudo ./install_docker_test.sh
   ```

## Bước 2: Cấu hình SSL Cloudflare

1. Truy cập Cloudflare Dashboard
2. Chọn tên miền của bạn
3. Đi tới SSL/TLS > Origin Server
4. Tạo Certificate mới
5. Sao chép certificate và private key
6. Lưu certificate vào `/www/banhang/nginx/ssl/test.banhang.ai.pem`
7. Lưu private key vào `/www/banhang/nginx/ssl/test.banhang.ai.key`

## Bước 3: Cấu hình biến môi trường

1. Chỉnh sửa file `.env.test` để cấu hình các thông số cho Laravel backend:
   ```bash
   cd /www/banhang
   nano .env.test
   ```

2. Chỉnh sửa file `env.frontend.test` để cấu hình cho Next.js frontend:
   ```bash
   nano env.frontend.test
   ```

## Bước 4: Khởi động dịch vụ

```bash
cd /www/banhang
docker-compose -f docker-compose.test.yml up -d
```

## Bước 5: Thiết lập Backend

```bash
# Tạo Laravel app key
docker-compose -f docker-compose.test.yml exec backend php artisan key:generate --force

# Chạy migrations
docker-compose -f docker-compose.test.yml exec backend php artisan migrate --force

# Tạo storage link
docker-compose -f docker-compose.test.yml exec backend php artisan storage:link

# Tối ưu hóa
docker-compose -f docker-compose.test.yml exec backend php artisan optimize
```

## Cấu hình OAuth

Để cấu hình OAuth login, cập nhật các thông số sau trong file `.env.test`:

```
OAUTH_CLIENT_ID=your_oauth_client_id
OAUTH_CLIENT_SECRET=your_oauth_client_secret
OAUTH_CALLBACK_URL=https://test.banhang.ai/oauth/callback
```

## Truy cập phpMyAdmin

phpMyAdmin đã được cài đặt và cấu hình sẵn để quản lý cơ sở dữ liệu MariaDB:

1. Truy cập phpMyAdmin qua URL: `https://test.banhang.ai/phpmyadmin/`
2. Đăng nhập với thông tin:
   - Username: `banhang` (hoặc `root` nếu bạn muốn quyền cao hơn)
   - Password: Mật khẩu được đặt trong file `.env.test` (DB_PASSWORD)

### Các tính năng có sẵn trong phpMyAdmin:

- Quản lý cơ sở dữ liệu và bảng
- Thực hiện các truy vấn SQL
- Import/Export dữ liệu
- Quản lý người dùng và quyền hạn
- Theo dõi hiệu suất cơ sở dữ liệu

### Bảo mật:

phpMyAdmin chỉ có thể truy cập thông qua HTTPS và được bảo vệ bằng xác thực SQL. Nếu cần thêm lớp bảo mật, bạn có thể:

1. Thêm xác thực HTTP Basic bằng cách sửa cấu hình Nginx
2. Hạn chế truy cập theo IP bằng cách thêm quy tắc tường lửa
3. Sử dụng .htaccess (cần thêm cấu hình cho Apache)

## Triển khai tự động (CI/CD)

Để triển khai ứng dụng tự động, sử dụng script `deploy_test.sh`:

```bash
chmod +x deploy_test.sh
./deploy_test.sh
```

## Khắc phục sự cố

### Kiểm tra logs

```bash
# Log của container backend
docker-compose -f docker-compose.test.yml logs backend

# Log của container frontend
docker-compose -f docker-compose.test.yml logs frontend

# Log của container nginx
docker-compose -f docker-compose.test.yml logs nginx

# Log của container phpMyAdmin
docker-compose -f docker-compose.test.yml logs phpmyadmin

# Log của container MariaDB
docker-compose -f docker-compose.test.yml logs mariadb
```

### Khởi động lại dịch vụ

```bash
docker-compose -f docker-compose.test.yml restart backend
docker-compose -f docker-compose.test.yml restart frontend
docker-compose -f docker-compose.test.yml restart nginx
docker-compose -f docker-compose.test.yml restart phpmyadmin
```

### Rebuild containers

```bash
docker-compose -f docker-compose.test.yml build backend frontend
docker-compose -f docker-compose.test.yml up -d --force-recreate
```

## Security Checklist

- ✅ SSL được cấu hình cho website
- ✅ Tường lửa được bật (UFW hoặc Firewall khác)
- ✅ Chỉ mở các cổng cần thiết (80, 443)
- ✅ Laravel được cấu hình với APP_ENV=test và APP_DEBUG=false
- ✅ Sử dụng mật khẩu mạnh cho database
- ✅ Đảm bảo permission đúng cho các thư mục storage và logs
- ✅ phpMyAdmin được bảo vệ bằng xác thực SQL

## Tài liệu tham khảo

- [Docker Documentation](https://docs.docker.com/)
- [Laravel Documentation](https://laravel.com/docs)
- [Next.js Documentation](https://nextjs.org/docs)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [MariaDB Documentation](https://mariadb.com/kb/en/documentation/)
- [phpMyAdmin Documentation](https://www.phpmyadmin.net/docs/) 