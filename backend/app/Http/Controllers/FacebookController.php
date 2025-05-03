<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\VectorStoreService;
use Carbon\Carbon;
use FacebookAds\Api;
use FacebookAds\Logger\CurlLogger;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\Page;
use FacebookAds\Object\Campaign;

class FacebookController extends Controller
{
    protected $vectorStoreService;
    protected $appId;
    protected $appSecret;
    protected $redirectUri;
    
    public function __construct(VectorStoreService $vectorStoreService = null)
    {
        $this->vectorStoreService = $vectorStoreService;
        $this->appId = env('FACEBOOK_APP_ID', 'demo_app_id');
        $this->appSecret = env('FACEBOOK_APP_SECRET', 'demo_app_secret');
        $this->redirectUri = env('FACEBOOK_REDIRECT_URI', url('/api/facebook/callback'));
    }

    /**
     * Generate Facebook login URL
     */
    public function getLoginUrl(Request $request)
    {
        // Permissions mapping, can be modified based on checkboxes selected by user
        $permissionsMap = [
            'messenger' => ['pages_messaging', 'pages_messaging_subscriptions'],
            'posts' => ['pages_read_engagement', 'pages_read_user_content'],
            'audience' => ['pages_show_list', 'pages_manage_metadata'],
            'ads' => ['ads_read', 'ads_management', 'business_management']
        ];
        
        // Get the selected permissions from request
        // Laravel automatically converts permission_types[] to an array
        $selectedTypes = $request->input('permission_types', []);
        
        // If no permissions were selected or the array is empty, use all permissions
        if (empty($selectedTypes)) {
            $selectedTypes = array_keys($permissionsMap);
        }
        
        // Log the selected types for debugging
        Log::info('Selected Facebook permission types', ['types' => $selectedTypes]);
        
        // Flatten the permissions array
        $permissions = [];
        foreach ($selectedTypes as $type) {
            if (isset($permissionsMap[$type])) {
                $permissions = array_merge($permissions, $permissionsMap[$type]);
            }
        }
        
        // Ensure no duplicate permissions
        $permissions = array_unique($permissions);
        
        $loginUrl = "https://www.facebook.com/v18.0/dialog/oauth?"
            . "client_id={$this->appId}"
            . "&redirect_uri=" . urlencode($this->redirectUri)
            . "&scope=" . implode(',', $permissions)
            . "&state=" . csrf_token()
            . "&response_type=code";
        
        return response()->json([
            'login_url' => $loginUrl
        ]);
    }
    
    /**
     * Handle Facebook OAuth callback
     */
    public function handleCallback(Request $request)
    {
        if (!$request->has('code')) {
            Log::error('Facebook callback error', [
                'error' => $request->input('error'),
                'error_reason' => $request->input('error_reason'),
                'error_description' => $request->input('error_description')
            ]);
            
            return redirect('/data/facebook?error=auth_failed');
        }
        
        // Get the authorization code
        $code = $request->input('code');
        
        try {
            // Exchange the code for an access token
            $response = Http::post('https://graph.facebook.com/v18.0/oauth/access_token', [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'redirect_uri' => $this->redirectUri,
                'code' => $code
            ]);
            
            $responseData = $response->json();
            
            if (!isset($responseData['access_token'])) {
                Log::error('Facebook token exchange failed', $responseData);
                return redirect('/data/facebook?error=token_exchange_failed');
            }
            
            $accessToken = $responseData['access_token'];
            $expiresIn = $responseData['expires_in'] ?? (60 * 60 * 24 * 60); // Default to 60 days
            
            // Get long-lived token
            $response = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'fb_exchange_token' => $accessToken
            ]);
            
            $longLivedTokenData = $response->json();
            
            if (isset($longLivedTokenData['access_token'])) {
                $accessToken = $longLivedTokenData['access_token'];
                $expiresIn = $longLivedTokenData['expires_in'] ?? (60 * 60 * 24 * 60);
            }
            
            // Get granted permissions
            $permissionsResponse = Http::get("https://graph.facebook.com/v18.0/me/permissions", [
                'access_token' => $accessToken
            ]);
            
            $permissionsData = $permissionsResponse->json();
            $permissions = [];
            
            if (isset($permissionsData['data'])) {
                foreach ($permissionsData['data'] as $permission) {
                    if ($permission['status'] === 'granted') {
                        $permissions[] = $permission['permission'];
                    }
                }
            }
            
            // Store the token for the user
            $user = Auth::user();
            if ($user) {
                // Store in database
                DB::table('facebook_tokens')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'access_token' => $accessToken,
                        'expires_at' => Carbon::now()->addSeconds($expiresIn),
                        'permissions' => json_encode($permissions),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]
                );
                
                // Get and store page tokens if available
                $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
                    'access_token' => $accessToken
                ]);
                
                $pagesData = $pagesResponse->json();
                $pageTokens = [];
                
                if (isset($pagesData['data'])) {
                    foreach ($pagesData['data'] as $page) {
                        $pageTokens[$page['id']] = [
                            'name' => $page['name'],
                            'access_token' => $page['access_token'],
                            'category' => $page['category'] ?? null
                        ];
                    }
                    
                    if (!empty($pageTokens)) {
                        DB::table('facebook_tokens')
                            ->where('user_id', $user->id)
                            ->update([
                                'page_tokens' => json_encode($pageTokens),
                                'updated_at' => Carbon::now()
                            ]);
                    }
                }
                
                // Fetch and store data if we have appropriate permissions
                $this->fetchAndStoreData($user->id, $accessToken);
            }
            
            return redirect('/data/facebook?status=connected');
            
        } catch (\Exception $e) {
            Log::error('Error exchanging Facebook code for token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect('/data/facebook?error=token_exchange_failed');
        }
    }
    
    /**
     * Get Facebook connection status
     */
    public function getStatus(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'connected' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        // Check if user has a valid Facebook token
        $tokenRecord = DB::table('facebook_tokens')
            ->where('user_id', $user->id)
            ->first();
        
        if ($tokenRecord && Carbon::parse($tokenRecord->expires_at)->isFuture()) {
            return response()->json([
                'connected' => true,
                'expires_at' => $tokenRecord->expires_at,
                'pages' => $this->getConnectedPages($tokenRecord->access_token)
            ]);
        }
        
        return response()->json([
            'connected' => false,
            'message' => 'No valid Facebook token found'
        ]);
    }
    
    /**
     * Get messenger conversations
     */
    public function getMessengerConversations(Request $request)
    {
        // In a real implementation, get the user's token and fetch from Graph API
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $tokenRecord = $this->getUserToken($user->id);
        if (!$tokenRecord) {
            return response()->json(['error' => 'No Facebook connection found'], 400);
        }
        
        // This would fetch real data from Facebook Graph API in production
        // Example: GET /page-id/conversations
        return response()->json([
            'conversations' => [
                // Example data
                [
                    'id' => 'conv123',
                    'name' => 'Khách hàng A',
                    'last_message' => 'Tôi muốn hỏi về sản phẩm',
                    'last_updated' => '2023-11-05T10:23:45Z'
                ],
                [
                    'id' => 'conv456',
                    'name' => 'Khách hàng B',
                    'last_message' => 'Làm sao để đặt hàng?',
                    'last_updated' => '2023-11-04T09:12:30Z'
                ]
            ]
        ]);
    }
    
    /**
     * Get Facebook posts
     */
    public function getPosts(Request $request)
    {
        // In real implementation, get token and fetch from Graph API
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $tokenRecord = $this->getUserToken($user->id);
        if (!$tokenRecord) {
            return response()->json(['error' => 'No Facebook connection found'], 400);
        }
        
        // This would fetch real data from Facebook Graph API in production
        // Example: GET /page-id/posts
        return response()->json([
            'posts' => [
                // Example data
                [
                    'id' => 'post123',
                    'message' => 'Sản phẩm mới đã có mặt tại cửa hàng!',
                    'created_time' => '2023-11-03T08:30:00Z',
                    'likes' => 45,
                    'comments' => 12,
                    'shares' => 5
                ],
                [
                    'id' => 'post456',
                    'message' => 'Khuyến mãi đặc biệt cuối tuần này',
                    'created_time' => '2023-11-01T14:15:00Z',
                    'likes' => 78,
                    'comments' => 23,
                    'shares' => 15
                ]
            ]
        ]);
    }
    
    /**
     * Get audience insights
     */
    public function getAudienceInsights(Request $request)
    {
        // In real implementation, get token and fetch from Graph API
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $tokenRecord = $this->getUserToken($user->id);
        if (!$tokenRecord) {
            return response()->json(['error' => 'No Facebook connection found'], 400);
        }
        
        // This would fetch real data from Facebook Graph API in production
        // Example: GET /page-id/insights
        return response()->json([
            'audience' => [
                'total_followers' => 1250,
                'new_followers_last_week' => 28,
                'demographics' => [
                    'gender' => [
                        'male' => 42,
                        'female' => 56,
                        'other' => 2
                    ],
                    'age_groups' => [
                        '18-24' => 15,
                        '25-34' => 38,
                        '35-44' => 22,
                        '45-54' => 16,
                        '55+' => 9
                    ],
                    'locations' => [
                        'Hà Nội' => 35,
                        'TP.HCM' => 30,
                        'Đà Nẵng' => 12,
                        'Other' => 23
                    ]
                ]
            ]
        ]);
    }
    
    /**
     * Get analytics data
     */
    public function getAnalytics(Request $request)
    {
        // In real implementation, get token and fetch from Graph API
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $tokenRecord = $this->getUserToken($user->id);
        if (!$tokenRecord) {
            return response()->json(['error' => 'No Facebook connection found'], 400);
        }
        
        // This would fetch real data from Facebook Graph API in production
        // Example: GET /page-id/insights with metrics parameter
        return response()->json([
            'engagement' => [
                'total_reach' => 5430,
                'total_impressions' => 8750,
                'post_engagement_rate' => 3.2,
                'weekly_trends' => [
                    'dates' => ['11/01', '11/02', '11/03', '11/04', '11/05', '11/06', '11/07'],
                    'reaches' => [420, 380, 520, 650, 720, 590, 480],
                    'engagements' => [32, 28, 45, 52, 68, 43, 35]
                ]
            ]
        ]);
    }
    
    /**
     * Get Ads data using the Facebook Business SDK
     */
    public function getAdsData(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $tokenRecord = $this->getUserToken($user->id);
        if (!$tokenRecord) {
            return response()->json(['error' => 'No Facebook connection found'], 400);
        }
        
        try {
            $accessToken = $tokenRecord->access_token;
            
            // For demo purposes, we'll return example data
            // In production, you would use the actual API
            if (app()->environment('production') && $this->appId !== 'demo_app_id') {
                // Initialize the Facebook Ads API
                Api::init($this->appId, $this->appSecret, $accessToken);
                
                // Create a new Logger
                $api = Api::instance();
                $api->setLogger(new CurlLogger());
                
                // Get account info
                $me = (new \FacebookAds\Object\User('me'))->getSelf(['id', 'name']);
                
                // Get all ad accounts
                $adAccounts = $me->getAdAccounts(['account_id', 'name', 'account_status']);
                
                $campaigns = [];
                $adSets = [];
                $ads = [];
                
                // For each account, get campaigns, ad sets, and ads
                foreach ($adAccounts as $account) {
                    $accountId = $account->id;
                    $adAccount = new AdAccount($accountId);
                    
                    // Get campaigns for this account
                    $accountCampaigns = $adAccount->getCampaigns(['id', 'name', 'objective', 'status']);
                    
                    foreach ($accountCampaigns as $campaign) {
                        $campaignId = $campaign->id;
                        
                        // Get insights for the campaign
                        $insights = $campaign->getInsights(
                            ['impressions', 'clicks', 'spend', 'ctr'],
                            [
                                'time_range' => ['since' => date('Y-m-d', strtotime('-30 days')), 'until' => date('Y-m-d')]
                            ]
                        );
                        
                        $campaignData = $campaign->exportAllData();
                        
                        if (count($insights) > 0) {
                            $campaignData['insights'] = $insights[0]->exportAllData();
                        }
                        
                        $campaigns[] = $campaignData;
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'user' => $me->exportAllData(),
                    'accounts' => $adAccounts->getArrayCopy(),
                    'campaigns' => $campaigns,
                    'ad_sets' => $adSets,
                    'ads' => $ads
                ]);
            } else {
                // Return example data for testing
                return response()->json([
                    'campaigns' => [
                        [
                            'id' => 'camp_123',
                            'name' => 'Summer Sale 2023',
                            'status' => 'ACTIVE',
                            'daily_budget' => 5000,
                            'lifetime_budget' => 150000,
                            'start_time' => '2023-06-01T00:00:00Z',
                            'end_time' => '2023-08-31T23:59:59Z',
                            'insights' => [
                                'impressions' => 24500,
                                'clicks' => 1200,
                                'ctr' => 4.9,
                                'spend' => 4200
                            ]
                        ],
                        [
                            'id' => 'camp_456',
                            'name' => 'Fall Collection Launch',
                            'status' => 'ACTIVE',
                            'daily_budget' => 7500,
                            'lifetime_budget' => 225000,
                            'start_time' => '2023-09-01T00:00:00Z',
                            'end_time' => '2023-11-30T23:59:59Z',
                            'insights' => [
                                'impressions' => 18200,
                                'clicks' => 950,
                                'ctr' => 5.2,
                                'spend' => 3800
                            ]
                        ]
                    ],
                    'demographics' => [
                        'age_gender' => [
                            [
                                'age_range' => '18-24',
                                'gender' => 'male',
                                'percentage' => 15
                            ],
                            [
                                'age_range' => '18-24',
                                'gender' => 'female',
                                'percentage' => 20
                            ],
                            [
                                'age_range' => '25-34',
                                'gender' => 'male',
                                'percentage' => 18
                            ],
                            [
                                'age_range' => '25-34',
                                'gender' => 'female',
                                'percentage' => 25
                            ],
                            [
                                'age_range' => '35-44',
                                'gender' => 'male',
                                'percentage' => 10
                            ],
                            [
                                'age_range' => '35-44',
                                'gender' => 'female',
                                'percentage' => 12
                            ]
                        ],
                        'locations' => [
                            [
                                'country' => 'Vietnam',
                                'city' => 'Ho Chi Minh City',
                                'percentage' => 45
                            ],
                            [
                                'country' => 'Vietnam',
                                'city' => 'Hanoi',
                                'percentage' => 30
                            ],
                            [
                                'country' => 'Vietnam',
                                'city' => 'Da Nang',
                                'percentage' => 15
                            ],
                            [
                                'country' => 'Vietnam',
                                'city' => 'Other',
                                'percentage' => 10
                            ]
                        ]
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching Facebook ads data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error fetching ads data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Disconnect Facebook integration
     */
    public function disconnect(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        try {
            // Delete token from database
            DB::table('facebook_tokens')
                ->where('user_id', $user->id)
                ->delete();
            
            // In real implementation, also revoke the token with Facebook
            // Example: GET /oauth/revoke with token and app info
            
            return response()->json([
                'success' => true,
                'message' => 'Facebook integration disconnected successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error disconnecting Facebook', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error disconnecting Facebook: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sync Facebook data to vector store
     */
    public function syncToVectorStore(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $tokenRecord = $this->getUserToken($user->id);
        if (!$tokenRecord) {
            return response()->json([
                'success' => false,
                'message' => 'No Facebook connection found'
            ], 400);
        }
        
        try {
            // Trigger data fetch and store
            $result = $this->fetchAndStoreData($user->id, $tokenRecord->access_token);
            
            return response()->json([
                'success' => true,
                'message' => 'Data synced successfully',
                'sync_details' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error syncing Facebook data', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error syncing data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get user's Facebook token
     */
    private function getUserToken($userId)
    {
        return DB::table('facebook_tokens')
            ->where('user_id', $userId)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }
    
    /**
     * Get connected pages for a Facebook token
     */
    private function getConnectedPages($accessToken)
    {
        // In real implementation, call the Graph API
        // Example: GET /me/accounts with the token
        
        // For demo, return mock data
        return [
            [
                'id' => 'page123',
                'name' => 'My Business Page',
                'category' => 'Shopping & Retail',
                'fan_count' => 2450
            ]
        ];
    }
    
    /**
     * Fetch data from Facebook and store in vector DB
     */
    private function fetchAndStoreData($userId, $accessToken)
    {
        // Skip if vector store service is not available (for testing/demo)
        if (!$this->vectorStoreService) {
            return [
                'status' => 'skipped',
                'reason' => 'Vector store service not available'
            ];
        }
        
        $result = [
            'posts_stored' => 0,
            'messages_stored' => 0,
            'ads_stored' => 0
        ];
        
        try {
            // 1. Fetch posts from Facebook
            // In real implementation: GET /page-id/posts
            $posts = $this->fetchPostsData($accessToken);
            
            // 2. Store posts in vector store
            foreach ($posts as $post) {
                $content = "Facebook Post ({$post['created_time']}): {$post['message']}";
                $metadata = [
                    'source' => 'facebook_post',
                    'post_id' => $post['id'],
                    'created_time' => $post['created_time'],
                    'engagement' => [
                        'likes' => $post['likes'],
                        'comments' => $post['comments'],
                        'shares' => $post['shares']
                    ],
                    'user_id' => $userId
                ];
                
                // Tạo file tạm thời để lưu nội dung
                $tempFilePath = storage_path('app/temp/fb_post_' . $post['id'] . '.txt');
                if (!is_dir(dirname($tempFilePath))) {
                    mkdir(dirname($tempFilePath), 0755, true);
                }
                file_put_contents($tempFilePath, $content);
                
                // Upload file qua Vector Store Service
                $this->vectorStoreService->uploadFile($tempFilePath, [
                    'name' => 'Facebook Post - ' . substr($post['message'], 0, 30) . '...',
                    'type' => 'text/plain',
                    'user_id' => $userId,
                    'data_type' => 'facebook_post',
                    'metadata' => json_encode($metadata)
                ]);
                
                // Xóa file tạm
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }
                
                $result['posts_stored']++;
            }
            
            // 3. Fetch messages from Facebook
            // In real implementation: GET /page-id/conversations and then GET /conversation-id/messages
            $messages = $this->fetchMessagesData($accessToken);
            
            // 4. Store messages in vector store
            foreach ($messages as $message) {
                $content = "Facebook Message ({$message['timestamp']}): {$message['message']}";
                $metadata = [
                    'source' => 'facebook_message',
                    'conversation_id' => $message['conversation_id'],
                    'sender' => $message['sender_name'],
                    'timestamp' => $message['timestamp'],
                    'user_id' => $userId
                ];
                
                // Tạo file tạm thời để lưu nội dung
                $tempFilePath = storage_path('app/temp/fb_msg_' . $message['id'] . '.txt');
                file_put_contents($tempFilePath, $content);
                
                // Upload file qua Vector Store Service
                $this->vectorStoreService->uploadFile($tempFilePath, [
                    'name' => 'Facebook Message - ' . $message['sender_name'],
                    'type' => 'text/plain',
                    'user_id' => $userId,
                    'data_type' => 'facebook_message',
                    'metadata' => json_encode($metadata)
                ]);
                
                // Xóa file tạm
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }
                
                $result['messages_stored']++;
            }
            
            // 5. Fetch ads data from Facebook
            // In real implementation: GET /act_<AD_ACCOUNT_ID>/campaigns
            $adsCampaigns = $this->fetchAdsData($accessToken);
            
            // 6. Store ads data in vector store
            foreach ($adsCampaigns as $campaign) {
                $content = "Facebook Ad Campaign ({$campaign['start_time']} - {$campaign['end_time']}): {$campaign['name']}";
                $metadata = [
                    'source' => 'facebook_ad',
                    'campaign_id' => $campaign['id'],
                    'status' => $campaign['status'],
                    'budget' => $campaign['budget'],
                    'spent' => $campaign['spent'],
                    'period' => [
                        'start' => $campaign['start_time'],
                        'end' => $campaign['end_time']
                    ],
                    'results' => $campaign['results'],
                    'user_id' => $userId
                ];
                
                // Tạo file tạm thời để lưu nội dung
                $tempFilePath = storage_path('app/temp/fb_ad_' . $campaign['id'] . '.txt');
                file_put_contents($tempFilePath, $content);
                
                // Upload file qua Vector Store Service
                $this->vectorStoreService->uploadFile($tempFilePath, [
                    'name' => 'Facebook Ad - ' . $campaign['name'],
                    'type' => 'text/plain',
                    'user_id' => $userId,
                    'data_type' => 'facebook_ad',
                    'metadata' => json_encode($metadata)
                ]);
                
                // Xóa file tạm
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }
                
                $result['ads_stored']++;
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Error storing Facebook data in vector store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Mock fetch posts from Facebook
     */
    private function fetchPostsData($accessToken)
    {
        // In real implementation, call the Graph API
        // For demo, return mock data
        return [
            [
                'id' => 'post123',
                'message' => 'Sản phẩm mới đã có mặt tại cửa hàng! Ghé thăm để khám phá bộ sưu tập mùa đông mới nhất với nhiều mẫu áo khoác, quần jeans và phụ kiện thời trang.',
                'created_time' => '2023-11-03T08:30:00Z',
                'likes' => 45,
                'comments' => 12,
                'shares' => 5
            ],
            [
                'id' => 'post456',
                'message' => 'Khuyến mãi đặc biệt cuối tuần này! Giảm giá 30% cho tất cả các sản phẩm mùa hè. Đừng bỏ lỡ cơ hội sở hữu những sản phẩm chất lượng với giá cực tốt.',
                'created_time' => '2023-11-01T14:15:00Z',
                'likes' => 78,
                'comments' => 23,
                'shares' => 15
            ]
        ];
    }
    
    /**
     * Mock fetch messages from Facebook
     */
    private function fetchMessagesData($accessToken)
    {
        // In real implementation, call the Graph API
        // For demo, return mock data
        return [
            [
                'id' => 'msg123',
                'conversation_id' => 'conv123',
                'message' => 'Tôi muốn hỏi về sản phẩm áo khoác mã AK102, còn hàng size L không ạ?',
                'sender_name' => 'Khách hàng A',
                'timestamp' => '2023-11-05T10:23:45Z'
            ],
            [
                'id' => 'msg456',
                'conversation_id' => 'conv456',
                'message' => 'Làm sao để đặt hàng online và thanh toán qua thẻ tín dụng vậy shop?',
                'sender_name' => 'Khách hàng B',
                'timestamp' => '2023-11-04T09:12:30Z'
            ]
        ];
    }
    
    /**
     * Mock fetch ads data from Facebook
     */
    private function fetchAdsData($accessToken)
    {
        // In real implementation, call the Marketing API
        // For demo, return mock data
        return [
            [
                'id' => 'camp123',
                'name' => 'Quảng cáo sản phẩm mới - Bộ sưu tập Thu Đông 2023',
                'status' => 'ACTIVE',
                'budget' => 2000000,
                'spent' => 1850000,
                'start_time' => '2023-10-20T00:00:00Z',
                'end_time' => '2023-12-25T23:59:59Z',
                'results' => [
                    'reach' => 78500,
                    'impressions' => 125600,
                    'clicks' => 3450,
                    'ctr' => 2.75,
                    'conversions' => 145
                ]
            ],
            [
                'id' => 'camp456',
                'name' => 'Khuyến mãi cuối năm - Giảm giá 30% toàn bộ sản phẩm',
                'status' => 'ACTIVE',
                'budget' => 3500000,
                'spent' => 2100000,
                'start_time' => '2023-11-15T00:00:00Z',
                'end_time' => '2023-12-31T23:59:59Z',
                'results' => [
                    'reach' => 65200,
                    'impressions' => 89400,
                    'clicks' => 2860,
                    'ctr' => 3.2,
                    'conversions' => 118
                ]
            ]
        ];
    }
} 