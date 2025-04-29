<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\VectorStoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
class FileController extends Controller
{
    protected $vectorStoreService;
    
    public function __construct(VectorStoreService $vectorStoreService)
    {
        $this->vectorStoreService = $vectorStoreService;
    }
    
    /**
     * Display a listing of the files.
     */
    public function index(Request $request)
    {
        $query = File::orderBy('created_at', 'desc');
        
        // Filter by user_id if not admin
        if ($request->user() && $request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }
        
        // Filter by business_id if provided
        if ($request->has('business_id')) {
            $query->where('business_id', $request->input('business_id'));
        }
        
        // Filter by data_type if provided
        if ($request->has('data_type')) {
            $query->where('data_type', $request->input('data_type'));
        }
        
        $files = $query->get();
        
        return response()->json($files);
    }

    /**
     * Store a newly uploaded file.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'data_type' => 'nullable|string|in:products,orders,customers,suppliers,general',
            'business_id' => 'nullable|exists:businesses,id',
        ]);

        if (!$request->hasFile('file')) {
            return response()->json(['message' => 'No file uploaded'], 400);
        }

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $extension = $uploadedFile->getClientOriginalExtension();
        $fileSize = $uploadedFile->getSize();
        $mimeType = $uploadedFile->getMimeType();

        Log::info('File info', [
            'path' => $uploadedFile->getPath(),
            'extension' => $extension,
            'mime_type' => $mimeType
        ]);
        
        // Generate a unique filename
        $filename = Str::uuid() . '.' . $extension;
        
        // Store the file
        $path = $uploadedFile->storeAs('uploads', $filename, 'public');
        
        if (!$path) {
            return response()->json(['message' => 'Error storing file'], 500);
        }
        
        // Get current user
        $user = $request->user();
        
        // Create database record
        $file = File::create([
            'name' => $originalName,
            'path' => $path,
            'filename' => $filename,
            'type' => $mimeType,
            'size' => $fileSize,
            'user_id' => $user ? $user->id : null,
            'business_id' => $request->input('business_id'),
            'data_type' => $request->input('data_type', 'general'),
            'vector_status' => 'pending',
        ]);
        
        // Process the file for vector store in the background
        dispatch(function () use ($file) {
            $this->vectorStoreService->processFile($file);
        })->afterResponse();
        
        return response()->json([
            'message' => 'File uploaded successfully',
            'file' => $file
        ], 201);
    }

    /**
     * Display the specified file.
     */
    public function show($id)
    {
        $file = File::findOrFail($id);
        
        return response()->json($file);
    }

    /**
     * Download the specified file.
     */
    public function download($id)
    {
        $file = File::findOrFail($id);
        
        if (!Storage::disk('public')->exists($file->path)) {
            return response()->json(['message' => 'File not found on storage'], 404);
        }
        
        $path = Storage::disk('public')->path($file->path);
        return response()->download($path, $file->name);
    }

    /**
     * Remove the specified file from storage.
     */
    public function destroy($id)
    {
        try {
            $file = File::findOrFail($id);
            
            // Check user permission (only file owner or admin can delete)
            $user = Auth::user();
            
            Log::info('User attempting to delete file', [
                'user_id' => $user ? $user->id : null,
                'file_id' => $id,
                'file_owner' => $file->user_id
            ]);
            
            // Nếu không có user (API call không có auth) hoặc không phải chủ sở hữu/superadmin thì từ chối
            if (!$user) {
                Log::error('File deletion failed - No authenticated user');
                return response()->json(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện thao tác này'], 401);
            }
            
            if ($user->id !== $file->user_id && $user->role !== 'superadmin') {
                Log::error('File deletion failed - Permission denied', [
                    'user_id' => $user->id,
                    'file_owner' => $file->user_id
                ]);
                return response()->json(['success' => false, 'message' => 'Bạn không có quyền xóa file này'], 403);
            }
            
            // Delete from vector store if has embedding_id
            if ($file->embedding_id) {
                $vectorStoreService = app(VectorStoreService::class);
                $vectorStoreService->deleteFileFromVectorStore($file->embedding_id);
            }
            
            // Delete file from storage
            if (Storage::exists($file->path)) {
                Storage::delete($file->path);
            }
            
            // Delete database record
            $file->delete();
            
            Log::info('File deleted successfully', ['file_id' => $id]);
            return response()->json(['success' => true, 'message' => 'File đã được xóa thành công']);
        } catch (\Exception $e) {
            Log::error('Error deleting file', [
                'file_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['success' => false, 'message' => 'Lỗi khi xóa file: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Search files using vector search
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:3',
            'business_id' => 'nullable|exists:businesses,id',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);
        
        $query = $request->input('query');
        $user = $request->user();
        $businessId = $request->input('business_id');
        $limit = $request->input('limit', 5);
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $results = $this->vectorStoreService->search($query, $user->id, $businessId, $limit);
        
        return response()->json([
            'results' => $results,
        ]);
    }
} 