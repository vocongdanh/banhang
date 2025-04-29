<?php

namespace App\Http\Controllers;

use App\Models\AIAgent;
use App\Models\AIConversation;
use App\Models\AIMessage;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AIConversationController extends Controller
{
    /**
     * Get conversations for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|string|exists:businesses,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $businessId = $request->input('business_id');
        $userId = Auth::id();

        // Check if the user has access to this business
        $userHasAccess = Business::where('id', $businessId)
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('users', function ($q) use ($userId) {
                        $q->where('users.id', $userId);
                    });
            })
            ->exists();

        if (!$userHasAccess) {
            return response()->json(['error' => 'Unauthorized access to this business'], 403);
        }

        // Get conversations for this user and business
        $conversations = AIConversation::with(['aiAgent'])
            ->where('user_id', $userId)
            ->where('business_id', $businessId)
            ->orderBy('last_message_at', 'desc')
            ->get();

        // Get last message for each conversation
        foreach ($conversations as $conversation) {
            $lastMessage = AIMessage::where('ai_conversation_id', $conversation->id)
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($lastMessage) {
                $conversation->last_message = [
                    'content' => Str::limit(strip_tags($lastMessage->content), 100),
                    'role' => $lastMessage->role
                ];
            }
        }

        return response()->json(['data' => $conversations]);
    }

    /**
     * Create a new conversation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ai_agent_id' => 'required|string|exists:ai_agents,id',
            'business_id' => 'required|string|exists:businesses,id',
            'title' => 'nullable|string|max:255',
            'initial_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $businessId = $request->input('business_id');
        $userId = Auth::id();

        // Check if the user has access to this business
        $userHasAccess = Business::where('id', $businessId)
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('users', function ($q) use ($userId) {
                        $q->where('users.id', $userId);
                    });
            })
            ->exists();

        if (!$userHasAccess) {
            return response()->json(['error' => 'Unauthorized access to this business'], 403);
        }

        // Verify AI agent exists and is accessible to this business
        $agent = AIAgent::where('id', $request->input('ai_agent_id'))
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)
                    ->orWhere('is_public', true);
            })
            ->first();

        if (!$agent) {
            return response()->json(['error' => 'AI Agent not found or not accessible'], 404);
        }

        // Create the conversation
        $title = $request->input('title') ?: 'Cuộc trò chuyện với ' . $agent->name;
        
        $conversation = AIConversation::create([
            'id' => (string) Str::uuid(),
            'title' => $title,
            'ai_agent_id' => $request->input('ai_agent_id'),
            'user_id' => $userId,
            'business_id' => $businessId,
            'last_message_at' => now(),
        ]);

        // If initial message is provided, create the message
        if ($request->has('initial_message') && $request->input('initial_message')) {
            $message = AIMessage::create([
                'ai_conversation_id' => $conversation->id,
                'user_id' => $userId,
                'role' => 'user',
                'content' => $request->input('initial_message'),
                'is_read' => true,
            ]);

            // Get AI response
            $aiService = app(\App\Services\AI\AIServiceFactory::class)->create();
            $business = Business::findOrFail($businessId);
            $assistantId = $business->assistant_id;

            if (!$assistantId) {
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

                $business->assistant_id = $assistantId;
                $business->save();
            }

            // Get AI response
            $response = $aiService->sendMessage(
                $assistantId,
                $request->input('initial_message'),
                [],
                [
                    'conversation_id' => (string) $conversation->id,
                    'business_id' => (string) $business->id
                ]
            );

            // Create AI message
            $aiMessage = AIMessage::create([
                'ai_conversation_id' => $conversation->id,
                'user_id' => null,
                'role' => 'assistant',
                'content' => $response['content'],
                'is_read' => false,
            ]);

            // Update conversation's last_message_at
            $conversation->last_message_at = now();
            $conversation->save();

            // Lưu thông tin thread vào conversation nếu cần
            if (!empty($response['thread_id']) && !$conversation->metadata) {
                $conversation->metadata = ['thread_id' => $response['thread_id']];
                $conversation->save();
            }
        }

        return response()->json([
            'message' => 'Conversation created successfully',
            'data' => $conversation->load('aiAgent')
        ], 201);
    }

    /**
     * Get a specific conversation with messages.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userId = Auth::id();
        
        $conversation = AIConversation::with(['aiAgent'])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
        
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Check if user has access to the associated business
        $businessId = $conversation->business_id;
        $userHasAccess = Business::where('id', $businessId)
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('users', function ($q) use ($userId) {
                        $q->where('users.id', $userId);
                    });
            })
            ->exists();

        if (!$userHasAccess) {
            return response()->json(['error' => 'Unauthorized access to this conversation'], 403);
        }

        // Get messages for this conversation
        $messages = AIMessage::where('ai_conversation_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages
        ]);
    }

    /**
     * Update a conversation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        
        $conversation = AIConversation::where('id', $id)
            ->where('user_id', $userId)
            ->first();
        
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Check if user has access to the associated business
        $businessId = $conversation->business_id;
        $userHasAccess = Business::where('id', $businessId)
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('users', function ($q) use ($userId) {
                        $q->where('users.id', $userId);
                    });
            })
            ->exists();

        if (!$userHasAccess) {
            return response()->json(['error' => 'Unauthorized access to this conversation'], 403);
        }

        $conversation->title = $request->input('title');
        $conversation->save();

        return response()->json([
            'message' => 'Conversation updated successfully',
            'data' => $conversation
        ]);
    }

    /**
     * Delete a conversation.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userId = Auth::id();
        
        $conversation = AIConversation::where('id', $id)
            ->where('user_id', $userId)
            ->first();
        
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Check if user has access to the associated business
        $businessId = $conversation->business_id;
        $userHasAccess = Business::where('id', $businessId)
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('users', function ($q) use ($userId) {
                        $q->where('users.id', $userId);
                    });
            })
            ->exists();

        if (!$userHasAccess) {
            return response()->json(['error' => 'Unauthorized access to this conversation'], 403);
        }

        // Delete associated messages first
        AIMessage::where('ai_conversation_id', $id)->delete();
        
        // Delete the conversation
        $conversation->delete();

        return response()->json([
            'message' => 'Conversation deleted successfully'
        ]);
    }
}
