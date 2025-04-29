<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\VectorStoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    protected $vectorStoreService;
    
    public function __construct(VectorStoreService $vectorStoreService)
    {
        $this->vectorStoreService = $vectorStoreService;
    }
    
    /**
     * Tìm kiếm thông qua vector store
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'business_id' => 'nullable|exists:businesses,id',
            'limit' => 'nullable|integer|min:1|max:20',
            'data_type' => 'nullable|string|in:products,orders,customers,suppliers,general',
        ]);
        
        $query = $request->input('query');
        $user = $request->user();
        $businessId = $request->input('business_id');
        $limit = $request->input('limit', 5);
        $dataType = $request->input('data_type');
        
        if (!$user && !$request->has('public')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        try {
            $results = $this->vectorStoreService->search(
                $query, 
                $user ? $user->id : null, 
                $businessId, 
                $limit,
                $dataType
            );
            
            // Nếu có kết quả, lấy thêm thông tin file
            if (!empty($results)) {
                $fileIds = array_map(function($result) {
                    return $result['metadata']['file_id'] ?? null;
                }, $results);
                
                $fileIds = array_filter($fileIds);
                
                if (!empty($fileIds)) {
                    $files = File::whereIn('id', $fileIds)->get()->keyBy('id');
                    
                    // Gắn thông tin file vào kết quả
                    foreach ($results as &$result) {
                        $fileId = $result['metadata']['file_id'] ?? null;
                        if ($fileId && isset($files[$fileId])) {
                            $result['file'] = $files[$fileId];
                        }
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'results' => $results,
                'count' => count($results),
            ]);
        } catch (\Exception $e) {
            Log::error('Vector search error', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error during search: ' . $e->getMessage(),
            ], 500);
        }
    }
} 