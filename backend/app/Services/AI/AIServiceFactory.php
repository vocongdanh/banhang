<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Exception;

class AIServiceFactory
{
    /**
     * Tạo một instance của AI service dựa trên loại được cấu hình
     *
     * @param string|null $type Loại service (mặc định lấy từ cấu hình)
     * @return AIServiceInterface
     *
     * @throws Exception Nếu loại service không hợp lệ
     */
    public static function create(?string $type = null): AIServiceInterface
    {
        // Lấy loại service từ cấu hình nếu không được chỉ định
        $serviceType = $type ?? config('ai.service_type', 'openai');
        
        // Map loại service đến lớp cụ thể
        $serviceMap = [
            'openai' => OpenAIService::class,
            // Thêm các service khác ở đây khi cần, ví dụ:
            // 'anthropic' => AnthropicService::class,
            // 'gemini' => GeminiService::class,
        ];
        
        if (!array_key_exists($serviceType, $serviceMap)) {
            Log::error("Invalid AI service type: {$serviceType}");
            throw new Exception("Invalid AI service type: {$serviceType}");
        }
        
        // Khởi tạo service từ container để hỗ trợ dependency injection
        return App::make($serviceMap[$serviceType]);
    }
} 