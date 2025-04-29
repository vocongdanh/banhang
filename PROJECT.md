# BÁN HÀNG AI - Nền tảng Hỗ trợ Kinh doanh bằng AI

## I. TỔNG QUAN DỰ ÁN

Đây là nền tảng SaaS (Software as a Service) giúp doanh nghiệp phân tích dữ liệu bán hàng và tối ưu hoạt động kinh doanh bằng trí tuệ nhân tạo. Hệ thống tích hợp với các model AI hiện đại từ OpenAI, Gemini, Grok, Meta AI, và DeepSearch để cung cấp giải pháp toàn diện cho doanh nghiệp đang kinh doanh trên các nền tảng thương mại điện tử (Shopee, Tiktok Shop), mạng xã hội (Facebook) và Website.

### Mô hình kinh doanh
- Mô hình subscription (thuê bao) theo gói
- Phân cấp theo số lượng module và số lượng user
- Doanh nghiệp có thể mở rộng theo nhu cầu sử dụng

### Đối tượng khách hàng
- Doanh nghiệp kinh doanh trên nền tảng TMĐT (Shopee, Tiktok Shop)
- Doanh nghiệp bán hàng trên mạng xã hội (Facebook, Instagram)
- Doanh nghiệp có website bán hàng riêng

## II. KIẾN TRÚC KỸ THUẬT

### 2.1 Backend (Laravel 12)
- **Ngôn ngữ**: PHP 8.2+
- **Database**: MySQL
- **API**: RESTful API với Laravel Sanctum authentication
- **Cấu trúc thư mục chính**:
  - `app/Http/Controllers`: Xử lý request từ client
  - `app/Models`: Định nghĩa cấu trúc dữ liệu
  - `app/Services`: Logic nghiệp vụ chính
  - `routes/api.php`: Định nghĩa API endpoints
  - `database/migrations`: Cấu trúc database

### 2.2 Frontend (Next.js 14)
- **Ngôn ngữ**: TypeScript
- **UI Framework**: TailwindCSS + Shadcn UI
- **State Management**: React Context API, React Query
- **Cấu trúc thư mục chính**:
  - `app/`: App Router (pages, routes)
  - `components/`: React components
  - `hooks/`: Custom React hooks
  - `lib/`: Utilities và API wrappers

### 2.3 Mobile App (Flutter)
- **Ngôn ngữ**: Dart
- **State Management**: Riverpod
- **API Integration**: API interface đồng nhất với web platform

### 2.4 Microservices
- **AI Service (Python)**:
  - Xử lý hình ảnh với Qdrant Vector DB
  - Phân tích sản phẩm từ ảnh
  - Trích xuất thông tin từ các định dạng file đặc thù

### 2.5 Cloud Infrastructure
- **Docker containers**: Microservices architecture
- **NGINX**: Load balancing, reverse proxy
- **OpenAI API**: Vector embeddings và LLM integration
- **Qdrant**: Vector DB cho hình ảnh

## III. MODULE CHỨC NĂNG

### 3.1 Quản lý Subscription
- **Planes & Pricing**: Quản lý gói subscription theo module và số lượng user
- **Billing**: Tích hợp thanh toán qua cổng VNPAY, Momo
- **User Management**: Mỗi doanh nghiệp có nhiều user với phân quyền khác nhau

### 3.2 Tích hợp & Import Dữ liệu
- **File Upload**: Import dữ liệu từ file (DOCX, PDF, TXT, CSV, XLSX)
- **Google Drive Sync**: Đồng bộ dữ liệu từ Google Drive
- **Shopee API**: Tự động thu thập dữ liệu bán hàng từ Shopee
- **Tiktok Shop API**: Tự động thu thập dữ liệu bán hàng từ Tiktok Shop
- **Website Crawler**: Thu thập dữ liệu từ website
- **Facebook API**: Đồng bộ dữ liệu từ Facebook Page, Group

### 3.3 Vector Store & AI
- **OpenAI Vector Store**: Lưu trữ văn bản dưới dạng vector embeddings
- **Qdrant Vector DB**: Lưu trữ hình ảnh dưới dạng vectors
- **AI Chatbot**: Hỗ trợ khách hàng, trả lời câu hỏi dựa trên dữ liệu
- **AI Agents**: Phân tích dữ liệu bán hàng, đề xuất chiến lược

### 3.4 Chatbot Đa Nền Tảng
- **Messenger Integration**: Chatbot bán hàng qua Facebook Messenger
- **Zalo Integration**: Chatbot bán hàng qua Zalo
- **Telegram Integration**: Chatbot bán hàng qua Telegram
- **Web Embedded Chat**: Nhúng chatbot vào website

## IV. QUY TRÌNH XỬ LÝ DỮ LIỆU

### 4.1 Thu thập dữ liệu
1. User upload file hoặc kết nối API (Shopee, Tiktok, Facebook)
2. Dữ liệu được lưu vào database và chuẩn hóa
3. Các file được chuyển đổi sang định dạng phù hợp (CSV → TXT)

### 4.2 Xử lý dữ liệu
1. Trích xuất nội dung dựa trên loại file
   - DOCX, PDF: Trích xuất text
   - CSV, XLSX: Chuyển đổi thành cấu trúc dữ liệu
   - Hình ảnh: Phân tích với Computer Vision
2. Tạo vector embeddings với OpenAI API
3. Lưu trữ vector vào Vector Store (OpenAI hoặc Qdrant)

### 4.3 AI Processing
1. Tạo OpenAI Assistant cho mỗi doanh nghiệp
2. Gán Vector Store cho Assistant
3. Assistant phân tích dữ liệu bán hàng và đưa ra insights

### 4.4 Tương tác với khách hàng
1. Chatbot tự động phân tích câu hỏi và context
2. Tìm kiếm thông tin liên quan trong Vector Store
3. Trả lời dựa trên dữ liệu của doanh nghiệp
4. Theo dõi và cải thiện qua thời gian (feedback loop)

## V. API ENDPOINTS

### Authentication
- `POST /api/login`: Đăng nhập hệ thống
- `POST /api/logout`: Đăng xuất
- `GET /api/user`: Lấy thông tin user hiện tại

### File Management
- `POST /api/upload`: Upload file
- `GET /api/files`: Danh sách file đã upload
- `GET /api/files/{id}/download`: Tải xuống file
- `DELETE /api/files/{id}`: Xóa file

### Data Import
- `POST /api/import`: Import dữ liệu từ file
- `POST /api/connect/shopee`: Kết nối Shopee API
- `POST /api/connect/tiktok`: Kết nối Tiktok Shop API
- `POST /api/connect/facebook`: Kết nối Facebook API

### AI & Vector Search
- `POST /api/vector-search`: Tìm kiếm trong Vector Store
- `POST /api/chat`: Tương tác với AI Assistant
- `POST /api/generate-report`: Tạo báo cáo phân tích

## VI. PHÁT TRIỂN & TRIỂN KHAI

### 6.1 Môi trường phát triển
```bash
# Backend (Laravel)
cd backend
composer install
php artisan migrate
php artisan serve --port=8001

# Frontend (Next.js)
cd frontend
npm install
npm run dev

# Mobile (Flutter)
cd mobile
flutter pub get
flutter run
```

### 6.2 Cài đặt cho Production
```bash
# Docker Compose
docker-compose -f docker-compose.prod.yml up -d
```

## VII. QUẢN LÝ DỮ LIỆU

### 7.1 Vector Store Management
- **Text Processing**: Tự động chuyển đổi CSV sang TXT trước khi upload
- **Batch Processing**: Upload nhiều file cùng lúc
- **Metadata**: Lưu trữ metadata để tối ưu tìm kiếm
- **Namespace**: Phân tách dữ liệu theo doanh nghiệp và user

### 7.2 Lưu ý quan trọng
- OpenAI Vector Store không hỗ trợ CSV trực tiếp, cần chuyển sang TXT
- PDF, DOCX được hỗ trợ natively
- Mỗi doanh nghiệp có Vector Store riêng để đảm bảo tính bảo mật
- Dữ liệu không được chia sẻ giữa các doanh nghiệp khác nhau 