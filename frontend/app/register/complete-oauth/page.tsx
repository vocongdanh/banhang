"use client"

import { useState, useEffect } from "react"
import { useRouter, useSearchParams } from "next/navigation"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

export default function CompleteOAuthRegistrationPage() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState("")
  
  // Form state
  const [formData, setFormData] = useState({
    business_name: "",
    business_description: ""
  })
  
  useEffect(() => {
    // Check if OAuth data is in session
    // In a real app, we'd make an API call to check session state
    const provider = searchParams?.get('provider')
    if (provider) {
      // Just for UI indication, the actual check is server-side
      console.log(`Completing registration for ${provider} user`)
    }
  }, [searchParams])
  
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target
    setFormData(prev => ({ ...prev, [name]: value }))
  }
  
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError("")
    
    try {
      // Call API to complete OAuth registration with business info
      const response = await fetch("/api/register/complete-oauth", {
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
          throw new Error(data.message || "Không thể hoàn tất đăng ký")
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
  
  return (
    <div className="container flex items-center justify-center min-h-screen py-12">
      <Card className="w-full max-w-md">
        <form onSubmit={handleSubmit}>
          <CardHeader>
            <CardTitle>Hoàn tất đăng ký</CardTitle>
            <CardDescription>
              Vui lòng cung cấp thông tin doanh nghiệp của bạn để hoàn tất đăng ký
            </CardDescription>
          </CardHeader>
          
          {error && (
            <div className="px-6 -mt-2">
              <div className="p-4 bg-red-50 border border-red-200 rounded-md">
                <p className="text-sm text-red-600 whitespace-pre-line">{error}</p>
              </div>
            </div>
          )}
          
          <CardContent className="space-y-4">
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
          </CardContent>
          <CardFooter>
            <Button className="w-full" type="submit" disabled={loading}>
              {loading ? "Đang xử lý..." : "Hoàn tất đăng ký"}
            </Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  )
} 