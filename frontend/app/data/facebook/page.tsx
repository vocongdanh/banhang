"use client"

import { useState, useEffect } from "react"
import { AppShell } from "@/components/layout/app-shell"
import { 
  Card, 
  CardContent, 
  CardDescription, 
  CardHeader, 
  CardTitle, 
  CardFooter 
} from "@/components/ui/card"
import { 
  Tabs, 
  TabsContent, 
  TabsList, 
  TabsTrigger 
} from "@/components/ui/tabs"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Checkbox } from "@/components/ui/checkbox"
import Link from "next/link"
import { useSearchParams } from "next/navigation"
import { 
  MessageCircle, 
  FileText, 
  Users, 
  BarChart3,
  AlertCircle,
  DollarSign
} from "lucide-react"

export default function FacebookIntegrationPage() {
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [isFacebookConnected, setIsFacebookConnected] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loginUrl, setLoginUrl] = useState('');
  const [messengerData, setMessengerData] = useState<any>(null);
  const [postsData, setPostsData] = useState<any>(null);
  const [audienceData, setAudienceData] = useState<any>(null);
  const [analyticsData, setAnalyticsData] = useState<any>(null);
  const [adsData, setAdsData] = useState<any>(null);
  const [permissions, setPermissions] = useState({
    messenger: true, 
    posts: true, 
    audience: true, 
    ads: true
  });
  
  const searchParams = useSearchParams();
  
  const handlePermissionChange = (type: string, checked: boolean) => {
    setPermissions(prev => ({
      ...prev,
      [type]: checked
    }));
  };

  const getLoginUrl = async () => {
    setIsLoading(true);
    const token = localStorage.getItem('auth_token');
    
    try {
      // Get selected permissions
      const selectedPermissions = Object.entries(permissions)
        .filter(([_, checked]) => checked)
        .map(([type]) => type);
      
      // Create URL search params with array support
      const params = new URLSearchParams();
      selectedPermissions.forEach(permission => {
        params.append('permission_types[]', permission);
      });
      
      const response = await fetch(`/api/facebook/login-url?${params.toString()}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      const data = await response.json();
      
      if (data.login_url) {
        setLoginUrl(data.login_url);
      } else {
        setError('Không thể lấy URL đăng nhập Facebook');
      }
    } catch (err) {
      console.error('Error getting Facebook login URL:', err);
      setError('Lỗi kết nối với máy chủ. Vui lòng thử lại sau.');
    } finally {
      setIsLoading(false);
    }
  };
  
  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    setIsLoggedIn(!!token);
    
    // Check for connection status or callback params
    const status = searchParams?.get('status');
    const errorParam = searchParams?.get('error');
    
    if (status === 'connected') {
      setIsFacebookConnected(true);
    }
    
    if (errorParam) {
      setError('Đã xảy ra lỗi khi kết nối với Facebook. Vui lòng thử lại.');
    }
    
    // Get connection status from API
    if (token) {
      setIsLoading(true);
      fetch('/api/facebook/status', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      })
      .then(res => res.json())
      .then(data => {
        setIsFacebookConnected(data.connected);
        setIsLoading(false);
      })
      .catch(err => {
        console.error('Error checking Facebook status:', err);
        setIsLoading(false);
      });
      
      // Get Facebook login URL
      getLoginUrl();
    }
  }, [searchParams]);
  
  // Load tab data when connected
  useEffect(() => {
    if (isFacebookConnected && isLoggedIn) {
      const token = localStorage.getItem('auth_token');
      setIsLoading(true);
      
      // Helper function to fetch data with error handling
      const fetchData = async (endpoint: string, setter: (data: any) => void) => {
        try {
          const res = await fetch(`/api/facebook/${endpoint}`, {
            headers: {
              'Authorization': `Bearer ${token}`
            }
          });
          
          if (!res.ok) {
            throw new Error(`HTTP error ${res.status}`);
          }
          
          const data = await res.json();
          setter(data);
        } catch (err) {
          console.error(`Error fetching ${endpoint} data:`, err);
        }
      };
      
      // Fetch all data types in parallel
      Promise.all([
        fetchData('messenger', setMessengerData),
        fetchData('posts', setPostsData),
        fetchData('audience', setAudienceData),
        fetchData('analytics', setAnalyticsData),
        fetchData('ads', setAdsData)
      ]).finally(() => {
        setIsLoading(false);
      });
    }
  }, [isFacebookConnected, isLoggedIn]);
  
  const handleDisconnect = () => {
    setIsLoading(true);
    const token = localStorage.getItem('auth_token');
    
    fetch('/api/facebook/disconnect', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        setIsFacebookConnected(false);
        // Clear cached data
        setMessengerData(null);
        setPostsData(null);
        setAudienceData(null);
        setAnalyticsData(null);
        setAdsData(null);
      } else {
        setError('Không thể ngắt kết nối Facebook. Vui lòng thử lại.');
      }
    })
    .catch(err => {
      console.error('Error disconnecting Facebook:', err);
      setError('Đã xảy ra lỗi. Vui lòng thử lại.');
    })
    .finally(() => {
      setIsLoading(false);
    });
  };

  if (!isLoggedIn) {
    return (
      <AppShell>
        <div className="container mx-auto py-6">
          <div className="p-8 bg-yellow-50 border border-yellow-300 rounded-md">
            <h3 className="text-lg font-semibold mb-2">Bạn cần đăng nhập trước</h3>
            <p>Vui lòng <a href="/login" className="text-blue-600 hover:underline">đăng nhập</a> để sử dụng tính năng này.</p>
          </div>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell>
      <div className="p-8">
        <div className="flex items-center mb-6">
          <Link 
            href="/data" 
            className="text-blue-500 hover:text-blue-700 mr-3"
          >
            <span className="inline-block">←</span> Quay lại
          </Link>
          <h1 className="text-3xl font-bold">Kết nối Facebook</h1>
        </div>
        
        {error && (
          <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
            <AlertCircle className="h-5 w-5 text-red-500 mr-2 flex-shrink-0 mt-0.5" />
            <div>
              <p className="text-red-700">{error}</p>
              <button 
                onClick={() => setError(null)} 
                className="text-sm text-red-500 hover:text-red-700 mt-2"
              >
                Đóng
              </button>
            </div>
          </div>
        )}

        {!isFacebookConnected ? (
          <Card className="mb-6">
            <CardHeader>
              <CardTitle>Kết nối với Facebook</CardTitle>
              <CardDescription>
                Kết nối tài khoản Facebook để phân tích tin nhắn và bài đăng
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                <p>
                  Kết nối Facebook sẽ cho phép ứng dụng:
                </p>
                <div className="space-y-2">
                  <div className="flex items-start space-x-2">
                    <Checkbox 
                      id="permission-messenger" 
                      checked={permissions.messenger}
                      onCheckedChange={(checked) => 
                        handlePermissionChange('messenger', checked === true)
                      }
                    />
                    <div className="grid gap-1.5 leading-none">
                      <Label htmlFor="permission-messenger">
                        Đọc tin nhắn Messenger của trang Facebook
                      </Label>
                      <p className="text-xs text-gray-500">Quyền: pages_messaging, pages_messaging_subscriptions</p>
                    </div>
                  </div>
                  <div className="flex items-start space-x-2">
                    <Checkbox 
                      id="permission-posts" 
                      checked={permissions.posts}
                      onCheckedChange={(checked) => 
                        handlePermissionChange('posts', checked === true)
                      }
                    />
                    <div className="grid gap-1.5 leading-none">
                      <Label htmlFor="permission-posts">
                        Đọc bài đăng trên trang Facebook
                      </Label>
                      <p className="text-xs text-gray-500">Quyền: pages_read_engagement, pages_read_user_content</p>
                    </div>
                  </div>
                  <div className="flex items-start space-x-2">
                    <Checkbox 
                      id="permission-audience" 
                      checked={permissions.audience}
                      onCheckedChange={(checked) => 
                        handlePermissionChange('audience', checked === true)
                      }
                    />
                    <div className="grid gap-1.5 leading-none">
                      <Label htmlFor="permission-audience">
                        Đọc thông tin người theo dõi và tương tác
                      </Label>
                      <p className="text-xs text-gray-500">Quyền: pages_show_list, pages_manage_metadata</p>
                    </div>
                  </div>
                  <div className="flex items-start space-x-2">
                    <Checkbox 
                      id="permission-ads" 
                      checked={permissions.ads}
                      onCheckedChange={(checked) => 
                        handlePermissionChange('ads', checked === true)
                      }
                    />
                    <div className="grid gap-1.5 leading-none">
                      <Label htmlFor="permission-ads">
                        Đọc thông tin quảng cáo Facebook
                      </Label>
                      <p className="text-xs text-gray-500">Quyền: ads_read, ads_management, business_management</p>
                    </div>
                  </div>
                </div>

                <div className="bg-blue-50 p-4 rounded-md">
                  <p className="text-sm text-blue-800">
                    Lưu ý: Ứng dụng chỉ phân tích dữ liệu và không lưu trữ nội dung tin nhắn cá nhân. 
                    Dữ liệu được sử dụng duy nhất với mục đích phân tích và cung cấp thông tin chi tiết.
                  </p>
                </div>
              </div>
            </CardContent>
            <CardFooter>
              <Button 
                className="bg-blue-600 hover:bg-blue-700" 
                disabled={!loginUrl || isLoading}
                onClick={() => {
                  // Re-fetch login URL with current permissions before redirecting
                  getLoginUrl().then(() => {
                    if (loginUrl) window.location.href = loginUrl;
                  });
                }}
              >
                {isLoading ? (
                  <span className="flex items-center">
                    <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Đang tải...
                  </span>
                ) : (
                  <span className="flex items-center">
                    <svg className="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M9.19795 21.5H13.198V13.4901H16.8021L17.198 9.50977H13.198V7.5C13.198 6.94772 13.6457 6.5 14.198 6.5H17.198V2.5H14.198C11.4365 2.5 9.19795 4.73858 9.19795 7.5V9.50977H7.19795L6.80206 13.4901H9.19795V21.5Z"></path>
                    </svg>
                    Kết nối với Facebook
                  </span>
                )}
              </Button>
            </CardFooter>
          </Card>
        ) : (
          <div>
            <div className="flex items-center justify-between gap-2 mb-4 p-3 bg-green-50 border border-green-200 rounded-md">
              <div className="flex items-center">
                <div className="h-3 w-3 bg-green-500 rounded-full mr-2"></div>
                <p className="text-green-700">Đã kết nối với Facebook</p>
              </div>
              <div className="flex space-x-2">
                <Button 
                  variant="outline" 
                  size="sm" 
                  className="text-blue-500 border-blue-200 hover:bg-blue-50 hover:text-blue-600"
                  onClick={() => {
                    setIsLoading(true);
                    const token = localStorage.getItem('auth_token');
                    
                    fetch('/api/facebook/sync-to-vector', {
                      method: 'POST',
                      headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                      }
                    })
                    .then(res => res.json())
                    .then(data => {
                      if (data.success) {
                        setError(null);
                        alert(`Đồng bộ dữ liệu thành công!\nĐã lưu: ${data.sync_details?.posts_stored || 0} bài đăng, ${data.sync_details?.messages_stored || 0} tin nhắn, ${data.sync_details?.ads_stored || 0} quảng cáo.`);
                      } else {
                        setError('Không thể đồng bộ dữ liệu: ' + (data.message || 'Lỗi không xác định'));
                      }
                    })
                    .catch(err => {
                      console.error('Error syncing Facebook data:', err);
                      setError('Đã xảy ra lỗi khi đồng bộ dữ liệu.');
                    })
                    .finally(() => {
                      setIsLoading(false);
                    });
                  }}
                  disabled={isLoading}
                >
                  {isLoading ? 'Đang đồng bộ...' : 'Đồng bộ dữ liệu'}
                </Button>
                <Button 
                  variant="outline" 
                  size="sm" 
                  className="text-red-500 border-red-200 hover:bg-red-50 hover:text-red-600"
                  onClick={handleDisconnect}
                  disabled={isLoading}
                >
                  {isLoading ? 'Đang xử lý...' : 'Ngắt kết nối'}
                </Button>
              </div>
            </div>

            <Tabs defaultValue="messenger" className="w-full">
              <TabsList className="grid w-full grid-cols-5">
                <TabsTrigger value="messenger">
                  <MessageCircle className="h-4 w-4 mr-2" />
                  Messenger
                </TabsTrigger>
                <TabsTrigger value="posts">
                  <FileText className="h-4 w-4 mr-2" />
                  Bài đăng
                </TabsTrigger>
                <TabsTrigger value="audience">
                  <Users className="h-4 w-4 mr-2" />
                  Khán giả
                </TabsTrigger>
                <TabsTrigger value="analytics">
                  <BarChart3 className="h-4 w-4 mr-2" />
                  Phân tích
                </TabsTrigger>
                <TabsTrigger value="ads">
                  <DollarSign className="h-4 w-4 mr-2" />
                  Quảng cáo
                </TabsTrigger>
              </TabsList>
              
              <TabsContent value="messenger" className="mt-6">
                <Card>
                  <CardHeader>
                    <CardTitle>Phân tích tin nhắn Messenger</CardTitle>
                    <CardDescription>
                      Phân tích và tổng hợp tin nhắn từ trang Facebook Messenger
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {messengerData ? (
                      <div className="space-y-4">
                        <h3 className="text-lg font-medium">Cuộc trò chuyện gần đây</h3>
                        <div className="divide-y">
                          {messengerData.conversations.map((convo: any) => (
                            <div key={convo.id} className="py-3">
                              <p className="font-medium">{convo.name}</p>
                              <p className="text-sm text-gray-500">{convo.last_message}</p>
                              <p className="text-xs text-gray-400 mt-1">
                                {new Date(convo.last_updated).toLocaleString('vi-VN')}
                              </p>
                            </div>
                          ))}
                        </div>
                      </div>
                    ) : (
                      <div className="py-8 text-center">
                        <p className="text-gray-500">Đang tải dữ liệu...</p>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>
              
              <TabsContent value="posts" className="mt-6">
                <Card>
                  <CardHeader>
                    <CardTitle>Phân tích bài đăng Facebook</CardTitle>
                    <CardDescription>
                      Xem thống kê và phân tích từ bài đăng trên trang Facebook
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {postsData ? (
                      <div className="space-y-4">
                        <h3 className="text-lg font-medium">Bài đăng gần đây</h3>
                        <div className="divide-y">
                          {postsData.posts.map((post: any) => (
                            <div key={post.id} className="py-3">
                              <p className="font-medium">{post.message}</p>
                              <div className="flex gap-4 mt-2">
                                <span className="text-xs flex items-center">
                                  <span className="inline-block w-2 h-2 bg-blue-500 rounded-full mr-1"></span>
                                  {post.likes} thích
                                </span>
                                <span className="text-xs flex items-center">
                                  <span className="inline-block w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                                  {post.comments} bình luận
                                </span>
                                <span className="text-xs flex items-center">
                                  <span className="inline-block w-2 h-2 bg-orange-500 rounded-full mr-1"></span>
                                  {post.shares} chia sẻ
                                </span>
                              </div>
                              <p className="text-xs text-gray-400 mt-1">
                                {new Date(post.created_time).toLocaleString('vi-VN')}
                              </p>
                            </div>
                          ))}
                        </div>
                      </div>
                    ) : (
                      <div className="py-8 text-center">
                        <p className="text-gray-500">Đang tải dữ liệu...</p>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>
              
              <TabsContent value="audience" className="mt-6">
                <Card>
                  <CardHeader>
                    <CardTitle>Phân tích khán giả</CardTitle>
                    <CardDescription>
                      Xem thông tin chi tiết về người theo dõi và tương tác
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {audienceData ? (
                      <div className="space-y-6">
                        <div className="grid grid-cols-2 gap-4">
                          <div className="bg-gray-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-500">Tổng người theo dõi</p>
                            <p className="text-2xl font-bold">{audienceData.audience.total_followers.toLocaleString()}</p>
                          </div>
                          <div className="bg-blue-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-500">Người theo dõi mới (tuần qua)</p>
                            <p className="text-2xl font-bold">{audienceData.audience.new_followers_last_week.toLocaleString()}</p>
                          </div>
                        </div>
                        
                        <div>
                          <h3 className="text-lg font-medium mb-3">Phân bố giới tính</h3>
                          <div className="flex items-center h-8 bg-gray-100 rounded-md overflow-hidden">
                            <div 
                              className="h-full bg-blue-500" 
                              style={{ width: `${audienceData.audience.demographics.gender.male}%` }}
                            ></div>
                            <div 
                              className="h-full bg-pink-500" 
                              style={{ width: `${audienceData.audience.demographics.gender.female}%` }}
                            ></div>
                            <div 
                              className="h-full bg-purple-500" 
                              style={{ width: `${audienceData.audience.demographics.gender.other}%` }}
                            ></div>
                          </div>
                          <div className="flex gap-4 mt-2 text-sm">
                            <span className="flex items-center">
                              <span className="inline-block w-3 h-3 bg-blue-500 rounded-full mr-1"></span>
                              Nam {audienceData.audience.demographics.gender.male}%
                            </span>
                            <span className="flex items-center">
                              <span className="inline-block w-3 h-3 bg-pink-500 rounded-full mr-1"></span>
                              Nữ {audienceData.audience.demographics.gender.female}%
                            </span>
                            <span className="flex items-center">
                              <span className="inline-block w-3 h-3 bg-purple-500 rounded-full mr-1"></span>
                              Khác {audienceData.audience.demographics.gender.other}%
                            </span>
                          </div>
                        </div>
                        
                        <div>
                          <h3 className="text-lg font-medium mb-3">Độ tuổi</h3>
                          <div className="grid grid-cols-5 gap-2">
                            {Object.entries(audienceData.audience.demographics.age_groups).map(([age, percentage]: [string, any]) => (
                              <div key={age} className="text-center">
                                <div className="h-24 bg-gray-100 rounded-md flex flex-col justify-end">
                                  <div 
                                    className="bg-green-500 rounded-md mx-1" 
                                    style={{ height: `${percentage}%` }}
                                  ></div>
                                </div>
                                <p className="text-xs mt-1">{age}</p>
                                <p className="text-xs font-medium">{percentage}%</p>
                              </div>
                            ))}
                          </div>
                        </div>
                      </div>
                    ) : (
                      <div className="py-8 text-center">
                        <p className="text-gray-500">Đang tải dữ liệu...</p>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>
              
              <TabsContent value="analytics" className="mt-6">
                <Card>
                  <CardHeader>
                    <CardTitle>Biểu đồ phân tích</CardTitle>
                    <CardDescription>
                      Xem biểu đồ và dữ liệu phân tích từ Facebook
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {analyticsData ? (
                      <div className="space-y-6">
                        <div className="grid grid-cols-3 gap-4">
                          <div className="bg-gray-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-500">Tổng lượt tiếp cận</p>
                            <p className="text-2xl font-bold">{analyticsData.engagement.total_reach.toLocaleString()}</p>
                          </div>
                          <div className="bg-blue-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-500">Tổng lượt hiển thị</p>
                            <p className="text-2xl font-bold">{analyticsData.engagement.total_impressions.toLocaleString()}</p>
                          </div>
                          <div className="bg-green-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-500">Tỷ lệ tương tác</p>
                            <p className="text-2xl font-bold">{analyticsData.engagement.post_engagement_rate}%</p>
                          </div>
                        </div>
                        
                        <div>
                          <h3 className="text-lg font-medium mb-3">Xu hướng theo tuần</h3>
                          <div className="h-64 bg-gray-50 rounded-lg p-4 relative">
                            {/* Simple chart visualization */}
                            <div className="absolute inset-0 flex items-end justify-between p-4">
                              {analyticsData.engagement.weekly_trends.reaches.map((reach: number, i: number) => {
                                const max = Math.max(...analyticsData.engagement.weekly_trends.reaches);
                                const height = (reach / max) * 100;
                                return (
                                  <div key={i} className="flex flex-col items-center w-10">
                                    <div 
                                      className="w-6 bg-blue-500 rounded-t"
                                      style={{ height: `${height}%` }}
                                    ></div>
                                    <p className="text-xs mt-1">{analyticsData.engagement.weekly_trends.dates[i]}</p>
                                  </div>
                                );
                              })}
                            </div>
                          </div>
                        </div>
                      </div>
                    ) : (
                      <div className="py-8 text-center">
                        <p className="text-gray-500">Đang tải dữ liệu...</p>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>
              
              <TabsContent value="ads" className="mt-6">
                <Card>
                  <CardHeader>
                    <CardTitle>Phân tích quảng cáo Facebook</CardTitle>
                    <CardDescription>
                      Xem thông tin chi tiết về hiệu suất các chiến dịch quảng cáo
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {adsData ? (
                      <div className="space-y-8">
                        {/* Summary Cards */}
                        <div className="grid grid-cols-4 gap-4">
                          <div className="bg-indigo-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-500">Tổng chi phí</p>
                            <p className="text-2xl font-bold">{(adsData.ads.summary.total_spend / 1000000).toFixed(2)} tr</p>
                          </div>
                          <div className="bg-sky-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-500">Lượt hiển thị</p>
                            <p className="text-2xl font-bold">{adsData.ads.summary.total_impressions.toLocaleString()}</p>
                          </div>
                          <div className="bg-teal-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-500">Lượt nhấp chuột</p>
                            <p className="text-2xl font-bold">{adsData.ads.summary.total_clicks.toLocaleString()}</p>
                          </div>
                          <div className="bg-orange-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-500">Chuyển đổi</p>
                            <p className="text-2xl font-bold">{adsData.ads.summary.total_conversions.toLocaleString()}</p>
                          </div>
                        </div>
                        
                        {/* Campaigns List */}
                        <div>
                          <h3 className="text-lg font-medium mb-4">Chiến dịch quảng cáo</h3>
                          <div className="space-y-4">
                            {adsData.ads.campaigns.map((campaign: any) => (
                              <div key={campaign.id} className="border rounded-lg p-4">
                                <div className="flex justify-between items-center mb-2">
                                  <h4 className="font-medium">{campaign.name}</h4>
                                  <span className={`px-2 py-1 rounded text-xs ${
                                    campaign.status === 'ACTIVE' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                  }`}>
                                    {campaign.status === 'ACTIVE' ? 'Đang chạy' : 'Tạm dừng'}
                                  </span>
                                </div>
                                
                                <div className="grid grid-cols-2 gap-4 mb-3">
                                  <div>
                                    <span className="text-xs text-gray-500">Ngày bắt đầu:</span>
                                    <p className="text-sm">{new Date(campaign.start_time).toLocaleDateString('vi-VN')}</p>
                                  </div>
                                  <div>
                                    <span className="text-xs text-gray-500">Ngày kết thúc:</span>
                                    <p className="text-sm">{new Date(campaign.end_time).toLocaleDateString('vi-VN')}</p>
                                  </div>
                                </div>
                                
                                <div className="mb-3">
                                  <span className="text-xs text-gray-500">Ngân sách:</span>
                                  <div className="relative pt-1">
                                    <div className="flex mb-2 items-center justify-between">
                                      <div>
                                        <span className="text-xs font-semibold inline-block text-blue-600">
                                          {Math.round((campaign.spent / campaign.budget) * 100)}%
                                        </span>
                                      </div>
                                      <div className="text-right">
                                        <span className="text-xs font-semibold inline-block">
                                          {(campaign.spent / 1000000).toFixed(2)} / {(campaign.budget / 1000000).toFixed(2)} tr
                                        </span>
                                      </div>
                                    </div>
                                    <div className="overflow-hidden h-2 text-xs flex rounded bg-blue-200">
                                      <div 
                                        style={{ width: `${(campaign.spent / campaign.budget) * 100}%` }}
                                        className="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500"
                                      ></div>
                                    </div>
                                  </div>
                                </div>
                                
                                <div className="grid grid-cols-4 gap-2 text-center">
                                  <div className="p-2 bg-gray-50 rounded">
                                    <p className="text-xs text-gray-500">Tiếp cận</p>
                                    <p className="font-medium">{campaign.results.reach.toLocaleString()}</p>
                                  </div>
                                  <div className="p-2 bg-gray-50 rounded">
                                    <p className="text-xs text-gray-500">Hiển thị</p>
                                    <p className="font-medium">{campaign.results.impressions.toLocaleString()}</p>
                                  </div>
                                  <div className="p-2 bg-gray-50 rounded">
                                    <p className="text-xs text-gray-500">Nhấp chuột</p>
                                    <p className="font-medium">{campaign.results.clicks.toLocaleString()}</p>
                                  </div>
                                  <div className="p-2 bg-gray-50 rounded">
                                    <p className="text-xs text-gray-500">CTR</p>
                                    <p className="font-medium">{campaign.results.ctr}%</p>
                                  </div>
                                </div>
                              </div>
                            ))}
                          </div>
                        </div>
                        
                        {/* Performance Charts */}
                        <div>
                          <h3 className="text-lg font-medium mb-4">Hiệu suất quảng cáo theo ngày</h3>
                          <div className="h-64 bg-gray-50 rounded-lg p-4 relative">
                            <div className="absolute inset-0 flex items-end justify-between p-4">
                              {adsData.ads.performance.daily.dates.map((date: string, i: number) => {
                                const max = Math.max(...adsData.ads.performance.daily.impressions);
                                const height = (adsData.ads.performance.daily.impressions[i] / max) * 100;
                                return (
                                  <div key={i} className="flex flex-col items-center w-16">
                                    <div className="w-10 bg-blue-500 rounded-t" style={{ height: `${height * 0.7}%` }}></div>
                                    <p className="text-xs mt-1">{date}</p>
                                  </div>
                                );
                              })}
                            </div>
                          </div>
                        </div>
                        
                        {/* Demographics */}
                        <div>
                          <h3 className="text-lg font-medium mb-4">Thống kê đối tượng quảng cáo</h3>
                          <div className="grid grid-cols-2 gap-6">
                            <div>
                              <h4 className="text-sm font-medium mb-2">Phân bố theo khu vực</h4>
                              <div className="space-y-2">
                                {adsData.ads.performance.demographics.regions.map((region: any) => (
                                  <div key={region.name} className="flex items-center">
                                    <span className="w-24 text-sm">{region.name}</span>
                                    <div className="flex-1 mx-2">
                                      <div className="h-4 bg-gray-200 rounded-full overflow-hidden">
                                        <div 
                                          className="h-full bg-blue-500" 
                                          style={{ width: `${region.value}%` }}
                                        ></div>
                                      </div>
                                    </div>
                                    <span className="text-sm">{region.value}%</span>
                                  </div>
                                ))}
                              </div>
                            </div>
                            
                            <div>
                              <h4 className="text-sm font-medium mb-2">Phân bố theo độ tuổi và giới tính</h4>
                              <div className="space-y-2">
                                {adsData.ads.performance.demographics.age_gender.map((item: any) => (
                                  <div key={item.age} className="grid grid-cols-3 gap-2">
                                    <div className="text-sm">{item.age}</div>
                                    <div className="flex items-center">
                                      <div className="w-full bg-gray-200 rounded-full h-2.5">
                                        <div className="bg-blue-500 h-2.5 rounded-full" style={{ width: `${item.male}%` }}></div>
                                      </div>
                                      <span className="ml-2 text-xs">{item.male}%</span>
                                    </div>
                                    <div className="flex items-center">
                                      <div className="w-full bg-gray-200 rounded-full h-2.5">
                                        <div className="bg-pink-500 h-2.5 rounded-full" style={{ width: `${item.female}%` }}></div>
                                      </div>
                                      <span className="ml-2 text-xs">{item.female}%</span>
                                    </div>
                                  </div>
                                ))}
                              </div>
                              <div className="flex justify-center mt-2 text-xs text-gray-500 space-x-4">
                                <div className="flex items-center">
                                  <div className="w-3 h-3 bg-blue-500 rounded-full mr-1"></div>
                                  <span>Nam</span>
                                </div>
                                <div className="flex items-center">
                                  <div className="w-3 h-3 bg-pink-500 rounded-full mr-1"></div>
                                  <span>Nữ</span>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    ) : (
                      <div className="py-8 text-center">
                        <p className="text-gray-500">Đang tải dữ liệu...</p>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>
            </Tabs>
          </div>
        )}
      </div>
    </AppShell>
  )
} 