<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for various AI services
    | that can be used by the application. The service_type determines
    | which AI service implementation will be used.
    |
    */

    // Loại service AI sẽ được sử dụng (openai, anthropic, gemini, v.v.)
    'service_type' => env('AI_SERVICE_TYPE', 'openai'),

    // Cấu hình cho mỗi loại service
    'services' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4-turbo'),
        ],
        
        // Cấu hình cho các service khác có thể thêm ở đây trong tương lai
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-opus'),
        ],
        
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-pro'),
        ],
    ],
    
    // Cấu hình mặc định cho business mới
    'defaults' => [
        'assistant' => [
            'name' => 'Business Assistant',
            'instructions' => 'You are a helpful AI assistant for a business.',
            'model' => 'gpt-4-turbo',
        ],
        'vector_store' => [
            'name' => 'Business Vector Store',
            'description' => 'Vector store for business data',
        ],
    ],
]; 