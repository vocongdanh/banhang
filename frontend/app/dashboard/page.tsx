"use client"

import { useState, useEffect } from "react"
import { getUser } from "@/lib/api-client"
import { AppShell } from "@/components/layout/app-shell"

export default function DashboardPage() {
  const [user, setUser] = useState<any>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  
  // Try to get user data in background
  useEffect(() => {
    const token = localStorage.getItem('auth_token')
    if (!token) return
    
    async function fetchUser() {
      try {
        setLoading(true)
        const userData = await getUser()
        setUser(userData)
        setError(null)
      } catch (error) {
        console.error("Error fetching user:", error)
        setError(error instanceof Error ? error.message : "Lỗi không xác định")
      } finally {
        setLoading(false)
      }
    }
    
    fetchUser()
  }, [])
  
  return (
    <AppShell>
      <div className="p-8">
        <h1 className="text-3xl font-bold mb-6">Tổng quan</h1>
        
        {/* Hiển thị lỗi nếu có */}
        {error && (
          <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div className="text-red-500 mb-2">Lỗi khi tải dữ liệu: {error}</div>
            <button 
              onClick={() => {
                setLoading(true)
                setError(null)
                getUser()
                  .then(userData => {
                    setUser(userData)
                    setLoading(false)
                  })
                  .catch(err => {
                    setError(err.message || "Lỗi không xác định")
                    setLoading(false)
                  })
              }} 
              className="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm"
            >
              Thử lại
            </button>
          </div>
        )}
        
        {/* Hiển thị loading spinner */}
        {loading && (
          <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg flex items-center">
            <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mr-3"></div>
            <span>Đang tải thông tin...</span>
          </div>
        )}
        
        {/* Nội dung chính */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Thông tin người dùng */}
          <div className="lg:col-span-1">
            {user ? (
              <div className="p-6 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold mb-3">Thông tin người dùng</h2>
                <p className="mb-1"><span className="font-medium">Tên:</span> {user.name}</p>
                <p className="mb-1"><span className="font-medium">Email:</span> {user.email}</p>
                <p><span className="font-medium">Vai trò:</span> {user.role || "Người dùng"}</p>
              </div>
            ) : (
              <div className="p-6 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold mb-2">Thông tin người dùng</h2>
                <p className="text-gray-500">Thông tin chi tiết không khả dụng.</p>
              </div>
            )}
          </div>
          
          {/* Phần nội dung chính */}
          <div className="lg:col-span-2">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="p-6 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold mb-2">Tổng quan</h2>
                <p>Đây là trang tổng quan của ứng dụng quản lý bán hàng.</p>
                <div className="mt-4 grid grid-cols-2 gap-3">
                  <div className="p-3 bg-blue-50 rounded-lg">
                    <div className="text-xl font-bold">0</div>
                    <div className="text-sm text-gray-500">Đơn hàng</div>
                  </div>
                  <div className="p-3 bg-green-50 rounded-lg">
                    <div className="text-xl font-bold">0</div>
                    <div className="text-sm text-gray-500">Sản phẩm</div>
                  </div>
                </div>
              </div>
              
              <div className="p-6 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold mb-2">Trạng thái hệ thống</h2>
                <p>Ứng dụng đang hoạt động bình thường.</p>
                <div className="mt-4">
                  <div className="flex items-center mb-2">
                    <div className="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                    <span>Frontend</span>
                  </div>
                  <div className="flex items-center">
                    <div className="w-3 h-3 rounded-full bg-yellow-500 mr-2"></div>
                    <span>API Backend</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  )
} 