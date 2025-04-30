<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use App\Models\AiAgent;
use App\Models\AiConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\VectorStoreService;
use App\Models\Business;
use Illuminate\Support\Facades\Log;
use App\Services\AI\AIServiceFactory;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;

class AiMessageController extends Controller
{
    protected $vectorStoreService;

    public function __construct(VectorStoreService $vectorStoreService)
    {
        $this->vectorStoreService = $vectorStoreService;
    }

    /**
     * Store a new message in a conversation
     */
    public function store(Request $request, AiConversation $conversation)
    {
        // Check if user has access to the conversation
        if (Auth::id() !== $conversation->user_id && !$this->isUserAdminOfBusiness($conversation->business_id)) {
            return response()->json(['message' => 'Unauthorized access to conversation'], 403);
        }

        $request->validate([
            'content' => 'required|string',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:pdf,doc,docx,txt,jpg,jpeg,png|max:10240',
        ]);

        // Create user message
        $message = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'role' => 'user',
            'content' => $request->content,
            'is_read' => true,
        ]);

        // Handle file uploads
        if ($request->hasFile('files')) {
            $fileUrls = [];
            $fileData = [];
            
            foreach ($request->file('files') as $file) {
                $path = $file->store('ai_conversation_files/' . $conversation->id, 'public');
                $fileUrl = url('storage/' . $path);
                $fileUrls[] = $fileUrl;
                
                // Xử lý nội dung file theo loại
                $fileContent = $this->processFileContent($file);
                if ($fileContent) {
                    $fileData[] = [
                        'url' => $fileUrl,
                        'name' => $file->getClientOriginalName(),
                        'content' => $fileContent,
                    ];
                }
            }
            
            // Update message with file URLs
            $message->files = $fileUrls;
            $message->save();
            
            // Xử lý lưu vào vector store nếu có dữ liệu
            if (!empty($fileData)) {
                // Lưu ý: Chúng ta tạm thời bỏ qua việc lưu vào vector store
                // Khi VectorStoreService được cập nhật, có thể bỏ comment và sử dụng phương thức tương ứng
                /*
                foreach ($fileData as $data) {
                    $this->vectorStoreService->processVectorData(
                        $data['content'],
                        [
                            'conversation_id' => $conversation->id,
                            'message_id' => $message->id,
                            'file_name' => $data['name'],
                            'file_url' => $data['url'],
                            'business_id' => $conversation->business_id,
                            'user_id' => Auth::id(),
                        ]
                    );
                }
                */
                
                // Log thông tin file để debug
                \Illuminate\Support\Facades\Log::info('Files uploaded in conversation', [
                    'conversation_id' => $conversation->id,
                    'file_count' => count($fileData),
                    'user_id' => Auth::id()
                ]);
            }
        }

        // Now generate AI response
        $agent = AiAgent::findOrFail($conversation->ai_agent_id);

        // Here, you would call OpenAI or your AI service to get a response
        // This is a placeholder - you'll need to implement actual AI integration
        $aiResponse = $this->getAIResponse($agent, $conversation, $request->content, $fileData ?? []);

        // Create AI message
        $aiMessage = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => null,
            'role' => 'assistant',
            'content' => $aiResponse,
            'is_read' => false,
        ]);

        // Update conversation's last_message_at
        $conversation->last_message_at = now();
        $conversation->save();

        return response()->json([
            'user_message' => $message,
            'ai_message' => $aiMessage
        ], 201);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request, AiConversation $conversation)
    {
        // Check if user has access to the conversation
        if (Auth::id() !== $conversation->user_id && !$this->isUserAdminOfBusiness($conversation->business_id)) {
            return response()->json(['message' => 'Unauthorized access to conversation'], 403);
        }

        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:ai_messages,id',
        ]);

        // Only mark messages that belong to this conversation
        AiMessage::whereIn('id', $request->message_ids)
            ->where('ai_conversation_id', $conversation->id)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Messages marked as read'], 200);
    }

    /**
     * Delete a message
     */
    public function destroy(AiMessage $message)
    {
        $conversation = $message->conversation;
        
        // Check if user has access to delete the message
        if (Auth::id() !== $conversation->user_id && !$this->isUserAdminOfBusiness($conversation->business_id)) {
            return response()->json(['message' => 'Unauthorized to delete this message'], 403);
        }

        // Only allow deletion of user's own messages or if admin
        if ($message->role === 'user' && $message->user_id !== Auth::id() && !$this->isUserAdminOfBusiness($conversation->business_id)) {
            return response()->json(['message' => 'Unauthorized to delete this message'], 403);
        }

        $message->delete();

        return response()->json(['message' => 'Message deleted successfully'], 200);
    }

    /**
     * Kiểm tra xem người dùng hiện tại có phải là admin của business hay không
     */
    private function isUserAdminOfBusiness($businessId)
    {
        $user = Auth::user();
        if (!$user) return false;
        
        return Business::where('id', $businessId)
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('users', function ($q) use ($user) {
                        $q->where('users.id', $user->id)
                            ->whereIn('role', ['admin', 'owner']);
                    });
            })
            ->exists();
    }

    /**
     * Xử lý nội dung file theo loại file
     */
    private function processFileContent($file)
    {
        $extension = $file->getClientOriginalExtension();
        $content = '';
        
        // Đơn giản chỉ đọc file text
        if (in_array($extension, ['txt'])) {
            $content = file_get_contents($file->getRealPath());
        }
        
        // Phần xử lý cho các loại file khác có thể thêm sau
        
        return $content;
    }

    /**
     * Call AI service to get a response
     */
    private function getAIResponse(AiAgent $agent, AiConversation $conversation, string $userMessage, array $files = [])
    {
        try {
            // Lấy business và assistant ID từ business
            $business = Business::findOrFail($conversation->business_id);
            $assistantId = $business->assistant_id;
            
            // Nếu business chưa có assistant_id, tạo một assistant mới
            if (!$assistantId) {
                $aiService = AIServiceFactory::create();
                
                // Tạo assistant mới với thông tin từ agent
                $assistantId = $aiService->createAssistant([
                    'name' => $agent->name,
                    'instructions' => $agent->system_prompt ?: "Bạn là {$agent->name}, một trợ lý AI hữu ích.",
                    'model' => $agent->model ?: 'gpt-4-turbo',
                    'tools' => [
                        ['type' => 'retrieval']
                    ],
                    'metadata' => [
                        'business_id' => $business->id,
                        'agent_id' => $agent->id
                    ]
                ]);
                
                // Lưu assistant_id vào business
                $business->assistant_id = $assistantId;
                $business->save();
                
                Log::info("Created new assistant for business", [
                    'business_id' => $business->id,
                    'assistant_id' => $assistantId
                ]);
            }
            
            // Chuẩn bị các file đính kèm
            $fileIds = [];
            if (!empty($files)) {
                // Xử lý files nếu cần
                // Xữ lý tải lên files cho assistant có thể làm sau
            }
            
            // Gọi service để gửi tin nhắn và nhận phản hồi
            $aiService = AIServiceFactory::create();
            $response = $aiService->sendMessage(
                $assistantId,
                $userMessage,
                $fileIds,
                [
                    'conversation_id' => (string) $conversation->id,
                    'business_id' => (string) $business->id
                ]
            );
            
            // Lưu thông tin thread vào conversation nếu cần
            if (!empty($response['thread_id']) && !$conversation->metadata) {
                $conversation->metadata = ['thread_id' => $response['thread_id']];
                $conversation->save();
            }
            
            return $response['content'];
        } catch (\Exception $e) {
            Log::error('Error getting AI response', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Trả về thông báo lỗi an toàn nếu gặp vấn đề
            return "Xin lỗi, tôi đang gặp một số vấn đề kỹ thuật. Vui lòng thử lại sau.";
        }
    }

    /**
     * Stream AI response for a conversation using a POST request first
     */
    public function streamPrepare(Request $request, AiConversation $conversation)
    {
        // Check if user has access to the conversation
        if (Auth::id() !== $conversation->user_id && !$this->isUserAdminOfBusiness($conversation->business_id)) {
            return response()->json(['message' => 'Unauthorized access to conversation'], 403);
        }

        $request->validate([
            'content' => 'required|string',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:pdf,doc,docx,txt,jpg,jpeg,png|max:10240',
        ]);

        // Create user message
        $message = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'role' => 'user',
            'content' => $request->content,
            'is_read' => true,
        ]);

        // Initialize fileData as empty array
        $fileData = [];
        
        // Handle file uploads
        if ($request->hasFile('files')) {
            $fileUrls = [];
            
            foreach ($request->file('files') as $file) {
                $path = $file->store('ai_conversation_files/' . $conversation->id, 'public');
                $fileUrl = url('storage/' . $path);
                $fileUrls[] = $fileUrl;
                
                // Xử lý nội dung file theo loại
                $fileContent = $this->processFileContent($file);
                if ($fileContent) {
                    $fileData[] = [
                        'url' => $fileUrl,
                        'name' => $file->getClientOriginalName(),
                        'content' => $fileContent,
                    ];
                }
            }
            
            // Update message with file URLs
            $message->files = $fileUrls;
            $message->save();
        }

        // Generate a temporary stream token and store it in the session or cache
        $streamToken = md5(uniqid(Auth::id() . $conversation->id, true));
        
        // Store token in cache for 10 minutes
        Cache::put(
            'stream_token_' . $streamToken, 
            [
                'user_id' => Auth::id(),
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'content' => $request->content,
                'files' => $fileData
            ],
            600 // 10 minutes
        );

        // Return the token to be used for streaming
        return response()->json([
            'success' => true,
            'message' => $message,
            'stream_token' => $streamToken
        ]);
    }

    /**
     * Stream AI response using the temporary token
     */
    public function streamWithToken(Request $request, $streamToken)
    {
        // Log the incoming token
        Log::info('Streaming with token request', [
            'token' => $streamToken,
            'headers' => $request->headers->all()
        ]);
        
        // Retrieve the stored data for this stream token
        $streamData = Cache::get('stream_token_' . $streamToken);
        
        if (!$streamData) {
            Log::error('Invalid or expired stream token', [
                'token' => $streamToken
            ]);
            return response()->json(['message' => 'Invalid or expired stream token'], 403);
        }
        
        // Log success
        Log::info('Stream token validated', [
            'token' => $streamToken,
            'user_id' => $streamData['user_id'],
            'conversation_id' => $streamData['conversation_id']
        ]);
        
        // Get the conversation
        $conversation = AiConversation::findOrFail($streamData['conversation_id']);
        $message = AiMessage::findOrFail($streamData['message_id']);
        $content = $streamData['content'];
        $fileData = $streamData['files'] ?? [];
        
        // Stream the response
        return response()->stream(function () use ($conversation, $message, $content, $fileData, $streamToken) {
            // Gửi message đầu tiên để lưu trữ ID
            echo "data: " . json_encode([
                'type' => 'user_message',
                'message' => $message
            ]) . "\n\n";
            flush();
            if (function_exists('ob_flush')) {
                ob_flush();
            }

            // Get the agent
            $agent = AiAgent::findOrFail($conversation->ai_agent_id);
            
            try {
                // Lấy business và assistant ID từ business
                $business = Business::findOrFail($conversation->business_id);
                $assistantId = $business->assistant_id;
                
                // Nếu business chưa có assistant_id, tạo một assistant mới
                if (!$assistantId) {
                    $aiService = AIServiceFactory::create();
                    
                    // Tạo assistant mới với thông tin từ agent
                    $assistantId = $aiService->createAssistant([
                        'name' => $agent->name,
                        'instructions' => $agent->system_prompt ?: "Bạn là {$agent->name}, một trợ lý AI hữu ích.",
                        'model' => $agent->model ?: 'gpt-4-turbo',
                        'tools' => [
                            ['type' => 'retrieval']
                        ],
                        'metadata' => [
                            'business_id' => $business->id,
                            'agent_id' => $agent->id
                        ]
                    ]);
                    
                    // Lưu assistant_id vào business
                    $business->assistant_id = $assistantId;
                    $business->save();
                }
                
                // Chuẩn bị các file đính kèm
                $fileIds = [];
                
                // Gọi service để gửi tin nhắn và nhận phản hồi stream
                $aiService = AIServiceFactory::create();
                $streamCallback = function ($chunk) {
                    echo "data: " . json_encode([
                        'type' => 'chunk',
                        'content' => $chunk
                    ]) . "\n\n";
                    flush();
                    if (function_exists('ob_flush')) {
                        ob_flush();
                    }
                };
                
                $response = $aiService->streamMessage(
                    $assistantId,
                    $content,
                    $fileIds,
                    [
                        'conversation_id' => (string) $conversation->id,
                        'business_id' => (string) $business->id
                    ],
                    $streamCallback
                );
                
                // Lưu thông tin thread vào conversation nếu cần
                if (!empty($response['thread_id']) && !$conversation->metadata) {
                    $conversation->metadata = ['thread_id' => $response['thread_id']];
                    $conversation->save();
                }
                
                // Lưu message của AI
                $aiMessage = AiMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'user_id' => null,
                    'role' => 'assistant',
                    'content' => $response['content'],
                    'is_read' => false,
                ]);
                
                // Update conversation's last_message_at
                $conversation->last_message_at = now();
                $conversation->save();
                
                // Remove the stream token from cache
                Cache::forget('stream_token_' . $streamToken);
                
                // Gửi thông tin message sau khi đã hoàn thành để frontend có ID
                echo "data: " . json_encode([
                    'type' => 'complete',
                    'message' => $aiMessage
                ]) . "\n\n";
                flush();
                if (function_exists('ob_flush')) {
                    ob_flush();
                }
                
            } catch (\Exception $e) {
                Log::error('Error in stream processing', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                echo "data: " . json_encode([
                    'type' => 'error',
                    'error' => 'Xin lỗi, đã xảy ra lỗi khi xử lý tin nhắn.'
                ]) . "\n\n";
                flush();
                if (function_exists('ob_flush')) {
                    ob_flush();
                }
            }
            
            echo "data: [DONE]\n\n";
            flush();
            if (function_exists('ob_flush')) {
                ob_flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no' // Disable buffering for Nginx
        ]);
    }

    /**
     * Original stream response method (for backward compatibility)
     */
    public function streamResponse(Request $request, AiConversation $conversation)
    {
        // Check if a token was provided in the query params (for EventSource)
        $tokenFromQuery = $request->query('token');
        if ($tokenFromQuery && !$request->user()) {
            // Manually authenticate with the token
            $user = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenFromQuery)?->tokenable;
            if ($user) {
                Auth::login($user);
            }
        }
        
        // Check if user has access to the conversation
        if (Auth::id() !== $conversation->user_id && !$this->isUserAdminOfBusiness($conversation->business_id)) {
            return response()->json(['message' => 'Unauthorized access to conversation'], 403);
        }

        $request->validate([
            'content' => 'required|string',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:pdf,doc,docx,txt,jpg,jpeg,png|max:10240',
        ]);

        // Create user message
        $message = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'role' => 'user',
            'content' => $request->content,
            'is_read' => true,
        ]);

        // Initialize fileData as empty array
        $fileData = [];
        
        // Handle file uploads
        if ($request->hasFile('files')) {
            $fileUrls = [];
            
            foreach ($request->file('files') as $file) {
                $path = $file->store('ai_conversation_files/' . $conversation->id, 'public');
                $fileUrl = url('storage/' . $path);
                $fileUrls[] = $fileUrl;
                
                // Xử lý nội dung file theo loại
                $fileContent = $this->processFileContent($file);
                if ($fileContent) {
                    $fileData[] = [
                        'url' => $fileUrl,
                        'name' => $file->getClientOriginalName(),
                        'content' => $fileContent,
                    ];
                }
            }
            
            // Update message with file URLs
            $message->files = $fileUrls;
            $message->save();
        }

        // Return streaming response
        return response()->stream(function () use ($conversation, $request, $message, $fileData) {
            // Tắt buffering để đảm bảo dữ liệu được gửi ngay lập tức
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }
            
            // Tắt bất kỳ buffering nào
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', false);
            
            if (function_exists('ini_set')) {
                ini_set('implicit_flush', true);
            }
            
            if (function_exists('ob_implicit_flush')) {
                ob_implicit_flush(true);
            }
            
            // Xóa tất cả buffer đang tồn tại
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            
            // Gửi message đầu tiên để lưu trữ ID
            echo "data: " . json_encode([
                'type' => 'user_message',
                'message' => $message
            ]) . "\n\n";
            flush();
            if (function_exists('ob_flush')) {  
                ob_flush();
            }

            // Get the agent
            $agent = AiAgent::findOrFail($conversation->ai_agent_id);
            
            try {
                // Lấy business và assistant ID từ business
                $business = Business::findOrFail($conversation->business_id);
                $assistantId = $business->assistant_id;
                
                // Nếu business chưa có assistant_id, tạo một assistant mới
                if (!$assistantId) {
                    $aiService = AIServiceFactory::create();
                    
                    // Tạo assistant mới với thông tin từ agent
                    $assistantId = $aiService->createAssistant([
                        'name' => $agent->name,
                        'instructions' => $agent->system_prompt ?: "Bạn là {$agent->name}, một trợ lý AI hữu ích.",
                        'model' => $agent->model ?: 'gpt-4-turbo',
                        'tools' => [
                            ['type' => 'retrieval']
                        ],
                        'metadata' => [
                            'business_id' => $business->id,
                            'agent_id' => $agent->id
                        ]
                    ]);
                    
                    // Lưu assistant_id vào business
                    $business->assistant_id = $assistantId;
                    $business->save();
                }
                
                // Chuẩn bị các file đính kèm
                $fileIds = [];
                
                // Gọi service để gửi tin nhắn và nhận phản hồi stream
                $aiService = AIServiceFactory::create();
                $streamCallback = function ($chunk) {
                    echo "data: " . json_encode([
                        'type' => 'chunk',
                        'content' => $chunk
                    ]) . "\n\n";
                    flush();
                    if (function_exists('ob_flush')) {
                        ob_flush();
                    }
                };
                
                $response = $aiService->streamMessage(
                    $assistantId,
                    $request->content,
                    $fileIds,
                    [
                        'conversation_id' => (string) $conversation->id,
                        'business_id' => (string) $business->id
                    ],
                    $streamCallback
                );
                
                // Lưu thông tin thread vào conversation nếu cần
                if (!empty($response['thread_id']) && !$conversation->metadata) {
                    $conversation->metadata = ['thread_id' => $response['thread_id']];
                    $conversation->save();
                }
                
                // Lưu message của AI
                $aiMessage = AiMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'user_id' => null,
                    'role' => 'assistant',
                    'content' => $response['content'],
                    'is_read' => false,
                ]);
                
                // Update conversation's last_message_at
                $conversation->last_message_at = now();
                $conversation->save();
                
                // Gửi thông tin message sau khi đã hoàn thành để frontend có ID
                echo "data: " . json_encode([
                    'type' => 'complete',
                    'message' => $aiMessage
                ]) . "\n\n";
                
            } catch (\Exception $e) {
                Log::error('Error in stream processing', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                echo "data: " . json_encode([
                    'type' => 'error',
                    'error' => 'Xin lỗi, đã xảy ra lỗi khi xử lý tin nhắn.'
                ]) . "\n\n";
                flush();
                if (function_exists('ob_flush')) {
                    ob_flush();
                }
            }
            
            echo "data: [DONE]\n\n";
            flush();
            if (function_exists('ob_flush')) {
                ob_flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no' // Disable buffering for Nginx
        ]);
    }

    /**
     * Stream response trực tiếp sử dụng Assistants API với chức năng stream
     */
    public function streamDirectResponse(Request $request, $streamToken)
    {
        try {
            \Illuminate\Support\Facades\Log::info('Bắt đầu stream trực tiếp với token', ['token' => $streamToken]);
            
            // Xác thực stream token
            $streamData = Cache::get('stream_token_' . $streamToken);
            if (!$streamData) {
                return response()->json(['error' => 'Token stream không hợp lệ'], 401);
            }
            
            \Illuminate\Support\Facades\Log::info('Token stream hợp lệ', $streamData);
            
            // Trích xuất metadata từ token
            $userId = $streamData['user_id'];
            $conversationId = $streamData['conversation_id'];
            $content = $streamData['content'];
            
            // Lấy thông tin cuộc hội thoại
            $conversation = AiConversation::findOrFail($conversationId);
            
            // Kiểm tra quyền truy cập của người dùng
            if ($conversation->user_id != $userId) {
                return response()->json(['error' => 'Không có quyền truy cập'], 403);
            }
            
            // Lấy thông tin business và assistant
            $business = Business::findOrFail($conversation->business_id);
            $assistantId = $business->assistant_id;
            $agent = AiAgent::findOrFail($conversation->ai_agent_id);
            
            if (!$assistantId) {
                return response()->json(['error' => 'Chưa cấu hình assistant cho business này'], 404);
            }
            
            \Illuminate\Support\Facades\Log::info('Sử dụng assistant', [
                'assistant_id' => $assistantId,
                'business_id' => $business->id,
                'conversation_id' => $conversationId
            ]);
            
            // Trả về response dạng stream
            return response()->stream(function () use ($assistantId, $conversation, $content) {
                // Thiết lập OpenAI API client và headers
                $client = new Client();
                $apiKey = config('services.openai.api_key');
                $headers = [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v2'
                ];
                
                try {
                    // Lấy hoặc tạo thread ID
                    $threadId = null;
                    \Illuminate\Support\Facades\Log::info('Xem metadata ở conversation', ['metadata' => $conversation->metadata]);
                    $metadata = json_decode($conversation->metadata, true);
                    if ($metadata && isset($metadata['thread_id'])) {
                        $threadId = $metadata['thread_id'];
                        \Illuminate\Support\Facades\Log::info('Sử dụng thread có sẵn', ['thread_id' => $threadId]);
                    } else {
                        // Tạo thread mới
                        $threadResponse = $client->post('https://api.openai.com/v1/threads', [
                            'headers' => $headers,
                            'json' => []
                        ]);
                        
                        $threadData = json_decode($threadResponse->getBody()->getContents(), true);
                        $threadId = $threadData['id'];
                        
                        // Lưu thread ID vào conversation
                        $conversation->metadata = ['thread_id' => $threadId];
                        $conversation->save();
                        
                        \Illuminate\Support\Facades\Log::info('Đã tạo thread mới', ['thread_id' => $threadId]);
                    }
                    
                    // Thêm tin nhắn người dùng vào thread
                    $messageResponse = $client->post("https://api.openai.com/v1/threads/{$threadId}/messages", [
                        'headers' => $headers,
                        'json' => [
                            'role' => 'user',
                            'content' => $content
                        ]
                    ]);
                    
                    \Illuminate\Support\Facades\Log::info('Đã thêm tin nhắn người dùng vào thread');
                    
                    // Tạo run với stream: true - API sẽ trả về dữ liệu dạng stream
                    $streamResponse = $client->post("https://api.openai.com/v1/threads/{$threadId}/runs", [
                        'headers' => $headers,
                        'json' => [
                            'assistant_id' => $assistantId,
                            'stream' => true,
                        ],
                        'stream' => true, // Đảm bảo Guzzle xử lý response dạng stream
                    ]);
                    
                    // Xử lý dữ liệu stream
                    $buffer = '';
                    $body = $streamResponse->getBody();
                    $fullMessage = '';
                    
                    // Gửi sự kiện bắt đầu
                    echo "data: " . json_encode(['type' => 'start']) . "\n\n";
                    flush();
                    
                    // Log để debug
                    \Illuminate\Support\Facades\Log::info('Bắt đầu đọc stream response');
                    
                    while (!$body->eof()) {
                        // Đọc một phần của response
                        $chunk = $body->read(1024);
                        $buffer .= $chunk;
                        
                        // Log để debug
                        \Illuminate\Support\Facades\Log::debug('Chunk nhận được', ['chunk' => $chunk]);
                        
                        // Xử lý các event hoàn chỉnh
                        while (($pos = strpos($buffer, "data: ")) !== false) {
                            $endPos = strpos($buffer, "\n\n", $pos);
                            if ($endPos === false) {
                                break; // Đợi thêm dữ liệu
                            }
                            
                            $line = substr($buffer, $pos + 6, $endPos - $pos - 6);
                            $buffer = substr($buffer, $endPos + 2);
                            
                            // Bỏ qua marker [DONE] hoặc dòng trống
                            if (trim($line) === "[DONE]" || empty(trim($line))) {
                                continue;
                            }
                            
                            try {
                                $data = json_decode($line, true);
                                if (!$data) {
                                    \Illuminate\Support\Facades\Log::warning('Không thể parse JSON', ['line' => $line]);
                                    continue;
                                }
                                
                                // Log để debug
                                //\Illuminate\Support\Facades\Log::debug('Event nhận được', ['data' => $data]);
                                
                                // Xử lý sự kiện dựa vào loại
                                $eventType = $data['object'] ?? '';

                                \Illuminate\Support\Facades\Log::debug('Event nhận được', ['event' => $data]);
                                
                                // Xử lý delta tin nhắn - nơi chứa nội dung text
                                if ($eventType === 'thread.message.delta') {
                                    \Illuminate\Support\Facades\Log::debug('Nội dung tin nhắn', ['data' => $data]);

                                    if (isset($data['delta']['content'][0]['text']['value'])) {
                                        $textChunk = $data['delta']['content'][0]['text']['value'];
                                        $textContent = $textChunk;
                                        $fullMessage .= $textContent;
                                        
                                        // Log để debug
                                        \Illuminate\Support\Facades\Log::debug('Gửi chunk text', ['content' => $textContent]);
                                        
                                        // Gửi ngay mỗi chunk văn bản đến client
                                        echo "data: " . json_encode([
                                            'type' => 'chunk',
                                            'content' => $textContent
                                        ]) . "\n\n";
                                        flush();
                                    }
                                }
                                // Xử lý sự kiện run hoàn thành
                                else if ($eventType === 'thread.run' && isset($data['status']) && $data['status'] === 'completed') {
                                    \Illuminate\Support\Facades\Log::info('Run đã hoàn thành', ['thread_id' => $threadId]);
                                    
                                    // Lưu tin nhắn vào database
                                    $message = new AiMessage([
                                        'ai_conversation_id' => $conversation->id,
                                        'content' => $fullMessage,
                                        'role' => 'assistant',
                                        'is_read' => false,
                                        'metadata' => json_encode([
                                            'thread_id' => $threadId,
                                            'message_id' => $data['id'] ?? null
                                        ])
                                    ]);
                                    $message->save();
                                    
                                    // Cập nhật conversation's last_message_at
                                    $conversation->last_message_at = now();
                                    $conversation->save();
                                    
                                    // Gửi thông tin tin nhắn đã lưu
                                    echo "data: " . json_encode([
                                        'type' => 'message_saved',
                                        'message_id' => $message->id
                                    ]) . "\n\n";
                                    flush();
                                    
                                    // Gửi sự kiện hoàn thành
                                    echo "data: " . json_encode([
                                        'type' => 'complete',
                                        'message' => $fullMessage
                                    ]) . "\n\n";
                                    flush();
                                }
                                // Xử lý sự kiện run thất bại
                                else if ($eventType === 'thread.run.failed') {
                                    \Illuminate\Support\Facades\Log::error('Run thất bại', ['error' => $data['last_error'] ?? 'Unknown error']);
                                    
                                    echo "data: " . json_encode([
                                        'type' => 'error',
                                        'message' => 'Có lỗi xảy ra khi xử lý yêu cầu'
                                    ]) . "\n\n";
                                    flush();
                                }
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error('Lỗi xử lý stream', ['error' => $e->getMessage()]);
                            }
                        }
                    }
                    
                    // Gửi marker kết thúc
                    echo "data: [DONE]\n\n";
                    flush();
                    
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Lỗi streaming: ' . $e->getMessage(), [
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    echo "data: " . json_encode([
                        'type' => 'error',
                        'error' => 'Đã xảy ra lỗi: ' . $e->getMessage()
                    ]) . "\n\n";
                    flush();
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no' // Tắt buffering cho Nginx
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi xác thực token stream: ' . $e->getMessage());
            return response()->json(['error' => 'Lỗi stream'], 500);
        }
    }
}
