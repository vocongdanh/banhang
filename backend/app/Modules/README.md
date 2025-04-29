# Laravel Modules Structure

## Core Modules

### 1. Authentication
- User authentication and authorization
- Role and permission management
- API token management

### 2. Business
- Company/Organization management
- Department management
- User management within organization

### 3. Integration
- E-commerce platform integration (Shopee, Tiktok)
- Google Drive integration
- Database connection management
- Web crawler management

### 4. AI
- Chatbot management
- AI Agent management
- Vector store management
- Model configuration

### 5. Data
- Data synchronization
- Data processing
- Data storage management

### 6. Communication
- Messaging platform integration (Messenger, Zalo, Telegram)
- Webhook management
- Notification system

## Module Structure

Each module follows this structure:
```
ModuleName/
├── Config/              # Module configuration
├── Console/             # Artisan commands
├── Controllers/         # HTTP controllers
├── Events/             # Event classes
├── Exceptions/         # Custom exceptions
├── Http/               # HTTP related classes
│   ├── Middleware/     # Custom middleware
│   ├── Requests/       # Form requests
│   └── Resources/      # API resources
├── Jobs/               # Queue jobs
├── Listeners/          # Event listeners
├── Models/             # Eloquent models
├── Providers/          # Service providers
├── Repositories/       # Repository classes
├── Services/           # Business logic services
└── Tests/              # Test classes
``` 