<?php

use Illuminate\Support\Facades\Route;
use App\Modules\AI\Http\Controllers\AIAgentController;

Route::prefix('ai')->group(function () {
    // AI Agent routes
    Route::apiResource('agents', AIAgentController::class);
    
    // Generate response from AI agent
    Route::post('agents/{agent}/generate', [AIAgentController::class, 'generateResponse']);
    
    // Vector store routes
    Route::prefix('vector-store')->group(function () {
        Route::post('search', [AIAgentController::class, 'searchVectorStore']);
    });
}); 