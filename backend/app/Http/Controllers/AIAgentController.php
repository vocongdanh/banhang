<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\Business;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AIAgentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $businessId = $request->input('business_id');
        
        if (!$businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Business ID is required'
            ], 400);
        }
        
        // Check user has access to this business
        $user = $request->user();
        if ($user->role !== 'superadmin' && !$user->businesses()->where('businesses.id', $businessId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this business'
            ], 403);
        }
        
        $agents = AiAgent::where('business_id', $businessId)->get();
        
        return response()->json([
            'success' => true,
            'data' => $agents
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'personality' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'business_id' => 'required|exists:businesses,id',
            'access_role' => 'nullable|string|in:owner,admin,member',
            'model' => 'nullable|string',
            'avatar' => 'nullable|image|max:1024', // 1MB max
            'capabilities' => 'nullable|array',
        ]);
        
        // Check user has access to this business
        $user = $request->user();
        if ($user->role !== 'superadmin' && !$user->businesses()->where('businesses.id', $validatedData['business_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this business'
            ], 403);
        }
        
        // Check subscription limits
        $business = Business::find($validatedData['business_id']);
        $subscription = $business->subscription;
        
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Business has no active subscription'
            ], 403);
        }
        
        // $plan = $subscription->subscriptionPlan;
        
        // if (!$plan) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Subscription has no plan'
        //     ], 403);
        // }
        
        // $currentAgentCount = $business->aiAgents()->count();
        
        // if ($currentAgentCount >= $plan->max_ai_agents) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'You have reached the maximum number of AI agents for your subscription plan'
        //     ], 403);
        // }
        
        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $validatedData['avatar'] = $avatarPath;
        }
        
        // Set default capabilities based on the subscription plan
        $capabilities = $validatedData['capabilities'] ?? [];
        
        // if ($plan->can_use_ai_voice) {
        //     $capabilities[] = 'voice';
        // }
        
        // if ($plan->can_use_ai_image_upload) {
        //     $capabilities[] = 'image_upload';
        // }
        
        $validatedData['capabilities'] = array_unique($capabilities);
        
        $agent = AiAgent::create($validatedData);
        
        return response()->json([
            'success' => true,
            'message' => 'AI Agent created successfully',
            'data' => $agent
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $agent = AiAgent::findOrFail($id);
        
        // Check user has access to this agent's business
        $user = request()->user();
        if ($user->role !== 'superadmin' && !$user->businesses()->where('businesses.id', $agent->business_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this AI agent'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $agent
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $agent = AiAgent::findOrFail($id);
        
        // Check user has access to this agent's business
        $user = $request->user();
        if ($user->role !== 'superadmin' && !$user->businesses()->where('businesses.id', $agent->business_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this AI agent'
            ], 403);
        }
        
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'personality' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'access_role' => 'nullable|string|in:owner,admin,member',
            'model' => 'nullable|string',
            'avatar' => 'nullable|image|max:1024', // 1MB max
            'capabilities' => 'nullable|array',
            'status' => 'sometimes|string|in:active,inactive',
        ]);
        
        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($agent->avatar) {
                Storage::disk('public')->delete($agent->avatar);
            }
            
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $validatedData['avatar'] = $avatarPath;
        }
        
        // Check capabilities against subscription plan
        if (isset($validatedData['capabilities'])) {
            $business = $agent->business;
            $subscription = $business->subscription;
            
            if ($subscription && $subscription->subscriptionPlan) {
                $plan = $subscription->subscriptionPlan;
                
                // Filter out capabilities that are not allowed by the plan
                if (in_array('voice', $validatedData['capabilities']) && !$plan->can_use_ai_voice) {
                    $validatedData['capabilities'] = array_diff($validatedData['capabilities'], ['voice']);
                }
                
                if (in_array('image_upload', $validatedData['capabilities']) && !$plan->can_use_ai_image_upload) {
                    $validatedData['capabilities'] = array_diff($validatedData['capabilities'], ['image_upload']);
                }
            }
        }
        
        $agent->update($validatedData);
        
        return response()->json([
            'success' => true,
            'message' => 'AI Agent updated successfully',
            'data' => $agent
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $agent = AiAgent::findOrFail($id);
        
        // Check user has access to this agent's business
        $user = request()->user();
        if ($user->role !== 'superadmin' && !$user->businesses()->where('businesses.id', $agent->business_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this AI agent'
            ], 403);
        }
        
        // Delete avatar if exists
        if ($agent->avatar) {
            Storage::disk('public')->delete($agent->avatar);
        }
        
        $agent->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'AI Agent deleted successfully'
        ]);
    }
}
