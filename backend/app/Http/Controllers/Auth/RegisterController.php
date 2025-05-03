<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use OpenAI\Laravel\Facades\OpenAI;
use App\Services\VectorStoreService;
use Exception;
use Carbon\Carbon;

class RegisterController extends Controller
{
    protected $vectorStoreService;
    
    public function __construct(VectorStoreService $vectorStoreService = null)
    {
        $this->vectorStoreService = $vectorStoreService;
    }
    
    /**
     * Register a new user and business
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'business_name' => 'required|string|max:255',
            'business_description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user', // Default role
            ]);
            
            // Create assistant in OpenAI
            $assistantId = $this->createOpenAIAssistant($request->business_name);
            
            // Create vector store
            $vectorStoreId = $this->createVectorStore($request->business_name);
            
            // Create business
            $business = Business::create([
                'name' => $request->business_name,
                'description' => $request->business_description,
                'owner_id' => $user->id,
                'assistant_id' => $assistantId,
                'vector_store_id' => $vectorStoreId,
                'ai_settings' => [
                    'model' => 'gpt-3.5-turbo',
                    'temperature' => 0.7,
                    'system_prompt' => 'Bạn là trợ lý AI của ' . $request->business_name . '. Hãy cung cấp thông tin chính xác và hữu ích.',
                ],
            ]);
            
            // Associate user with business
            $business->users()->attach($user->id, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
            
            // Create token for API access
            $token = $user->createToken('auth-token')->plainTextToken;
            
            DB::commit();
            
            return response()->json([
                'message' => 'Đăng ký thành công',
                'user' => $user->load('businesses'),
                'token' => $token,
            ], 201);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Đăng ký thất bại: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Redirect to Facebook OAuth
     */
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }
    
    /**
     * Handle Facebook callback
     */
    public function handleFacebookCallback(Request $request)
    {
        if ($request->error) {
            return redirect('/register?error=facebook_auth_failed');
        }
        
        try {
            $facebookUser = Socialite::driver('facebook')->user();
            
            // Check if user exists
            $user = User::where('email', $facebookUser->getEmail())->first();
            
            if ($user) {
                // User exists, update Facebook details
                $user->update([
                    'facebook_id' => $facebookUser->getId(),
                ]);
                
                // Generate token
                $token = $user->createToken('auth-token')->plainTextToken;
                
                // Store Facebook access token
                $this->storeFacebookToken($user->id, $facebookUser->token);
                
                return redirect('/login/oauth-callback?token=' . $token);
            } else {
                // Store Facebook details in session for registration completion
                session([
                    'oauth_provider' => 'facebook',
                    'oauth_id' => $facebookUser->getId(),
                    'oauth_token' => $facebookUser->token,
                    'oauth_name' => $facebookUser->getName(),
                    'oauth_email' => $facebookUser->getEmail(),
                ]);
                
                return redirect('/register/complete-oauth');
            }
            
        } catch (Exception $e) {
            Log::error('Facebook callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect('/register?error=facebook_callback_error');
        }
    }
    
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }
    
    /**
     * Handle Google callback
     */
    public function handleGoogleCallback(Request $request)
    {
        if ($request->error) {
            return redirect('/register?error=google_auth_failed');
        }
        
        try {
            $googleUser = Socialite::driver('google')->user();
            
            // Check if user exists
            $user = User::where('email', $googleUser->getEmail())->first();
            
            if ($user) {
                // User exists, update Google details
                $user->update([
                    'google_id' => $googleUser->getId(),
                ]);
                
                // Generate token
                $token = $user->createToken('auth-token')->plainTextToken;
                
                return redirect('/login/oauth-callback?token=' . $token);
            } else {
                // Store Google details in session for registration completion
                session([
                    'oauth_provider' => 'google',
                    'oauth_id' => $googleUser->getId(),
                    'oauth_token' => $googleUser->token,
                    'oauth_name' => $googleUser->getName(),
                    'oauth_email' => $googleUser->getEmail(),
                ]);
                
                return redirect('/register/complete-oauth');
            }
            
        } catch (Exception $e) {
            Log::error('Google callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect('/register?error=google_callback_error');
        }
    }
    
    /**
     * Complete OAuth registration (requires business info)
     */
    public function completeOAuthRegistration(Request $request)
    {
        // Validate OAuth session data exists
        if (!session('oauth_provider') || !session('oauth_id') || !session('oauth_email')) {
            return response()->json([
                'message' => 'Dữ liệu OAuth không hợp lệ'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'business_description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Create user with OAuth data
            $user = User::create([
                'name' => session('oauth_name'),
                'email' => session('oauth_email'),
                'password' => Hash::make(uniqid()), // Random password as user will login via OAuth
                'role' => 'user',
                session('oauth_provider') . '_id' => session('oauth_id'),
            ]);
            
            // Create assistant in OpenAI
            $assistantId = $this->createOpenAIAssistant($request->business_name);
            
            // Create vector store
            $vectorStoreId = $this->createVectorStore($request->business_name);
            
            // Create business
            $business = Business::create([
                'name' => $request->business_name,
                'description' => $request->business_description,
                'owner_id' => $user->id,
                'assistant_id' => $assistantId,
                'vector_store_id' => $vectorStoreId,
                'ai_settings' => [
                    'model' => 'gpt-3.5-turbo',
                    'temperature' => 0.7,
                    'system_prompt' => 'Bạn là trợ lý AI của ' . $request->business_name . '. Hãy cung cấp thông tin chính xác và hữu ích.',
                ],
            ]);
            
            // Associate user with business
            $business->users()->attach($user->id, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
            
            // Store Facebook token if available
            if (session('oauth_provider') === 'facebook' && session('oauth_token')) {
                $this->storeFacebookToken($user->id, session('oauth_token'));
            }
            
            // Create token for API access
            $token = $user->createToken('auth-token')->plainTextToken;
            
            // Clear oauth session data
            session()->forget([
                'oauth_provider', 'oauth_id', 'oauth_token', 'oauth_name', 'oauth_email'
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Đăng ký thành công',
                'user' => $user->load('businesses'),
                'token' => $token,
            ], 201);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('OAuth registration completion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Đăng ký thất bại: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Store Facebook access token
     */
    private function storeFacebookToken($userId, $accessToken)
    {
        try {
            // Get token expiration - usually 60 days for long-lived tokens
            $expiresIn = 60 * 24 * 60 * 60; // 60 days in seconds
            
            // Store token in database
            DB::table('facebook_tokens')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'access_token' => $accessToken,
                    'expires_at' => Carbon::now()->addSeconds($expiresIn),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]
            );
            
            return true;
        } catch (Exception $e) {
            Log::error('Error storing Facebook token', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Create OpenAI Assistant
     */
    private function createOpenAIAssistant($businessName)
    {
        try {
            // In production, create a real assistant
            if (env('APP_ENV') === 'production') {
                $assistant = OpenAI::assistants()->create([
                    'name' => 'Assistant cho ' . $businessName,
                    'instructions' => 'Bạn là trợ lý AI của ' . $businessName . '. Hãy cung cấp thông tin chính xác và hữu ích.',
                    'model' => 'gpt-3.5-turbo',
                ]);
                
                return $assistant->id;
            }
            
            // For development/testing, return a mock ID
            return 'asst_' . uniqid();
        } catch (Exception $e) {
            Log::error('Error creating OpenAI assistant', [
                'business_name' => $businessName,
                'error' => $e->getMessage()
            ]);
            
            // For fallback, return a mock ID
            return 'asst_' . uniqid();
        }
    }
    
    /**
     * Create Vector Store
     */
    private function createVectorStore($businessName)
    {
        try {
            // If vector store service is available
            if ($this->vectorStoreService) {
                $collection = $this->vectorStoreService->createCollection(
                    str_replace(' ', '_', strtolower($businessName)) . '_' . uniqid()
                );
                
                return $collection['id'] ?? 'vs_' . uniqid();
            }
            
            // For development/testing, return a mock ID
            return 'vs_' . uniqid();
        } catch (Exception $e) {
            Log::error('Error creating vector store', [
                'business_name' => $businessName,
                'error' => $e->getMessage()
            ]);
            
            // For fallback, return a mock ID
            return 'vs_' . uniqid();
        }
    }
} 