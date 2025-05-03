<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Exception;

class VectorStoreService
{
    protected $openaiApiKey;
    protected $vectorStoreId;
    
    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.api_key');
        $this->vectorStoreId = env('OPENAI_VECTOR_STORE_ID');
    }
    
    /**
     * Process a file and upload its content to the vector store
     * 
     * @param File $file The file model
     * @return bool Whether the operation was successful
     */
    public function processFile(File $file)
    {
        try {
            // Update the status to processing
            $file->vector_status = 'processing';
            $file->save();
            
            // Get the file content
            $path = Storage::disk('public')->path($file->path);
            
            if (!file_exists($path)) {
                throw new \Exception("File not found: {$path}");
            }
            
            // Extract content based on file type
            $content = $this->extractContent($path, $file->type);
            
            // Generate embedding and upload to vector store
            $embeddingId = $this->uploadToVectorStore($content, $file);
            
            // Update the file record
            $file->embedding_id = $embeddingId;
            $file->vector_status = 'completed';
            $file->save();
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error processing file for vector store', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Update the status to failed
            $file->vector_status = 'failed';
            $file->save();
            
            return false;
        }
    }
    
    /**
     * Extract content from a file based on its type
     * 
     * @param string $path File path
     * @param string $type File MIME type
     * @return string Extracted content
     */
    protected function extractContent($path, $type)
    {
        // Basic implementation for text and CSV files
        if (strpos($type, 'text/') === 0 || $type === 'text/csv' || $type === 'application/csv') {
            return file_get_contents($path);
        }
        
        // For Excel files
        if ($type === 'application/vnd.ms-excel' || 
            $type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            // Use a library like PhpSpreadsheet to extract content
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $spreadsheet = $reader->load($path);
            
            $content = '';
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                foreach ($worksheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    
                    foreach ($cellIterator as $cell) {
                        $content .= $cell->getValue() . "\t";
                    }
                    $content .= PHP_EOL;
                }
            }
            
            return $content;
        }
        
        // For PDF files
        if ($type === 'application/pdf') {
            // You would need a PDF parser library
            // This is a placeholder for such functionality
            return "PDF content extraction not implemented";
        }
        
        return "Unsupported file type: {$type}";
    }
    
    /**
     * Upload content to the vector store using OpenAI API
     * 
     * @param string $content The content to embed
     * @param File $file The file model for metadata
     * @return string The embedding ID
     */
    protected function uploadToVectorStore($content, File $file)
    {
        try {
            // Generate a unique namespace based on user_id and business_id
            $namespace = "user_{$file->user_id}" . ($file->business_id ? "_business_{$file->business_id}" : "");
            
            // Prepare metadata
            $metadata = [
                'file_id' => $file->id,
                'filename' => $file->name,
                'data_type' => $file->data_type,
                'user_id' => $file->user_id,
                'business_id' => $file->business_id,
                'uploaded_at' => $file->created_at->toIso8601String(),
            ];
            
            // Generate embeddings using OpenAI
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'input' => $content,
                'model' => 'text-embedding-3-small',
                'encoding_format' => 'float',
            ]);
            
            if (!$response->successful()) {
                Log::error('OpenAI Embedding API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception("OpenAI Embedding API error: " . $response->body());
            }
            
            $embedding = $response->json('data.0.embedding');
            
            // Kiểm tra vector_store_id đã được cấu hình
            if (empty($this->vectorStoreId)) {
                Log::error('Vector Store ID không được cấu hình');
                throw new \Exception("Vector Store ID không được cấu hình");
            }
            
            Log::info('Gọi API Vector Store', [
                'vector_store_id' => $this->vectorStoreId,
                'namespace' => $namespace,
            ]);
            
            // Thay vì gọi /vector_stores/vectors với header OpenAI-Beta,
            // bây giờ gọi trực tiếp vector_stores API
            $vectorRequest = [
                'vectors' => [
                    [
                        'embedding' => $embedding,
                        'metadata' => $metadata,
                        'content' => substr($content, 0, 2000), // Giới hạn content để tránh lỗi
                        'namespace' => $namespace
                    ]
                ]
            ];
            
            Log::info('Vector Store Request', [
                'url' => "https://api.openai.com/v1/vector_stores/{$this->vectorStoreId}/vectors",
                'request' => json_encode($vectorRequest)
            ]);
            
            $vectorResponse = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->post("https://api.openai.com/v1/vector_stores/{$this->vectorStoreId}/vectors", $vectorRequest);
            
            // Log chi tiết response để debug
            Log::info('Vector Store Response', [
                'status' => $vectorResponse->status(),
                'body' => $vectorResponse->body(),
            ]);
            
            if (!$vectorResponse->successful()) {
                Log::error('OpenAI Vector Store API error', [
                    'status' => $vectorResponse->status(),
                    'body' => $vectorResponse->body(),
                ]);
                throw new \Exception("OpenAI Vector Store API error: " . $vectorResponse->body());
            }
            
            // Get the vector ID from the response
            $vectorIds = $vectorResponse->json('vector_ids');
            
            if (empty($vectorIds) || !isset($vectorIds[0])) {
                throw new \Exception("No vector ID returned from OpenAI Vector Store");
            }
            
            // Return the actual vector ID from OpenAI Vector Store
            return $vectorIds[0];
        } catch (\Exception $e) {
            Log::error('Upload Vector Store Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Search the vector store for similar content
     * 
     * @param string $query The query to search for
     * @param int|null $userId The user ID to restrict search to
     * @param int|null $businessId Optional business ID to restrict search to
     * @param int $limit Maximum number of results to return
     * @param string|null $dataType Optional data type filter
     * @return array Search results
     */
    public function search($query, $userId = null, $businessId = null, $limit = 5, $dataType = null)
    {
        try {
            // Generate embeddings for the query
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'input' => $query,
                'model' => 'text-embedding-3-small',
                'encoding_format' => 'float',
            ]);
            
            if (!$response->successful()) {
                throw new \Exception("OpenAI API error: " . $response->body());
            }
            
            $queryEmbedding = $response->json('data.0.embedding');
            
            // Prepare filter for search
            $filter = [];
            
            if ($userId) {
                $filter['user_id'] = $userId;
            }
            
            if ($businessId) {
                $filter['business_id'] = $businessId;
            }
            
            if ($dataType) {
                $filter['data_type'] = $dataType;
            }
            
            // Build namespace based on filters for more efficient searching
            $namespace = $userId ? "user_{$userId}" : null;
            if ($businessId) {
                $namespace = $namespace ? "{$namespace}_business_{$businessId}" : "business_{$businessId}";
            }
            
            // Using direct HTTP request instead of the OpenAI SDK
            $searchResponse = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->post("https://api.openai.com/v1/vector_stores/{$this->vectorStoreId}/search", [
                'query' => $queryEmbedding,
                'namespace' => $namespace,
                'filter' => !empty($filter) ? $filter : null,
                'limit' => $limit
            ]);
            
            if (!$searchResponse->successful()) {
                throw new \Exception("Vector Store search error: " . $searchResponse->body());
            }
            
            $matches = $searchResponse->json('matches') ?? [];
            
            // Format the results
            $results = [];
            foreach ($matches as $match) {
                $results[] = [
                    'score' => $match['score'],
                    'content' => $match['content'],
                    'metadata' => $match['metadata'],
                    'vector_id' => $match['id'],
                ];
            }
            
            // Log the search query and results
            Log::info('Vector search performed', [
                'query' => $query,
                'user_id' => $userId,
                'business_id' => $businessId,
                'data_type' => $dataType,
                'results_count' => count($results),
            ]);
            
            return $results;
        } catch (\Exception $e) {
            Log::error('Error searching vector store', [
                'query' => $query,
                'user_id' => $userId,
                'business_id' => $businessId,
                'data_type' => $dataType,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }
    
    /**
     * Upload file to vector store
     */
    public function uploadFile($filePath, $attributes = [])
    {
        try {
            // Thêm log chi tiết hơn về file được xử lý
            Log::info('VectorStoreService uploadFile - Bắt đầu xử lý file', [
                'filePath' => $filePath,
                'exists' => file_exists($filePath),
                'fileSize' => file_exists($filePath) ? filesize($filePath) : 'N/A',
                'baseName' => basename($filePath)
            ]);
            
            // Check if file is CSV and convert to text if needed
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            Log::info('VectorStoreService uploadFile - File extension', [
                'extension' => $fileExtension,
                'pathinfo' => pathinfo($filePath)
            ]);
            $tempFile = null;
            
            // CSV files are not supported by OpenAI's vector store, so we convert them to .txt
            $unsupportedExtensions = ['csv', 'xlsx', 'xls'];
            if (in_array(strtolower($fileExtension), $unsupportedExtensions)) {
                $tempFile = tempnam(sys_get_temp_dir(), 'converted_') . '.txt';
                Log::info('VectorStoreService uploadFile - File cần chuyển đổi', [
                    'original_extension' => $fileExtension,
                    'tempFile' => $tempFile
                ]);
                
                // Xử lý chuyển đổi nội dung dựa trên loại file
                if (strtolower($fileExtension) === 'csv') {
                    // Chuyển đổi CSV sang text
                    Log::info('VectorStoreService uploadFile - Bắt đầu chuyển đổi CSV sang text');
                    $this->convertCsvToText($filePath, $tempFile);
                } else if (in_array(strtolower($fileExtension), ['xlsx', 'xls'])) {
                    // Chuyển đổi Excel sang text
                    Log::info('VectorStoreService uploadFile - Bắt đầu chuyển đổi Excel sang text');
                    $this->convertExcelToText($filePath, $tempFile);
                }
                
                $filePath = $tempFile;
                Log::info('Đã chuyển đổi file không được hỗ trợ sang định dạng txt', [
                    'original_extension' => $fileExtension,
                    'new_path' => $filePath
                ]);
            } else {
                Log::info('VectorStoreService uploadFile - File không cần chuyển đổi', [
                    'extension' => $fileExtension
                ]);
            }
            
            // 1. Upload file to OpenAI
            $fileHandle = fopen($filePath, 'r');
            $fileName = basename($filePath);
            
            $fileResponse = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
            ])->attach(
                'file', $fileHandle, $fileName
            )->post('https://api.openai.com/v1/files', [
                'purpose' => 'assistants',
            ]);
            
            if (!$fileResponse->successful()) {
                throw new \Exception("File upload error: " . $fileResponse->body());
            }
            
            $openaiFileId = $fileResponse->json('id');
            
            // 2. Add to vector store
            $batchResponse = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->post("https://api.openai.com/v1/vector_stores/{$this->vectorStoreId}/file_batches", [
                'file_ids' => [$openaiFileId],
                'attributes' => $attributes,
            ]);
            
            // Clean up temp file if created
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            if (!$batchResponse->successful()) {
                throw new \Exception("Vector store file batch error: " . $batchResponse->body());
            }
            
            return [
                'success' => true,
                'file_id' => $openaiFileId,
                'batch_id' => $batchResponse->json('id')
            ];
        } catch (Exception $e) {
            // Clean up temp file if created and exception occurred
            if (isset($tempFile) && $tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            Log::error('Vector store file upload error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Chuyển đổi file CSV sang định dạng văn bản
     * 
     * @param string $inputPath Đường dẫn file CSV
     * @param string $outputPath Đường dẫn file output
     * @return bool Trạng thái chuyển đổi
     */
    protected function convertCsvToText($inputPath, $outputPath)
    {
        try {
            $output = fopen($outputPath, 'w');
            if (($handle = fopen($inputPath, "r")) !== FALSE) {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    // Nối các cột với ký tự tab và thêm xuống dòng
                    fwrite($output, implode("\t", $data) . PHP_EOL);
                }
                fclose($handle);
            }
            fclose($output);
            
            // CHỈNH SỬA TẠM THỜI: Lưu một bản sao của file đã chuyển đổi để debug
            $debugDir = storage_path('app/debug');
            if (!file_exists($debugDir)) {
                mkdir($debugDir, 0755, true);
            }
            $debugFile = $debugDir . '/' . basename($inputPath) . '_converted_' . date('YmdHis') . '.txt';
            copy($outputPath, $debugFile);
            
            // Log thông tin về file debug
            \Illuminate\Support\Facades\Log::info('DEBUG: Đã lưu bản sao của file CSV đã chuyển đổi', [
                'input_file' => $inputPath,
                'debug_file' => $debugFile
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Lỗi khi chuyển đổi CSV sang text', [
                'input' => $inputPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Chuyển đổi file Excel sang định dạng văn bản
     * 
     * @param string $inputPath Đường dẫn file Excel
     * @param string $outputPath Đường dẫn file output
     * @return bool Trạng thái chuyển đổi
     */
    protected function convertExcelToText($inputPath, $outputPath)
    {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($inputPath);
            $spreadsheet = $reader->load($inputPath);
            
            $output = fopen($outputPath, 'w');
            
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                // Thêm tên worksheet vào đầu
                fwrite($output, "Sheet: " . $worksheet->getTitle() . PHP_EOL);
                
                foreach ($worksheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    
                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $rowData[] = $cell->getValue();
                    }
                    
                    // Nối các cột với ký tự tab và thêm xuống dòng
                    fwrite($output, implode("\t", $rowData) . PHP_EOL);
                }
                
                // Thêm dòng trống giữa các worksheet
                fwrite($output, PHP_EOL);
            }
            
            fclose($output);
            
            // CHỈNH SỬA TẠM THỜI: Lưu một bản sao của file đã chuyển đổi để debug
            $debugDir = storage_path('app/debug');
            if (!file_exists($debugDir)) {
                mkdir($debugDir, 0755, true);
            }
            $debugFile = $debugDir . '/' . basename($inputPath) . '_converted_' . date('YmdHis') . '.txt';
            copy($outputPath, $debugFile);
            
            // Log thông tin về file debug
            \Illuminate\Support\Facades\Log::info('DEBUG: Đã lưu bản sao của file Excel đã chuyển đổi', [
                'input_file' => $inputPath,
                'debug_file' => $debugFile
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Lỗi khi chuyển đổi Excel sang text', [
                'input' => $inputPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get vector store file batch status
     */
    public function getBatchStatus($batchId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->get("https://api.openai.com/v1/vector_stores/{$this->vectorStoreId}/file_batches/{$batchId}");
            
            if (!$response->successful()) {
                throw new \Exception("Batch status error: " . $response->body());
            }
            
            return $response->json();
        } catch (Exception $e) {
            Log::error('Vector store batch status error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * List all files in the vector store
     */
    public function listFiles($limit = 100)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->get("https://api.openai.com/v1/vector_stores/{$this->vectorStoreId}/files", [
                'limit' => $limit
            ]);
            
            if (!$response->successful()) {
                throw new \Exception("List files error: " . $response->body());
            }
            
            return $response->json();
        } catch (Exception $e) {
            Log::error('Vector store list files error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete file from vector store
     */
    public function deleteFile($fileId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->delete("https://api.openai.com/v1/vector_stores/{$this->vectorStoreId}/files/{$fileId}");
            
            if (!$response->successful()) {
                throw new \Exception("Delete file error: " . $response->body());
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            Log::error('Vector store delete file error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete file from OpenAI API
     * 
     * @param string $fileId OpenAI file ID
     * @return array Result status
     */
    public function deleteFileFromVectorStore($fileId)
    {
        if (!$fileId) {
            return false;
        }
        
        try {
            // Sử dụng HTTP request thay vì $this->client
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->delete("https://api.openai.com/v1/files/{$fileId}");
            
            if (!$response->successful()) {
                throw new \Exception("File deletion error: " . $response->body());
            }
            
            return [
                'success' => true,
                'deleted' => $fileId
            ];
        } catch (\Exception $e) {
            Log::error('Error deleting file from vector store', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a new vector store collection
     * 
     * @param string $collectionName The name for the new collection
     * @return array|null Collection data or null on failure
     */
    public function createCollection($collectionName)
    {
        try {
            // If we're using OpenAI's vector store
            if (env('APP_ENV') === 'production') {
                // Create a new vector store in OpenAI
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->openaiApiKey}",
                    'Content-Type' => 'application/json',
                ])->post('https://api.openai.com/v1/vector_stores', [
                    'name' => $collectionName,
                    'description' => "Vector store for {$collectionName}",
                ]);
                
                if (!$response->successful()) {
                    Log::error('OpenAI Vector Store Creation API error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    throw new \Exception("OpenAI Vector Store API error: " . $response->body());
                }
                
                return $response->json();
            }
            
            // For development/testing, return a mock collection
            return [
                'id' => 'vs_' . uniqid(),
                'name' => $collectionName,
                'created_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error('Vector Store Collection Creation Error', [
                'collection_name' => $collectionName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }
} 