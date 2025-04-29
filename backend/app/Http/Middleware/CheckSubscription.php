<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $feature = null): Response
    {
        $user = $request->user();
        
        // SuperAdmin bypass all checks
        if ($user && $user->role === 'superadmin') {
            return $next($request);
        }
        
        $businessId = $request->input('business_id');
        
        // Nếu không có business_id, chuyển sang route tiếp theo
        if (!$businessId) {
            return $next($request);
        }
        
        // Kiểm tra user có thuộc business không
        if (!$user->businesses()->where('businesses.id', $businessId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập vào doanh nghiệp này'
            ], 403);
        }
        
        // Nếu có yêu cầu kiểm tra feature cụ thể
        if ($feature) {
            $business = \App\Models\Business::find($businessId);
            
            if (!$business || !$business->hasActiveSubscription()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Doanh nghiệp không có subscription hợp lệ'
                ], 403);
            }
            
            if (!$business->hasFeature($feature)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tính năng này không được hỗ trợ trong gói dịch vụ hiện tại'
                ], 403);
            }
        }
        
        return $next($request);
    }
}
