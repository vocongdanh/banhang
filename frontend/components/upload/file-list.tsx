"use client"

import { useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { DownloadIcon, FileIcon, Trash2Icon } from "lucide-react"
import { toast } from "@/components/ui/use-toast"

interface FileData {
  id: string
  name: string
  size: number
  uploaded_at: string
  type: string
  url: string
}

interface FileListProps {
  refreshTrigger: number
}

export function FileList({ refreshTrigger }: FileListProps) {
  const [files, setFiles] = useState<FileData[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchFiles = async () => {
      setLoading(true)
      try {
        // Lấy token xác thực từ localStorage
        const token = localStorage.getItem('auth_token');
        
        // Sử dụng URL backend thay vì API route nội bộ
        const apiUrl = process.env.NEXT_PUBLIC_API_URL ? `${process.env.NEXT_PUBLIC_API_URL}/files` : "http://localhost:8001/api/files";
        
        const response = await fetch(apiUrl, {
          headers: token ? {
            'Authorization': `Bearer ${token}`
          } : {},
          credentials: 'include',
        })
        
        if (!response.ok) {
          throw new Error("Failed to fetch files")
        }
        const data = await response.json()
        setFiles(data)
      } catch (error) {
        console.error("Error fetching files:", error)
        toast({
          title: "Lỗi",
          description: "Không thể tải danh sách tệp tin",
          variant: "destructive",
        })
      } finally {
        setLoading(false)
      }
    }

    fetchFiles()
  }, [refreshTrigger])

  const handleDelete = async (id: string) => {
    if (!confirm("Bạn có chắc chắn muốn xóa tệp tin này?")) {
      return
    }

    try {
      // Lấy token xác thực từ localStorage
      const token = localStorage.getItem('auth_token');
      
      // Sử dụng URL backend thay vì API route nội bộ
      const apiUrl = process.env.NEXT_PUBLIC_API_URL ? `${process.env.NEXT_PUBLIC_API_URL}/files/${id}` : `http://localhost:8001/api/files/${id}`;
      
      const response = await fetch(apiUrl, {
        method: "DELETE",
        headers: token ? {
          'Authorization': `Bearer ${token}`
        } : {},
        credentials: 'include',
      })

      if (!response.ok) {
        throw new Error("Failed to delete file")
      }

      setFiles(files.filter((file) => file.id !== id))
      toast({
        title: "Thành công",
        description: "Tệp tin đã được xóa thành công",
      })
    } catch (error) {
      console.error("Delete error:", error)
      toast({
        title: "Lỗi",
        description: "Không thể xóa tệp tin",
        variant: "destructive",
      })
    }
  }

  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + " B"
    else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB"
    else return (bytes / 1048576).toFixed(1) + " MB"
  }

  const formatDate = (dateString: string) => {
    const date = new Date(dateString)
    return date.toLocaleDateString("vi-VN", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    })
  }

  const getFileIcon = (type: string) => {
    return <FileIcon className="h-8 w-8 text-blue-500" />
  }

  if (loading) {
    return (
      <div className="flex justify-center items-center h-40">
        <p className="text-sm text-gray-500">Đang tải danh sách tệp tin...</p>
      </div>
    )
  }

  if (files.length === 0) {
    return (
      <div className="flex justify-center items-center h-40 border border-dashed rounded-lg">
        <p className="text-sm text-gray-500">Chưa có tệp tin nào được tải lên</p>
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {files.map((file) => (
        <Card key={file.id}>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                {getFileIcon(file.type)}
                <div>
                  <h3 className="font-medium text-sm truncate max-w-[200px] md:max-w-[300px]">
                    {file.name}
                  </h3>
                  <div className="flex gap-2 text-xs text-gray-500">
                    <span>{formatFileSize(file.size)}</span>
                    <span>•</span>
                    <span>{formatDate(file.uploaded_at)}</span>
                  </div>
                </div>
              </div>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  className="h-8 w-8 p-0"
                  onClick={() => window.open(file.url, "_blank")}
                >
                  <DownloadIcon className="h-4 w-4" />
                  <span className="sr-only">Download</span>
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  className="h-8 w-8 p-0 text-red-500 hover:text-red-600"
                  onClick={() => handleDelete(file.id)}
                >
                  <Trash2Icon className="h-4 w-4" />
                  <span className="sr-only">Delete</span>
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
} 