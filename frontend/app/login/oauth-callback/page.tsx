"use client"

import { useEffect, useState } from "react"
import { useRouter, useSearchParams } from "next/navigation"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"

export default function OAuthCallbackPage() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const [isProcessing, setIsProcessing] = useState(true)
  const [error, setError] = useState("")
  
  useEffect(() => {
    const token = searchParams?.get('token')
    const error = searchParams?.get('error')
    
    if (error) {
      setError(decodeURIComponent(error))
      setIsProcessing(false)
      return
    }
    
    if (token) {
      // Store token
      localStorage.setItem("auth_token", token)
      
      // Redirect to dashboard after short delay
      setTimeout(() => {
        router.push("/dashboard")
      }, 1000)
    } else {
      setError("Không thể xác thực. Thiếu token xác thực.")
      setIsProcessing(false)
    }
  }, [searchParams, router])
  
  return (
    <div className="container flex items-center justify-center min-h-screen py-12">
      <Card className="w-[400px]">
        <CardHeader>
          <CardTitle className="text-center">
            {isProcessing ? "Đang xử lý đăng nhập..." : error ? "Lỗi xác thực" : "Đăng nhập thành công"}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {isProcessing ? (
            <div className="flex justify-center py-4">
              <div className="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
          ) : error ? (
            <div className="text-center text-red-500">
              {error}
              <div className="mt-4">
                <button 
                  onClick={() => router.push('/login')}
                  className="text-blue-500 hover:underline"
                >
                  Quay lại trang đăng nhập
                </button>
              </div>
            </div>
          ) : (
            <div className="text-center text-green-500">
              Đăng nhập thành công! Đang chuyển hướng...
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
} 