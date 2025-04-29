<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\VectorStoreService;
use App\Models\File;
use Exception;

class ImportController extends Controller
{
    protected $vectorStoreService;
    
    public function __construct(VectorStoreService $vectorStoreService)
    {
        $this->vectorStoreService = $vectorStoreService;
    }
    
    public function import(Request $request)
    {
        try {
            // Validate input
            $validatedData = $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv,json,doc,docx,pdf,txt,rtf,ppt,pptx',
                'data_type' => 'nullable|string',
            ]);
            
            $file = $request->file('file');
            $dataType = $request->input('data_type', 'default');
            
            // Lấy user hiện tại
            $user = $request->user();
            
            // Kiểm tra subscription và quyền hạn
            $businessId = $request->input('business_id');
            
            // Nếu không phải superadmin, cần kiểm tra quyền hạn
            if ($user->role !== 'superadmin') {
                // Kiểm tra user có thuộc business không
                $hasAccess = $this->checkBusinessAccess($user, $businessId);
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền truy cập vào doanh nghiệp này'
                    ], 403);
                }
                
                // Kiểm tra subscription quota
                $canUpload = $this->checkSubscriptionQuota($businessId);
                if (!$canUpload) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Doanh nghiệp đã vượt quá giới hạn upload file. Vui lòng nâng cấp gói dịch vụ.'
                    ], 403);
                }
            }
            
            // Tạo tên file với extension gốc
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = pathinfo($originalName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;
            
            // Lưu file với tên và đuôi gốc
            $storagePath = $file->storeAs('imports', $filename);
            $fullPath = Storage::path($storagePath);
            
            Log::info('Import file uploaded', [
                'file' => $file->getClientOriginalName(),
                'data_type' => $dataType,
                'storage_path' => $storagePath,
                'extension' => $extension,
                'mime_type' => $file->getMimeType()
            ]);
            
            // Store file information in the database
            $fileModel = new File([
                'name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'path' => $storagePath,
                'type' => $file->getMimeType(),
                'data_type' => $dataType,
                'size' => $file->getSize(),
                'user_id' => $request->user()->id,
                'business_id' => $businessId,
            ]);
            $fileModel->save();
            
            // Upload to vector store
            $vectorStoreResult = null;
            try {
                $vectorStoreResult = $this->vectorStoreService->uploadFile(
                    $fullPath, 
                    ['type' => $dataType]
                );
                Log::info('File uploaded to vector store', $vectorStoreResult);
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                Log::error('Vector store upload failed: ' . $errorMessage);
                
                // Check for specific OpenAI API errors
                if (strpos($errorMessage, 'unsupported_file') !== false) {
                    $vectorStoreResult = [
                        'warning' => 'File uploaded to database but could not be added to vector store',
                        'error' => 'This file format is not supported by the AI search engine'
                    ];
                } else {
                    $vectorStoreResult = ['error' => $errorMessage];
                }
            }
            
            // Clean up temporary file
            Storage::delete($storagePath);
            
            // Sau khi upload lên vector store thành công, cập nhật thêm file_id và batch_id
            if ($vectorStoreResult && isset($vectorStoreResult['file_id'])) {
                $fileModel->embedding_id = $vectorStoreResult['file_id'];
                $fileModel->vector_status = 'completed';
                
                // Nếu có batch_id thì lưu thêm
                if (isset($vectorStoreResult['batch_id'])) {
                    $fileModel->batch_id = $vectorStoreResult['batch_id'];
                }
                
                $fileModel->save();
            }
            
            // Trong trường hợp lỗi, cập nhật trạng thái
            if (isset($vectorStoreResult['error'])) {
                $fileModel->vector_status = 'failed';
                $fileModel->save();
            }
            
            // Return result
            return response()->json([
                'success' => true,
                'message' => "File imported successfully",
                'data' => [
                    'file_name' => $file->getClientOriginalName(),
                    'file_id' => $fileModel->id,
                    'data_type' => $dataType,
                    'vector_store' => $vectorStoreResult
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Import error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function checkBusinessAccess($user, $businessId)
    {
        // Nếu không có business_id, cho phép upload
        if (!$businessId) return true;
        
        // Kiểm tra user có trong business_user table không
        return $user->businesses()->where('businesses.id', $businessId)->exists();
    }

    private function checkSubscriptionQuota($businessId)
    {
        // Nếu không có business_id, cho phép upload
        if (!$businessId) return true;
        
        // Lấy thông tin subscription của business
        $business = \App\Models\Business::find($businessId);
        if (!$business) return false;
        
        // TODO: Kiểm tra quota dựa trên subscription plan
        // Đây là placeholder, cần implement logic thực tế
        $currentFileCount = File::where('business_id', $businessId)->count();
        $maxFiles = 100; // Giả sử gói cơ bản cho phép 100 file
        
        return $currentFileCount < $maxFiles;
    }
} 