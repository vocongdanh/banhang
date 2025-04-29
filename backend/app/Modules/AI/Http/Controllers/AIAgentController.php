<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AIAgent;
use App\Modules\AI\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AIAgentController extends Controller
{
    protected $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Display a listing of the AI agents.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AIAgent::query();

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $agents = $query->paginate(10);

        return response()->json($agents);
    }

    /**
     * Store a newly created AI agent.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:customer_service,business_agent',
            'model' => 'required|string',
            'temperature' => 'required|numeric|between:0,2',
            'max_tokens' => 'required|integer|min:1',
            'system_prompt' => 'nullable|string',
            'metadata' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $agent = AIAgent::create($request->all());

        return response()->json($agent, 201);
    }

    /**
     * Display the specified AI agent.
     */
    public function show(AIAgent $agent): JsonResponse
    {
        return response()->json($agent);
    }

    /**
     * Update the specified AI agent.
     */
    public function update(Request $request, AIAgent $agent): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|in:customer_service,business_agent',
            'model' => 'sometimes|required|string',
            'temperature' => 'sometimes|required|numeric|between:0,2',
            'max_tokens' => 'sometimes|required|integer|min:1',
            'system_prompt' => 'nullable|string',
            'metadata' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $agent->update($request->all());

        return response()->json($agent);
    }

    /**
     * Remove the specified AI agent.
     */
    public function destroy(AIAgent $agent): JsonResponse
    {
        $agent->delete();

        return response()->json(null, 204);
    }

    /**
     * Generate response from AI agent.
     */
    public function generateResponse(Request $request, AIAgent $agent): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string',
            'context' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $response = $this->aiService->generateResponse(
                $agent,
                $request->prompt,
                $request->context ?? []
            );

            return response()->json(['response' => $response]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
} 