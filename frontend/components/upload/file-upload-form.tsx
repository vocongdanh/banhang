"use client"

import { useState, useEffect } from "react"
import { useRouter } from "next/navigation"
import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { UploadIcon } from "lucide-react"
import { useToast } from "@/components/ui/use-toast"

export interface FileUploadFormProps {
  onUploadSuccess?: () => void
}

export function FileUploadForm({ onUploadSuccess }: FileUploadFormProps) {
  const [file, setFile] = useState<File | null>(null)
  const [isUploading, setIsUploading] = useState(false)
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const router = useRouter()
  const { toast } = useToast()

  // Kiểm tra xem người dùng đã đăng nhập chưa
  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    setIsLoggedIn(!!token);
  }, []);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setFile(e.target.files[0])
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!file) {
      toast({
        title: "Lỗi",
        description: "Vui lòng chọn tệp tin để tải lên",
        variant: "destructive"
      })
      return
    }

    setIsUploading(true)
    
    try {
      const formData = new FormData()
      formData.append("file", file)
      formData.append("data_type", "general")
      
      // Lấy token xác thực từ localStorage
      const token = localStorage.getItem('auth_token');
      
      // Log để debug
      console.log("Token exists:", !!token);
      console.log("API URL:", process.env.NEXT_PUBLIC_API_URL || "undefined");
      
      // Hardcode API URL nếu biến môi trường không tồn tại
      const apiUrl = process.env.NEXT_PUBLIC_API_URL ? `${process.env.NEXT_PUBLIC_API_URL}/import` : "http://localhost:8001/api/import";
      console.log("Gọi API tại:", apiUrl);
      
      // Gọi API với header Authorization nếu có token
      const response = await fetch(apiUrl, {
        method: "POST",
        headers: token ? {
          'Authorization': `Bearer ${token}`
        } : {},
        body: formData,
        credentials: 'include',
      })

      console.log("Response status:", response.status);
      
      if (!response.ok) {
        const errorData = await response.json()
        console.error("API error:", errorData);
        throw new Error(errorData.message || "Lỗi khi tải lên tệp tin")
      }

      const result = await response.json();
      console.log("Upload success:", result);
      
      toast({
        title: "Thành công",
        description: "Tệp tin đã được tải lên thành công",
      })

      setFile(null)
      // Reset file input
      const fileInput = document.getElementById("file") as HTMLInputElement
      if (fileInput) fileInput.value = ""
      
      // Trigger refresh of file list
      if (onUploadSuccess) onUploadSuccess()
      
      // Redirect back to data page
      router.push("/data")
    } catch (error) {
      console.error("Upload error:", error)
      toast({
        title: "Lỗi",
        description: error instanceof Error ? error.message : "Lỗi không xác định khi tải lên tệp tin",
        variant: "destructive"
      })
    } finally {
      setIsUploading(false)
    }
  }

  // Hiển thị thông báo nếu chưa đăng nhập
  if (!isLoggedIn) {
    return (
      <Card className="p-6">
        <div className="p-4 bg-yellow-50 border border-yellow-300 rounded-md">
          <h3 className="text-lg font-semibold mb-2">Bạn cần đăng nhập trước</h3>
          <p>Vui lòng <a href="/login" className="text-blue-600 hover:underline">đăng nhập</a> để sử dụng tính năng này.</p>
        </div>
      </Card>
    );
  }

  return (
    <Card className="p-6">
      <form onSubmit={handleSubmit} className="space-y-6">
        <div className="space-y-2">
          <Label htmlFor="file">Chọn tệp tin</Label>
          <Input
            id="file"
            type="file"
            onChange={handleFileChange}
            accept=".pdf,.csv,.xlsx,.xls,.txt,.png,.jpg,.jpeg,.doc,.docx,.ppt,.pptx,.rtf"
            disabled={isUploading}
          />
          <p className="text-sm text-muted-foreground">
            Hỗ trợ các định dạng: PDF, Excel, CSV, TXT, PNG, JPG, DOC, DOCX, PPT, PPTX, RTF
          </p>
        </div>

        <Button 
          type="submit" 
          disabled={!file || isUploading}
          className="w-full"
        >
          {isUploading ? (
            <span className="flex items-center">
              <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Đang tải lên...
            </span>
          ) : (
            <span className="flex items-center">
              <UploadIcon className="mr-2 h-5 w-5" />
              Tải lên
            </span>
          )}
        </Button>
      </form>
    </Card>
  )
} 