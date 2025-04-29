<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\Models\AIAgent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $openaiApiKey;
    protected $qdrantHost;

    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.api_key');
        $this->qdrantHost = config('services.qdrant.host');
    }

    /**
     * Generate response from AI model
     */
    public function generateResponse(AIAgent $agent, string $prompt, array $context = []): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $agent->model,
                'messages' => $this->prepareMessages($agent, $prompt, $context),
                'temperature' => $agent->temperature,
                'max_tokens' => $agent->max_tokens,
            ]);

            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'];
            }

            Log::error('OpenAI API Error', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new \Exception('Failed to generate AI response');
        } catch (\Exception $e) {
            Log::error('AI Service Error', [
                'error' => $e->getMessage(),
                'agent_id' => $agent->id,
            ]);

            throw $e;
        }
    }

    /**
     * Prepare messages for OpenAI API
     */
    protected function prepareMessages(AIAgent $agent, string $prompt, array $context): array
    {
        $messages = [];

        // Add system prompt
        if ($agent->system_prompt) {
            $messages[] = [
                'role' => 'system',
                'content' => $agent->system_prompt,
            ];
        }

        // Add context if available
        if (!empty($context)) {
            $messages[] = [
                'role' => 'system',
                'content' => 'Context: ' . json_encode($context),
            ];
        }

        // Add user prompt
        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        return $messages;
    }

    /**
     * Search in vector store
     */
    public function searchVectorStore(string $collection, string $query, int $limit = 5): array
    {
        try {
            $response = Http::post("{$this->qdrantHost}/collections/{$collection}/points/search", [
                'vector' => $this->getEmbedding($query),
                'limit' => $limit,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Failed to search vector store');
        } catch (\Exception $e) {
            Log::error('Vector Store Search Error', [
                'error' => $e->getMessage(),
                'collection' => $collection,
            ]);

            throw $e;
        }
    }

    /**
     * Get embedding for text
     */
    protected function getEmbedding(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/embeddings', [
            'model' => 'text-embedding-ada-002',
            'input' => $text,
        ]);

        if ($response->successful()) {
            return $response->json()['data'][0]['embedding'];
        }

        throw new \Exception('Failed to get embedding');
    }
} 