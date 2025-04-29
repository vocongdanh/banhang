<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            session()->regenerate();
            
            // Lấy user với relationship businesses
            $user = User::with('businesses')->find(Auth::id());
            
            // Create token for API access
            $token = $user->createToken('auth-token')->plainTextToken;
            
            return response()->json([
                'user' => $user,
                'token' => $token,
                'message' => 'Đăng nhập thành công'
            ]);
        }

        return response()->json([
            'message' => 'Thông tin đăng nhập không chính xác'
        ], 401);
    }

    public function destroy(Request $request)
    {
        if ($request->user()) {
            // Revoke all tokens
            $request->user()->tokens()->delete();
        }
        
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ]);
    }
} 