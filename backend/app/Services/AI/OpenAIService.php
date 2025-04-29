<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use GuzzleHttp\Client;

class OpenAIService implements AIServiceInterface
{
    protected $apiKey;
    protected $baseUrl = 'https://api.openai.com/v1';
    
    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        
        if (empty($this->apiKey)) {
            Log::error('OpenAI API key is not configured');
            throw new Exception('OpenAI API key is not configured. Please check your .env file.');
        }
        
        // Allow base URL override from config
        $configBaseUrl = config('services.openai.base_url');
        if (!empty($configBaseUrl)) {
            $this->baseUrl = rtrim($configBaseUrl, '/');
            Log::info('Using custom OpenAI API base URL', ['base_url' => $this->baseUrl]);
        }
    }
    
    /**
     * Tạo một assistant mới cho business
     *
     * @param array $options Các tùy chọn cho assistant
     * @return string ID của assistant được tạo
     */
    public function createAssistant(array $options): string
    {
        try {
            $defaultOptions = [
                'name' => 'Business Assistant',
                'instructions' => 'You are a helpful AI assistant for a business.',
                'model' => 'gpt-4-turbo',
                'tools' => [
                    ['type' => 'retrieval']
                ],
            ];
            
            $assistantOptions = array_merge($defaultOptions, $options);
            
            $response = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/assistants", $assistantOptions);
            
            if (!$response->successful()) {
                Log::error('Failed to create OpenAI assistant', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);
                throw new Exception('Failed to create OpenAI assistant: ' . $response->json()['error']['message'] ?? 'Unknown error');
            }
            
            return $response->json()['id'];
        } catch (Exception $e) {
            Log::error('Exception creating OpenAI assistant', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Tạo một vector store mới cho business
     *
     * @param array $options Các tùy chọn cho vector store
     * @return string ID của vector store được tạo
     */
    public function createVectorStore(array $options): string
    {
        try {
            $defaultOptions = [
                'name' => 'Business Vector Store',
                'description' => 'Vector store for business data',
            ];
            
            $vectorStoreOptions = array_merge($defaultOptions, $options);
            
            $response = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/vector_stores", $vectorStoreOptions);
            
            if (!$response->successful()) {
                Log::error('Failed to create OpenAI vector store', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);
                throw new Exception('Failed to create OpenAI vector store: ' . $response->json()['error']['message'] ?? 'Unknown error');
            }
            
            return $response->json()['id'];
        } catch (Exception $e) {
            Log::error('Exception creating OpenAI vector store', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Gửi tin nhắn đến assistant và nhận phản hồi
     *
     * @param string $assistantId ID của assistant
     * @param string $message Nội dung tin nhắn người dùng
     * @param array $files Các file đính kèm (tùy chọn)
     * @param array $metadata Metadata bổ sung (tùy chọn)
     * @return array Phản hồi từ assistant
     */
    public function sendMessage(string $assistantId, string $message, array $files = [], array $metadata = []): array
    {
        Log::info('Sending message to assistant', [
            'assistantId' => $assistantId,
            'message' => $message,
            'filesCount' => count($files),
            'hasMetadata' => !empty($metadata)
        ]);

        try {
            // 1. Create a thread if it doesn't exist
            $threadResponse = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/threads");
            
            if (!$threadResponse->successful()) {
                Log::error('Failed to create thread', [
                    'status' => $threadResponse->status(),
                    'body' => $threadResponse->json()
                ]);
                throw new Exception('Failed to create thread: ' . $threadResponse->json()['error']['message'] ?? 'Unknown error');
            }
            
            $threadId = $threadResponse->json()['id'];
            
            // 2. Add message to thread
            $messageData = [
                'role' => 'user',
                'content' => $message,
            ];
            
            if (!empty($files)) {
                $messageData['file_ids'] = $files;
            }
            
            if (!empty($metadata)) {
                $messageData['metadata'] = $metadata;
            }
            
            $response = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/threads/{$threadId}/messages", $messageData);

            if (!$response->successful()) {
                Log::error('Failed to add message to thread', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception('Failed to add message to thread: ' . $response->body());
            }
            
            // 3. Run assistant on the thread
            $runResponse = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/threads/{$threadId}/runs", [
                    'assistant_id' => $assistantId
                ]);
            
            if (!$runResponse->successful()) {
                Log::error('Failed to run assistant', [
                    'status' => $runResponse->status(),
                    'body' => $runResponse->json()
                ]);
                throw new Exception('Failed to run assistant');
            }
            
            $responseData = $runResponse->json();
            if (!isset($responseData['id'])) {
                Log::error('Invalid run response format', [
                    'response' => $responseData
                ]);
                throw new Exception('Invalid run response format: missing id');
            }
            
            $runId = $responseData['id'];
            
            // 4. Check the status of the run
            $status = 'queued';
            $maxAttempts = 60; // 5 minutes with 5 seconds per check
            $attempts = 0;
            
            while ($status !== 'completed' && $attempts < $maxAttempts) {
                //sleep(5); // Wait 5 seconds
                
                $statusResponse = Http::withToken($this->apiKey)
                    ->withHeaders([
                        'OpenAI-Beta' => 'assistants=v2'
                    ])
                    ->get("{$this->baseUrl}/threads/{$threadId}/runs/{$runId}");
                
                if (!$statusResponse->successful()) {
                    Log::error('Failed to check run status', [
                        'status' => $statusResponse->status(),
                        'body' => $statusResponse->json()
                    ]);
                    throw new Exception('Failed to check run status');
                }
                
                $status = $statusResponse->json()['status'];
                $attempts++;
                
                if (in_array($status, ['failed', 'cancelled', 'expired'])) {
                    Log::error('Run failed', [
                        'status' => $status,
                        'details' => $statusResponse->json()
                    ]);
                    throw new Exception("Run failed with status: {$status}");
                }
            }
            
            if ($status !== 'completed') {
                throw new Exception('Run timed out');
            }
            
            // 5. Get messages from assistant
            $messagesResponse = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->get("{$this->baseUrl}/threads/{$threadId}/messages", [
                    'order' => 'desc',
                    'limit' => 1
                ]);
            
            if (!$messagesResponse->successful()) {
                Log::error('Failed to get messages', [
                    'status' => $messagesResponse->status(),
                    'body' => $messagesResponse->json()
                ]);
                throw new Exception('Failed to get messages');
            }
            
            $messages = $messagesResponse->json()['data'];
            $assistantMessage = collect($messages)->firstWhere('role', 'assistant');
            
            if (!$assistantMessage) {
                throw new Exception('No assistant message found');
            }
            
            // Process message content
            $content = '';
            if (isset($assistantMessage['content']) && is_array($assistantMessage['content'])) {
                foreach ($assistantMessage['content'] as $contentItem) {
                    if ($contentItem['type'] === 'text') {
                        $content .= $contentItem['text']['value'] . "\n";
                    }
                }
            }
            
            return [
                'thread_id' => $threadId,
                'run_id' => $runId,
                'content' => trim($content),
                'message_id' => $assistantMessage['id'],
                'created_at' => $assistantMessage['created_at']
            ];
            
        } catch (Exception $e) {
            Log::error('Exception in sendMessage', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Thêm files vào vector store
     *
     * @param string $vectorStoreId ID của vector store
     * @param array $files Thông tin các file cần thêm
     * @return array Kết quả thêm files
     */
    public function addFilesToVectorStore(string $vectorStoreId, array $files): array
    {
        try {
            // 1. Tải lên file lên OpenAI
            $fileIds = [];
            
            foreach ($files as $file) {
                $fileResponse = Http::withToken($this->apiKey)
                    ->withHeaders([
                        'OpenAI-Beta' => 'assistants=v2'
                    ])
                    ->attach('file', file_get_contents($file['path']), $file['name'])
                    ->post("{$this->baseUrl}/files", [
                        'purpose' => 'vector_store_file'
                    ]);
                
                if (!$fileResponse->successful()) {
                    Log::error('Failed to upload file to OpenAI', [
                        'status' => $fileResponse->status(),
                        'body' => $fileResponse->json()
                    ]);
                    throw new Exception('Failed to upload file to OpenAI');
                }
                
                $fileIds[] = $fileResponse->json()['id'];
            }
            
            // 2. Tạo batch để thêm vào vector store
            $batchResponse = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/vector_stores/{$vectorStoreId}/file_batches", [
                    'file_ids' => $fileIds
                ]);
            
            if (!$batchResponse->successful()) {
                Log::error('Failed to add files to vector store', [
                    'status' => $batchResponse->status(),
                    'body' => $batchResponse->json()
                ]);
                throw new Exception('Failed to add files to vector store');
            }
            
            return [
                'batch_id' => $batchResponse->json()['id'],
                'file_ids' => $fileIds
            ];
        } catch (Exception $e) {
            Log::error('Exception adding files to OpenAI vector store', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Tìm kiếm trong vector store
     *
     * @param string $vectorStoreId ID của vector store
     * @param string $query Truy vấn tìm kiếm
     * @param array $filters Các bộ lọc (tùy chọn)
     * @return array Kết quả tìm kiếm
     */
    public function searchVectorStore(string $vectorStoreId, string $query, array $filters = []): array
    {
        try {
            $searchData = [
                'query' => $query,
                'max_chunks' => 10
            ];
            
            if (!empty($filters)) {
                $searchData['filter'] = $filters;
            }
            
            $response = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/vector_stores/{$vectorStoreId}/search", $searchData);
            
            if (!$response->successful()) {
                Log::error('Failed to search vector store', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);
                throw new Exception('Failed to search vector store');
            }
            
            return $response->json()['chunks'];
        } catch (Exception $e) {
            Log::error('Exception searching OpenAI vector store', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Gửi tin nhắn đến assistant và nhận phản hồi dạng stream
     *
     * @param string $assistantId ID của assistant
     * @param string $message Nội dung tin nhắn người dùng
     * @param array $files Các file đính kèm (tùy chọn)
     * @param array $metadata Metadata bổ sung (tùy chọn)
     * @param callable $streamCallback Callback xử lý từng chunk trong phản hồi
     * @return array Thông tin phản hồi (bao gồm thread_id và nội dung hoàn chỉnh)
     */
    public function streamMessage(string $assistantId, string $message, array $files = [], array $metadata = [], callable $streamCallback = null): array
    {
        try {
            // Kiểm tra API key
            if (empty($this->apiKey)) {
                Log::error('OpenAI API key is not configured');
                throw new Exception('OpenAI API key is not configured');
            }
            
            // Log the input parameters (excluding sensitive data)
            Log::info('Starting streamMessage', [
                'assistantId' => $assistantId,
                'messageLength' => strlen($message),
                'filesCount' => count($files),
                'hasMetadata' => !empty($metadata),
                'hasCallback' => is_callable($streamCallback)
            ]);
            
            // 1. Tạo thread nếu chưa có
            $threadResponse = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/threads");
            
            // Log detailed thread response information
            Log::info('Raw OpenAI thread response', [
                'status' => $threadResponse->status(),
                'headers' => $threadResponse->headers(),
                'raw_body' => substr($threadResponse->body(), 0, 2000) // Log first 2000 chars of body
            ]);
            
            if (!$threadResponse->successful()) {
                Log::error('Failed to create thread', [
                    'status' => $threadResponse->status(),
                    'body' => $threadResponse->body(),
                    'headers' => $threadResponse->headers()
                ]);
                throw new Exception('Failed to create thread: ' . ($threadResponse->json()['error']['message'] ?? 'Unknown error'));
            }
            
            // Check content type to determine how to process the response
            $contentType = $threadResponse->header('Content-Type');
            Log::info('Thread response content type', ['contentType' => $contentType]);
            
            $threadId = null;
            
            // If response is JSON, process normally
            if (strpos($contentType, 'application/json') !== false) {
                $threadData = $threadResponse->json();
                Log::info('Thread response data (JSON)', ['response' => $threadData]);
                
                if (!isset($threadData['id'])) {
                    Log::error('Invalid thread response format', [
                        'response' => $threadData
                    ]);
                    throw new Exception('Invalid thread response format: missing id');
                }
                
                $threadId = $threadData['id'];
            }
            // If response is event-stream (which can happen with project API keys)
            else if (strpos($contentType, 'text/event-stream') !== false) {
                // Extract the thread ID from the response body or headers
                $body = $threadResponse->body();
                Log::info('Thread response body sample', ['body' => substr($body, 0, 500)]);
                
                // Try to extract thread ID from Location header
                $location = $threadResponse->header('Location');
                if ($location) {
                    // The location might be like: .../threads/{thread_id}
                    $parts = explode('/', $location);
                    $threadId = end($parts);
                    Log::info('Extracted thread ID from Location header', ['threadId' => $threadId]);
                }
                
                // If we couldn't get the ID from the Location header
                if (empty($threadId)) {
                    // Try to extract it from the response body
                    if (preg_match('/"id":\s*"(thread_[^"]+)"/', $body, $matches)) {
                        $threadId = $matches[1];
                        Log::info('Extracted thread ID from response body', ['threadId' => $threadId]);
                    }
                }
                
                // If we still don't have a thread ID, use a fallback approach
                if (empty($threadId)) {
                    Log::warning('Could not extract thread ID from response, using fallback approach');
                    
                    // List threads and take the most recent
                    $threadsResponse = Http::withToken($this->apiKey)
                        ->withHeaders([
                            'OpenAI-Beta' => 'assistants=v2'
                        ])
                        ->get("{$this->baseUrl}/threads", [
                            'limit' => 1,
                            'order' => 'desc'
                        ]);
                    
                    if ($threadsResponse->successful() && isset($threadsResponse->json()['data'][0]['id'])) {
                        $threadId = $threadsResponse->json()['data'][0]['id'];
                        Log::info('Retrieved thread ID from threads list', ['threadId' => $threadId]);
                    } else {
                        Log::error('Failed to get thread ID from threads list', [
                            'status' => $threadsResponse->status(),
                            'body' => $threadsResponse->json()
                        ]);
                        throw new Exception('Could not determine thread ID');
                    }
                }
            } else {
                Log::error('Unexpected thread response content type', [
                    'contentType' => $contentType,
                    'body' => $threadResponse->body()
                ]);
                throw new Exception('Unexpected thread response format from OpenAI API');
            }
            
            // Ensure we have a valid thread ID
            if (empty($threadId)) {
                Log::error('Failed to obtain thread ID through any method');
                throw new Exception('Failed to obtain thread ID');
            }
            
            Log::info('Using thread ID', ['threadId' => $threadId]);
            
            // 2. Thêm tin nhắn vào thread
            $messageData = [
                'role' => 'user',
                'content' => $message,
            ];
            
            if (!empty($files)) {
                $messageData['file_ids'] = $files;
            }
            
            if (!empty($metadata)) {
                $messageData['metadata'] = $metadata;
            }
            
            $messageResponse = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/threads/{$threadId}/messages", $messageData);
            
            if (!$messageResponse->successful()) {
                Log::error('Failed to add message to thread', [
                    'status' => $messageResponse->status(),
                    'body' => $messageResponse->body(),
                    'headers' => $messageResponse->headers()
                ]);
                throw new Exception('Failed to add message to thread: ' . ($messageResponse->json()['error']['message'] ?? 'Unknown error'));
            }
            
            // Check content type for message response too
            $contentType = $messageResponse->header('Content-Type');
            Log::info('Message response content type', ['contentType' => $contentType]);
            
            // Log the message id if it's in JSON format
            if (strpos($contentType, 'application/json') !== false) {
                $messageData = $messageResponse->json();
                if (isset($messageData['id'])) {
                    Log::info('Message created with ID', ['messageId' => $messageData['id']]);
                }
            }
            
            // 3. Chạy assistant trên thread với stream=true
            $runResponse = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->post("{$this->baseUrl}/threads/{$threadId}/runs", [
                    'assistant_id' => $assistantId,
                    'stream' => true
                ]);
            
            // Log detailed response information immediately
            Log::info('Raw OpenAI run response', [
                'status' => $runResponse->status(),
                'headers' => $runResponse->headers(),
                'raw_body' => substr($runResponse->body(), 0, 2000) // Log first 2000 chars of body
            ]);
            
            if (!$runResponse->successful()) {
                Log::error('Failed to run assistant', [
                    'status' => $runResponse->status(),
                    'body' => $runResponse->body(),
                    'headers' => $runResponse->headers()
                ]);
                throw new Exception('Failed to run assistant: ' . ($runResponse->json()['error']['message'] ?? 'Unknown error'));
            }
            
            // For project API keys, the response format may be different
            // Check the Content-Type header to determine how to process the response
            $contentType = $runResponse->header('Content-Type');
            Log::info('Run response content type', ['contentType' => $contentType]);
            
            $runId = null;
            
            // If response is JSON, process normally
            if (strpos($contentType, 'application/json') !== false) {
                $responseData = $runResponse->json();
                Log::info('Run response data (JSON)', ['response' => $responseData]);
                
                if (empty($responseData) || !is_array($responseData)) {
                    Log::error('Empty or invalid run response', [
                        'response' => $responseData,
                        'status' => $runResponse->status(),
                        'headers' => $runResponse->headers()
                    ]);
                    throw new Exception('Empty or invalid run response from OpenAI');
                }
                
                if (!isset($responseData['id'])) {
                    Log::error('Invalid run response format', [
                        'response' => $responseData
                    ]);
                    throw new Exception('Invalid run response format: missing id');
                }
                
                $runId = $responseData['id'];
            } 
            // If response is event-stream (which is common with project API keys)
            else if (strpos($contentType, 'text/event-stream') !== false) {
                // Extract the run ID from the response body or headers
                // For event-stream, parse the first data chunk to get the ID
                $body = $runResponse->body();
                Log::info('Run response body sample', ['body' => substr($body, 0, 500)]); // Log first 500 chars for debugging
                
                // Try to extract run ID from Location header
                $location = $runResponse->header('Location');
                if ($location) {
                    // The location might be like: .../threads/{thread_id}/runs/{run_id}
                    $parts = explode('/', $location);
                    $runId = end($parts);
                    Log::info('Extracted run ID from Location header', ['runId' => $runId]);
                }
                
                // If we couldn't get the ID from the Location header
                if (empty($runId)) {
                    // Try to extract it from the response body
                    if (preg_match('/"id":\s*"(run_[^"]+)"/', $body, $matches)) {
                        $runId = $matches[1];
                        Log::info('Extracted run ID from response body', ['runId' => $runId]);
                    }
                }
                
                // If we still don't have a run ID, use a fallback approach
                if (empty($runId)) {
                    Log::warning('Could not extract run ID from response, using fallback approach');
                    
                    // Get the most recent run for this thread
                    $runsResponse = Http::withToken($this->apiKey)
                        ->withHeaders([
                            'OpenAI-Beta' => 'assistants=v2'
                        ])
                        ->get("{$this->baseUrl}/threads/{$threadId}/runs", [
                            'limit' => 1,
                            'order' => 'desc'
                        ]);
                    
                    if ($runsResponse->successful() && isset($runsResponse->json()['data'][0]['id'])) {
                        $runId = $runsResponse->json()['data'][0]['id'];
                        Log::info('Retrieved run ID from runs list', ['runId' => $runId]);
                    } else {
                        Log::error('Failed to get run ID from runs list', [
                            'status' => $runsResponse->status(),
                            'body' => $runsResponse->json()
                        ]);
                        throw new Exception('Could not determine run ID');
                    }
                }
            } else {
                Log::error('Unexpected response content type', [
                    'contentType' => $contentType,
                    'body' => $runResponse->body()
                ]);
                throw new Exception('Unexpected response format from OpenAI API');
            }
            
            // Ensure we have a valid run ID
            if (empty($runId)) {
                Log::error('Failed to obtain run ID through any method');
                throw new Exception('Failed to obtain run ID');
            }
            
            Log::info('Using run ID', ['runId' => $runId]);
            
            // We'll use polling instead of streaming because the stream endpoint appears to be invalid
            // This is a common approach recommended by OpenAI
            $fullContent = '';
            $isCompleted = false;
            $maxPollingAttempts = 60;
            $pollingAttempts = 0;
            $runStatus = 'queued';
            
            try {
                // Poll the run status until it's completed or failed or we reach max attempts
                while (!$isCompleted && $pollingAttempts < $maxPollingAttempts) {
                    // Sleep to avoid hitting rate limits (adjust as needed)
                    if ($pollingAttempts > 0) {
                        // usleep(10000); // 0.01 seconds instead of 0.1 seconds
                    }
                    
                    $pollingAttempts++;
                    Log::info('Polling run status', ['attempt' => $pollingAttempts, 'runId' => $runId]);
                    
                    // Get the current status of the run
                    $statusResponse = Http::withToken($this->apiKey)
                        ->withHeaders([
                            'OpenAI-Beta' => 'assistants=v2'
                        ])
                        ->get("{$this->baseUrl}/threads/{$threadId}/runs/{$runId}");
                    
                    if (!$statusResponse->successful()) {
                        Log::error('Failed to get run status', [
                            'status' => $statusResponse->status(),
                            'body' => $statusResponse->body(),
                        ]);
                        
                        if ($pollingAttempts >= $maxPollingAttempts) {
                            throw new Exception('Failed to get run status after multiple attempts');
                        }
                        
                        continue;
                    }
                    
                    $statusData = $statusResponse->json();
                    $runStatus = $statusData['status'] ?? 'unknown';
                    
                    Log::info('Current run status', ['status' => $runStatus, 'attempt' => $pollingAttempts]);
                    
                    // Check if the run has completed, failed, or expired
                    if (in_array($runStatus, ['completed', 'failed', 'cancelled', 'expired'])) {
                        $isCompleted = true;
                        
                        if ($runStatus === 'completed') {
                            // Get the messages from the thread
                            $messagesResponse = Http::withToken($this->apiKey)
                                ->withHeaders([
                                    'OpenAI-Beta' => 'assistants=v2'
                                ])
                                ->get("{$this->baseUrl}/threads/{$threadId}/messages", [
                                    'limit' => 10,
                                    'order' => 'desc'
                                ]);
                            
                            if (!$messagesResponse->successful()) {
                                Log::error('Failed to get messages after run completion', [
                                    'status' => $messagesResponse->status(),
                                    'body' => $messagesResponse->body(),
                                ]);
                                throw new Exception('Failed to get messages after run completion');
                            }
                            
                            $messagesData = $messagesResponse->json();
                            
                            // Find the assistant's message
                            if (isset($messagesData['data']) && is_array($messagesData['data'])) {
                                foreach ($messagesData['data'] as $message) {
                                    if (isset($message['role']) && $message['role'] === 'assistant') {
                                        // Extract the content from the message
                                        if (isset($message['content']) && is_array($message['content'])) {
                                            foreach ($message['content'] as $content) {
                                                if (isset($content['type']) && $content['type'] === 'text' && 
                                                    isset($content['text']) && is_array($content['text']) && 
                                                    isset($content['text']['value'])) {
                                                    
                                                    $messageText = $content['text']['value'];
                                                    $fullContent .= $messageText;
                                                    
                                                    // Simulate streaming by delivering chunks
                                                    if (is_callable($streamCallback)) {
                                                        // Chunk the message into smaller pieces to simulate real streaming
                                                        $chunks = $this->chunkText($messageText);
                                                        foreach ($chunks as $chunk) {
                                                            call_user_func($streamCallback, $chunk);
                                                            // Small delay between chunks to simulate typing
                                                            // usleep(10000); // 10 milliseconds thay vì 50ms
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        
                                        break; // We only need the most recent assistant message
                                    }
                                }
                            } else {
                                Log::warning('No messages data found after run completion', [
                                    'response' => $messagesData
                                ]);
                            }
                        } else if ($runStatus === 'failed') {
                            // Handle failure case
                            $errorDetails = $statusData['last_error'] ?? ['message' => 'Unknown error'];
                            $errorMessage = $errorDetails['message'] ?? 'Run failed without specific error details';
                            
                            Log::error('Run failed', [
                                'status' => $runStatus,
                                'error' => $errorDetails
                            ]);
                            
                            throw new Exception("Run failed with status: {$runStatus}");
                        } else {
                            // Other terminal states
                            Log::warning('Run ended in non-completed state', [
                                'status' => $runStatus
                            ]);
                            
                            throw new Exception("Run ended with status: {$runStatus}");
                        }
                    } else if ($runStatus === 'requires_action') {
                        // Handle any required actions (tool calls, etc.)
                        // This is a placeholder for handling tool calls which may be needed in the future
                        Log::info('Run requires action', [
                            'required_action' => $statusData['required_action'] ?? 'unknown'
                        ]);
                        
                        // For now, we'll just continue polling
                    }
                }
                
                if (!$isCompleted) {
                    Log::error('Run did not complete within expected time', [
                        'attempts' => $pollingAttempts,
                        'last_status' => $runStatus
                    ]);
                    throw new Exception('Run did not complete within expected time');
                }
                
                return [
                    'content' => $fullContent,
                    'thread_id' => $threadId,
                    'run_id' => $runId
                ];
            } catch (\Exception $e) {
                Log::error('Exception during stream processing', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        } catch (Exception $e) {
            Log::error('Exception streaming message from OpenAI assistant', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Fallback method to get the final message after a run completes
     * Used when streaming fails or is interrupted
     *
     * @param string $threadId Thread ID
     * @param string $runId Run ID
     * @return array Message data
     */
    private function getFinalMessageAfterCompletion(string $threadId, string $runId): array
    {
        Log::info('Getting final message after completion', ['thread_id' => $threadId, 'run_id' => $runId]);
        
        // Wait for run to complete if it's still in progress
        $attempts = 0;
        $maxAttempts = 24; // 2 minutes with 5 seconds per check
        $status = '';
        
        while ($attempts < $maxAttempts) {
            $statusResponse = Http::withToken($this->apiKey)
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2'
                ])
                ->get("{$this->baseUrl}/threads/{$threadId}/runs/{$runId}");
            
            if (!$statusResponse->successful()) {
                Log::error('Failed to check run status in fallback', [
                    'status' => $statusResponse->status(),
                    'body' => $statusResponse->json()
                ]);
                break;
            }
            
            $statusData = $statusResponse->json();
            if (isset($statusData['status'])) {
                $status = $statusData['status'];
                Log::info('Run status in fallback', ['status' => $status]);
                
                if ($status === 'completed') {
                    break;
                } else if ($status === 'failed' || $status === 'cancelled' || $status === 'expired') {
                    Log::error('Run failed in fallback', [
                        'status' => $status,
                        'details' => $statusData
                    ]);
                    throw new Exception("Run failed with status: {$status}");
                }
            }
            
            // sleep(5); // Wait between polling attempts - removing this delay
            $attempts++;
        }
        
        if ($status !== 'completed') {
            Log::warning('Run did not complete in allotted time, attempting to retrieve messages anyway');
        }
        
        // Get the latest message
        $messagesResponse = Http::withToken($this->apiKey)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2'
            ])
            ->get("{$this->baseUrl}/threads/{$threadId}/messages", [
                'order' => 'desc',
                'limit' => 1
            ]);
        
        if (!$messagesResponse->successful()) {
            Log::error('Failed to get messages in fallback', [
                'status' => $messagesResponse->status(),
                'body' => $messagesResponse->json()
            ]);
            throw new Exception('Failed to get messages in fallback');
        }
        
        $messagesData = $messagesResponse->json();
        if (!isset($messagesData['data']) || !is_array($messagesData['data']) || empty($messagesData['data'])) {
            Log::error('Invalid or empty messages response', [
                'response' => $messagesData
            ]);
            return [
                'content' => 'Error: No message content available',
                'thread_id' => $threadId,
                'run_id' => $runId
            ];
        }
        
        $assistantMessage = collect($messagesData['data'])->firstWhere('role', 'assistant');
        
        if ($assistantMessage) {
            if (isset($assistantMessage['content']) && is_array($assistantMessage['content']) && 
                !empty($assistantMessage['content']) && 
                isset($assistantMessage['content'][0]) && 
                isset($assistantMessage['content'][0]['text']) && 
                isset($assistantMessage['content'][0]['text']['value'])) {
                $content = $assistantMessage['content'][0]['text']['value'];
            } else {
                Log::warning('Could not access message content in fallback', [
                    'assistantMessage' => $assistantMessage
                ]);
                $content = "Error: Could not access message content";
            }
        } else {
            Log::warning('No assistant message found in fallback', [
                'messages' => $messagesData['data']
            ]);
            $content = "Error: No assistant message found";
        }
        
        return [
            'content' => $content,
            'thread_id' => $threadId,
            'run_id' => $runId
        ];
    }

    public function streamThread(string $threadId, string $runId)
    {
        Log::info('Starting streamThread', ['thread_id' => $threadId, 'run_id' => $runId]);
        
        try {
            $client = new Client();
            $runUrl = "{$this->baseUrl}/threads/{$threadId}/runs/{$runId}";
            $messagesUrl = "{$this->baseUrl}/threads/{$threadId}/messages?limit=1";
            $options = [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'OpenAI-Beta' => 'assistants=v2',
                    'Content-Type' => 'application/json',
                ],
            ];
            
            $attempts = 0;
            $maxAttempts = 60; // Increase max attempts to reduce sleep dependency
            $status = '';
            $lastMessageId = null;
            $lastMessageContent = '';
            
            // Start streaming immediately while checking status
            echo "data: " . json_encode(['type' => 'start']) . "\n\n";
            ob_flush();
            flush();
            
            // Poll for messages more aggressively
            while (($status !== 'completed' && $status !== 'failed') && $attempts < $maxAttempts) {
                // Check run status
                $response = $client->get($runUrl, $options);
                $responseData = json_decode($response->getBody()->getContents(), true);
                $status = $responseData['status'] ?? 'unknown';
                
                // Check if there's any new content, even if not completed
                $messagesResponse = $client->get($messagesUrl, $options);
                $messagesData = json_decode($messagesResponse->getBody()->getContents(), true);
                
                if (isset($messagesData['data']) && is_array($messagesData['data']) && count($messagesData['data']) > 0) {
                    $message = $messagesData['data'][0];
                    $currentMessageId = $message['id'];
                    $messageText = '';
                    
                    foreach ($message['content'] as $content) {
                        if ($content['type'] === 'text') {
                            $messageText .= $content['text']['value'];
                        }
                    }
                    
                    // If new message or content changed, stream it
                    if ($currentMessageId !== $lastMessageId || $messageText !== $lastMessageContent) {
                        // If we already have a message, only stream the new content
                        if ($lastMessageContent !== '' && $lastMessageId === $currentMessageId) {
                            $newContent = substr($messageText, strlen($lastMessageContent));
                            if (!empty($newContent)) {
                                $this->streamTextWithChunks($newContent);
                            }
                        } else {
                            // Stream entire message if it's new
                            $this->streamTextWithChunks($messageText);
                        }
                        
                        $lastMessageId = $currentMessageId;
                        $lastMessageContent = $messageText;
                    }
                }
                
                Log::info('Polling run status', ['status' => $status, 'attempt' => $attempts + 1]);
                $attempts++;
                
                if ($status === 'completed') {
                    break;
                }
                
                if ($status === 'failed') {
                    $error = $responseData['last_error'] ?? ['message' => 'Unknown error'];
                    Log::error('Run failed', ['error' => $error]);
                    throw new Exception('Run failed: ' . ($error['message'] ?? 'Unknown error'));
                }
            }
            
            // Final check to make sure we get the complete message
            if ($status === 'completed') {
                $messagesResponse = $client->get($messagesUrl, $options);
                $messagesData = json_decode($messagesResponse->getBody()->getContents(), true);
                
                if (isset($messagesData['data']) && is_array($messagesData['data']) && count($messagesData['data']) > 0) {
                    $message = $messagesData['data'][0];
                    $messageText = '';
                    
                    foreach ($message['content'] as $content) {
                        if ($content['type'] === 'text') {
                            $messageText .= $content['text']['value'];
                        }
                    }
                    
                    // Only stream if there's new content
                    if ($messageText !== $lastMessageContent) {
                        $newContent = substr($messageText, strlen($lastMessageContent));
                        if (!empty($newContent)) {
                            $this->streamTextWithChunks($newContent);
                        }
                    }
                    
                    // Send the complete event
                    echo "data: " . json_encode(['type' => 'complete', 'message' => $message]) . "\n\n";
                    ob_flush();
                    flush();
                    
                    return $message;
                }
            }
            
            throw new Exception("Failed to get message after {$attempts} attempts, final status: {$status}");
        } catch (Exception $e) {
            Log::error('Exception in streamThread', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            echo "data: " . json_encode(['type' => 'error', 'error' => $e->getMessage()]) . "\n\n";
            ob_flush();
            flush();
            throw $e;
        }
    }
    
    /**
     * Process a complete SSE event
     */
    private function processEvent(string $eventData)
    {
        try {
            $data = json_decode($eventData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to decode event JSON', [
                    'error' => json_last_error_msg(),
                    'data' => $eventData
                ]);
                return;
            }
            
            // Log the event type and structure for debugging
            Log::info('Processing event', [
                'type' => $data['type'] ?? 'unknown',
                'keys' => array_keys($data),
                'has_data' => isset($data['data']),
                'data_keys' => isset($data['data']) ? array_keys($data['data']) : []
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error processing event', [
                'message' => $e->getMessage(),
                'data' => $eventData
            ]);
        }
    }

    public function streamResponse($threadId, $runId)
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Prevent Nginx buffering

        // Disable output buffering
        if (ob_get_level()) ob_end_clean();
        ob_implicit_flush(true);
        
        Log::info('Starting streamResponse', ['thread_id' => $threadId, 'run_id' => $runId]);
        
        try {
            $client = new \GuzzleHttp\Client();
            $headers = [
                'Authorization' => "Bearer {$this->apiKey}",
                'OpenAI-Beta' => 'assistants=v2',
                'Content-Type' => 'application/json',
            ];
            
            // Send immediate signal that we're connecting
            echo "data: " . json_encode(['type' => 'connecting']) . "\n\n";
            flush();
            
            // Check if run is already completed
            $runResponse = $client->get("{$this->baseUrl}/threads/{$threadId}/runs/{$runId}", [
                'headers' => $headers,
            ]);
            
            $runData = json_decode($runResponse->getBody()->getContents(), true);
            $status = $runData['status'] ?? null;
            
            Log::info('Initial run status', ['status' => $status]);
            
            // If run is completed, stream the response
            if ($status === 'completed') {
                $messagesResponse = $client->get("{$this->baseUrl}/threads/{$threadId}/messages?limit=1", [
                    'headers' => $headers,
                ]);
                
                $messagesData = json_decode($messagesResponse->getBody()->getContents(), true);
                
                if (isset($messagesData['data']) && is_array($messagesData['data']) && count($messagesData['data']) > 0) {
                    $message = $messagesData['data'][0];
                    $messageText = '';
                    
                    // Extract text from message
                    foreach ($message['content'] as $content) {
                        if ($content['type'] === 'text') {
                            $messageText .= $content['text']['value'];
                        }
                    }
                    
                    // Stream the message character by character 
                    $this->streamTextWithChunks($messageText);
                    
                    // Send completion event
                    echo "data: " . json_encode(['type' => 'complete', 'message' => $message]) . "\n\n";
                    flush();
                } else {
                    echo "data: " . json_encode(['type' => 'error', 'error' => 'Failed to retrieve message']) . "\n\n";
                    flush();
                }
            } else {
                // If run is not completed, poll for updates
                $isCompleted = false;
                $attempts = 0;
                $maxAttempts = 60; // Longer maximum polling time
                $lastMessageId = null;
                $lastMessageContent = '';
                
                while (!$isCompleted) {
                    // Log each poll attempt to debug any delays
                    Log::info('Polling run status in streamResponse', ['attempt' => $attempts]);
                    
                    // Get the run status
                    $runResponse = $client->get("{$this->baseUrl}/threads/{$threadId}/runs/{$runId}", [
                        'headers' => $headers,
                    ]);
                    
                    $runData = json_decode($runResponse->getBody()->getContents(), true);
                    $status = $runData['status'] ?? null;
                    
                    Log::info('Run status', ['status' => $status, 'attempt' => $attempts]);
                    
                    // Check for content updates even if not completed
                    $messagesResponse = $client->get("{$this->baseUrl}/threads/{$threadId}/messages?limit=1", [
                        'headers' => $headers,
                    ]);
                    
                    $messagesData = json_decode($messagesResponse->getBody()->getContents(), true);
                    
                    if (isset($messagesData['data']) && is_array($messagesData['data']) && count($messagesData['data']) > 0) {
                        $message = $messagesData['data'][0];
                        $currentMessageId = $message['id'];
                        $messageText = '';
                        
                        foreach ($message['content'] as $content) {
                            if ($content['type'] === 'text') {
                                $messageText .= $content['text']['value'];
                            }
                        }
                        
                        // Stream any new content immediately
                        if ($currentMessageId !== $lastMessageId || $messageText !== $lastMessageContent) {
                            // If we already have a message, only stream the new content
                            if ($lastMessageContent !== '' && $lastMessageId === $currentMessageId) {
                                $newContent = substr($messageText, strlen($lastMessageContent));
                                if (!empty($newContent)) {
                                    $this->streamTextWithChunks($newContent);
                                }
                            } else {
                                // Stream entire message if it's new
                                $this->streamTextWithChunks($messageText);
                            }
                            
                            $lastMessageId = $currentMessageId;
                            $lastMessageContent = $messageText;
                        }
                    }
                    
                    // If completed, exit the loop
                    if ($status === 'completed') {
                        // Send completion event
                        if (isset($message)) {
                            echo "data: " . json_encode(['type' => 'complete', 'message' => $message]) . "\n\n";
                            flush();
                        }
                        $isCompleted = true;
                        break;
                    } 
                    // If we've reached our limit of attempts or the run failed, exit
                    else if ($attempts >= $maxAttempts || $status === 'failed' || $status === 'cancelled' || $status === 'expired') {
                        $errorMessage = $status === 'failed' ? 'Run failed: ' . ($runData['last_error']['message'] ?? 'Unknown error') : "Run {$status} after {$attempts} attempts";
                        
                        Log::error($errorMessage, ['status' => $status]);
                        echo "data: " . json_encode(['type' => 'error', 'error' => $errorMessage]) . "\n\n";
                        flush();
                        $isCompleted = true;
                        break;
                    }
                    
                    $attempts++;
                    // No sleep here - we want to poll frequently
                }
            }
        } catch (\Exception $e) {
            Log::error('Exception in streamResponse', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            echo "data: " . json_encode(['type' => 'error', 'error' => $e->getMessage()]) . "\n\n";
            flush();
        }
        
        Log::info('Completed streamResponse');
        return;
    }

    /**
     * Split text into smaller chunks for streaming
     *
     * @param string $text The text to chunk
     * @param int $chunkSize The approximate size of each chunk (default 20 characters)
     * @return array Array of text chunks
     */
    private function chunkText($text, $chunkSize = 20)
    {
        if (empty($text)) {
            return [];
        }
        
        return str_split($text, 1); // Always split into single characters
    }
    
    private function streamTextWithChunks($text)
    {
        $chunks = $this->chunkText($text);
        
        foreach ($chunks as $chunk) {
            echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
            ob_flush();
            flush();
            // usleep(5000); // Removing delay between chunks
        }
    }
} 