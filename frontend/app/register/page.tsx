"use client"

import { useState } from "react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"

export default function RegisterPage() {
  const router = useRouter()
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState("")
  
  // Registration form state
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    business_name: "",
    business_description: ""
  })
  
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target
    setFormData(prev => ({ ...prev, [name]: value }))
  }
  
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError("")
    
    try {
      // Call API to register user and business
      const response = await fetch("/api/register", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify(formData)
      })
      
      const data = await response.json()
      
      if (!response.ok) {
        if (data.errors) {
          // Format validation errors
          const errorMessages = Object.entries(data.errors)
            .map(([field, messages]) => `${field}: ${(messages as string[]).join(", ")}`)
            .join("\n")
          
          throw new Error(errorMessages)
        } else {
          throw new Error(data.message || "Đăng ký thất bại")
        }
      }
      
      // Store token
      localStorage.setItem("auth_token", data.token)
      
      // Redirect to dashboard
      router.push("/dashboard")
    } catch (err) {
      setError((err as Error).message)
    } finally {
      setLoading(false)
    }
  }
  
  const handleOAuthLogin = (provider: string) => {
    window.location.href = `/oauth/${provider}`
  }
  
  return (
    <div className="container relative flex-col items-center justify-center h-screen md:grid lg:max-w-none lg:grid-cols-2 lg:px-0">
      <div className="relative flex-col hidden h-full p-10 text-white bg-muted md:flex dark:border-r lg:flex">
        <div className="absolute inset-0 bg-blue-900" />
        <div className="relative z-20 mt-auto">
          <blockquote className="space-y-2">
            <p className="text-lg">
              "Nền tảng này đã giúp chúng tôi tự động hóa quá trình phân tích kinh doanh và tiết kiệm hàng giờ mỗi tuần."
            </p>
            <footer className="text-sm">
              Nguyễn Văn A - Giám đốc Công ty ABC
            </footer>
          </blockquote>
        </div>
      </div>
      <div className="lg:p-8">
        <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px] lg:w-[500px]">
          <div className="flex flex-col space-y-2 text-center">
            <h1 className="text-2xl font-semibold tracking-tight">
              Tạo tài khoản và doanh nghiệp
            </h1>
            <p className="text-sm text-muted-foreground">
              Nhập thông tin của bạn bên dưới để tạo tài khoản và doanh nghiệp
            </p>
          </div>
          
          {error && (
            <div className="p-4 bg-red-50 border border-red-200 rounded-md">
              <p className="text-sm text-red-600 whitespace-pre-line">{error}</p>
            </div>
          )}
          
          <Card>
            <form onSubmit={handleSubmit}>
              <CardHeader>
                <CardTitle>Thông tin tài khoản</CardTitle>
                <CardDescription>
                  Nhập thông tin cá nhân và doanh nghiệp của bạn
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Họ và tên</Label>
                  <Input 
                    id="name" 
                    name="name" 
                    type="text" 
                    placeholder="Nguyễn Văn A" 
                    value={formData.name}
                    onChange={handleInputChange}
                    required 
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="email">Email</Label>
                  <Input 
                    id="email" 
                    name="email" 
                    type="email" 
                    placeholder="example@gmail.com" 
                    value={formData.email}
                    onChange={handleInputChange}
                    required 
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="password">Mật khẩu</Label>
                  <Input 
                    id="password" 
                    name="password" 
                    type="password" 
                    value={formData.password}
                    onChange={handleInputChange}
                    required 
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="password_confirmation">Xác nhận mật khẩu</Label>
                  <Input 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    type="password" 
                    value={formData.password_confirmation}
                    onChange={handleInputChange}
                    required 
                  />
                </div>
                
                <div className="pt-4 border-t">
                  <CardTitle className="mb-4 text-lg">Thông tin doanh nghiệp</CardTitle>
                  <div className="space-y-2">
                    <Label htmlFor="business_name">Tên doanh nghiệp</Label>
                    <Input 
                      id="business_name" 
                      name="business_name" 
                      type="text" 
                      placeholder="Công ty ABC" 
                      value={formData.business_name}
                      onChange={handleInputChange}
                      required 
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="business_description">Mô tả</Label>
                    <Input 
                      id="business_description" 
                      name="business_description" 
                      placeholder="Mô tả ngắn về doanh nghiệp" 
                      value={formData.business_description}
                      onChange={handleInputChange}
                    />
                  </div>
                </div>
                
                <div className="pt-3">
                  <Button className="w-full" type="submit" disabled={loading}>
                    {loading ? "Đang xử lý..." : "Đăng ký"}
                  </Button>
                </div>
              </CardContent>
            </form>
              
            <CardContent>
              <div className="relative my-2">
                <div className="absolute inset-0 flex items-center">
                  <Separator className="w-full" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                  <span className="bg-background px-2 text-muted-foreground">Hoặc đăng ký với</span>
                </div>
              </div>
              
              <div className="grid gap-2 mt-4">
                <Button 
                  type="button"
                  variant="outline"
                  className="bg-[#1877F2] text-white hover:bg-[#0C63D4] hover:text-white"
                  onClick={() => handleOAuthLogin("facebook")}
                  disabled={loading}
                >
                  <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M9.19795 21.5H13.198V13.4901H16.8021L17.198 9.50977H13.198V7.5C13.198 6.94772 13.6457 6.5 14.198 6.5H17.198V2.5H14.198C11.4365 2.5 9.19795 4.73858 9.19795 7.5V9.50977H7.19795L6.80206 13.4901H9.19795V21.5Z"></path>
                  </svg>
                  Đăng ký với Facebook
                </Button>
                
                <Button 
                  type="button"
                  variant="outline"
                  className="bg-white text-gray-900 border border-gray-300 hover:bg-gray-50"
                  onClick={() => handleOAuthLogin("google")}
                  disabled={loading}
                >
                  <svg className="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                  </svg>
                  Đăng ký với Google
                </Button>
              </div>
            </CardContent>
            
            <CardFooter>
              <p className="w-full text-center text-sm text-muted-foreground">
                Đã có tài khoản?{" "}
                <Link href="/login" className="underline underline-offset-4 hover:text-primary">
                  Đăng nhập
                </Link>
              </p>
            </CardFooter>
          </Card>
        </div>
      </div>
    </div>
  )
} 