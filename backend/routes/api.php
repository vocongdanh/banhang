<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AIAgentController;
use App\Http\Controllers\AIConversationController;
use App\Http\Controllers\AIMessageController;

Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout')->middleware('auth:sanctum');

// Debug route to check token
Route::get('/check-auth', function (Request $request) {
    $user = $request->user();
    if ($user) {
        return response()->json([
            'authenticated' => true,
            'user_id' => $user->id,
            'token_exists' => true
        ]);
    }
    
    return response()->json([
        'authenticated' => false,
        'token_exists' => $request->bearerToken() ? true : false
    ]);
})->middleware('auth:sanctum');

// Get authenticated user
Route::get('/user', function (Request $request) {
    // Log incoming request
    Log::info('User request', [
        'headers' => $request->headers->all(),
        'has_bearer' => $request->bearerToken() ? true : false
    ]);
    
    try {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Không tìm thấy thông tin người dùng'
            ], 401);
        }
        
        // Load businesses relationship
        $user->load('businesses');
        
        return response()->json($user);
    } catch (\Exception $e) {
        Log::error('Error in /user route', [
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'message' => 'Lỗi: ' . $e->getMessage()
        ], 500);
    }
})->middleware('auth:sanctum');

// File management routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/upload', [FileController::class, 'store']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::get('/files/{id}/download', [FileController::class, 'download']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
    
    // Vector search route
    Route::post('/search', [SearchController::class, 'search']);
    
    // Data import route
    Route::post('/import', [ImportController::class, 'import']);
});

// Public routes that don't require authentication
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

// Test route that doesn't require authentication
Route::get('/debug', function () {
    return response()->json([
        'message' => 'API is working',
        'time' => now()->toDateTimeString(),
        'php_version' => phpversion(),
        'laravel_version' => app()->version(),
    ]);
});

// Stream endpoint with token-based auth (no auth middleware)
Route::get('/ai-stream/{streamToken}', [AIMessageController::class, 'streamWithToken']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Routes không cần kiểm tra subscription
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/user', function (Request $request) {
        // Log incoming request
        Log::info('User request', [
            'headers' => $request->headers->all(),
            'has_bearer' => $request->bearerToken() ? true : false
        ]);
        
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Không tìm thấy thông tin người dùng'
                ], 401);
            }
            
            // Load businesses relationship
            $user->load('businesses');
            
            return response()->json($user);
        } catch (\Exception $e) {
            Log::error('Error in /user route', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // AI Routes - basic functionality
    Route::apiResource('ai-agents', AIAgentController::class);
    Route::apiResource('ai-conversations', AIConversationController::class);
    Route::post('/ai-conversations/{conversation}/messages', [AIMessageController::class, 'store']);
    
    // Stream messages routes
    Route::get('/ai-conversations/{conversation}/messages/stream', [AIMessageController::class, 'streamResponse']);
    Route::post('/ai-conversations/{conversation}/messages/stream', [AIMessageController::class, 'streamResponse']);
    
    // New stream endpoints with token-based auth
    Route::post('/ai-conversations/{conversation}/messages/stream-prepare', [AIMessageController::class, 'streamPrepare']);
    
    Route::post('/ai-conversations/{conversation}/messages/read', [AIMessageController::class, 'markAsRead']);
    Route::delete('/ai-messages/{message}', [AIMessageController::class, 'destroy']);
    
    // Routes cần kiểm tra feature cụ thể
    Route::middleware('subscription:can_use_vector_search')->group(function () {
        Route::post('/vector-search', [SearchController::class, 'search']);
    });
});

// Add new direct streaming route using Chat API instead of Assistants API
Route::get('/ai-direct-stream/{token}', [AIMessageController::class, 'streamDirectResponse']); 